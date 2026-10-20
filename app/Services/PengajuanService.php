<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Dosen;
use App\Models\LogAktivitas;
use App\Models\Mahasiswa;
use App\Models\Pengajuan;
use App\Models\PengajuanDokumen;
use App\Models\Pengaturan;
use App\Models\PeriodeAkademik;
use App\Models\Persyaratan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Satu-satunya pintu untuk mengubah status pengajuan TA/KP.
 *
 * Status gabungan (`status`) selalu DITURUNKAN dari dua dimensi yang sengaja
 * dipisah: status_administrasi (Operator Akademik) dan status_akademik (Dospem).
 * Service ini hanya memeriksa kondisi data/status; pemeriksaan "siapa boleh"
 * dilakukan oleh PengajuanPolicy di controller.
 */
class PengajuanService
{
    public const DISK = 'local'; // disk privat: berkas hanya terbuka lewat route terotorisasi

    public function buat(Mahasiswa $mahasiswa, JenisPengajuan $jenis, array $data, User $actor): Pengajuan
    {
        $mahasiswa->loadMissing('prodi');
        if (! $mahasiswa->prodi_id || ! $mahasiswa->prodi?->aktif) {
            throw new WorkflowException('Program studi pada data akademik Anda belum valid. Hubungi Admin untuk memperbaikinya.');
        }

        return DB::transaction(function () use ($mahasiswa, $jenis, $data, $actor) {
            // Kunci baris mahasiswa agar dua request bersamaan tidak membuat pengajuan ganda.
            Mahasiswa::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();

            if ($mahasiswa->pengajuanBerjalan($jenis)) {
                throw new WorkflowException("Anda masih memiliki pengajuan {$jenis->singkat()} yang sedang berjalan.");
            }

            $pengajuan = new Pengajuan(Arr::only($data, (new Pengajuan)->getFillable()));
            $pengajuan->forceFill([
                'mahasiswa_id' => $mahasiswa->id,
                'prodi_id' => $mahasiswa->prodi_id,
                'periode_akademik_id' => PeriodeAkademik::query()->aktif()->latest('id')->value('id'),
                'jenis' => $jenis,
                'status' => StatusPengajuan::Draft,
                'status_administrasi' => StatusAdministrasi::Menunggu,
                'status_akademik' => StatusAkademik::Menunggu,
            ])->save();

            LogAktivitas::catat($actor, 'pengajuan_dibuat', "Draft pengajuan {$jenis->singkat()} dibuat.", $pengajuan);

            return $pengajuan;
        });
    }

    public function perbarui(Pengajuan $pengajuan, array $data, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($data, $actor) {
            if (! $p->bolehDiubahMahasiswa()) {
                throw new WorkflowException("Pengajuan tidak dapat diubah karena berstatus \"{$p->status->label()}\".");
            }

            $p->fill(Arr::only($data, $p->getFillable()))->save();
            LogAktivitas::catat($actor, 'pengajuan_diubah', 'Data pengajuan diperbarui.', $p);
        });
    }

    public function unggahDokumen(Pengajuan $pengajuan, Persyaratan $persyaratan, UploadedFile $file, User $actor): PengajuanDokumen
    {
        $this->pastikanPersyaratanBerlaku($pengajuan, $persyaratan);

        $lama = $pengajuan->dokumens()->where('persyaratan_id', $persyaratan->id)->first();
        $path = $file->store("pengajuan/{$pengajuan->id}", self::DISK);

        try {
            $dokumen = DB::transaction(function () use ($pengajuan, $persyaratan, $file, $path, $actor) {
                $dokumen = PengajuanDokumen::query()->updateOrCreate(
                    ['pengajuan_id' => $pengajuan->id, 'persyaratan_id' => $persyaratan->id],
                    ['file_path' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'uploaded_by' => $actor->id],
                );
                LogAktivitas::catat($actor, 'dokumen_diunggah', "Dokumen \"{$persyaratan->nama}\" diunggah.", $pengajuan);

                return $dokumen;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }

        if ($lama && $lama->file_path !== $path) {
            Storage::disk(self::DISK)->delete($lama->file_path);
        }

        return $dokumen;
    }

    public function hapusDokumen(PengajuanDokumen $dokumen, User $actor): void
    {
        $pengajuan = $dokumen->pengajuan;
        $lampiran = $dokumen->persyaratan?->jenis === JenisPersyaratan::KpLampiran;

        if (! $lampiran && ! $pengajuan->bolehDiubahMahasiswa()) {
            throw new WorkflowException('Dokumen tidak dapat dihapus karena pengajuan sedang diproses.');
        }

        $path = $dokumen->file_path;
        $nama = $dokumen->persyaratan?->nama ?? 'dokumen';
        $dokumen->delete();
        Storage::disk(self::DISK)->delete($path);
        LogAktivitas::catat($actor, 'dokumen_dihapus', "Dokumen \"{$nama}\" dihapus.", $pengajuan);
    }

    /** Mahasiswa mengirim (atau mengirim ulang setelah revisi) pengajuannya. */
    public function kirim(Pengajuan $pengajuan, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor) {
            if (! $p->bolehDiubahMahasiswa()) {
                throw new WorkflowException("Pengajuan tidak dapat dikirim karena berstatus \"{$p->status->label()}\".");
            }

            $this->pastikanDataLengkap($p);

            $ulang = $p->diajukan_pada !== null;

            // Bagian yang diminta revisi kembali menunggu pemeriksaan.
            if ($p->status_administrasi === StatusAdministrasi::Revisi) {
                $p->status_administrasi = StatusAdministrasi::Menunggu;
            }
            if ($p->status_akademik === StatusAkademik::Revisi) {
                $p->status_akademik = StatusAkademik::Menunggu;
            }
            $p->diajukan_pada ??= now();

            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat(
                $actor,
                $ulang ? 'pengajuan_diajukan_ulang' : 'pengajuan_diajukan',
                $ulang ? 'Pengajuan diperbaiki dan diajukan ulang.' : 'Pengajuan dikirim untuk diverifikasi.',
                $p,
            );
        });
    }

    /** Hanya draft yang belum pernah dikirim yang boleh dihapus mahasiswa. */
    public function batalkan(Pengajuan $pengajuan, User $actor): void
    {
        if ($pengajuan->status !== StatusPengajuan::Draft || $pengajuan->diajukan_pada !== null) {
            throw new WorkflowException('Hanya draft yang belum dikirim yang dapat dihapus.');
        }

        $berkas = $pengajuan->dokumens()->pluck('file_path')->all();
        $jenis = $pengajuan->jenis->singkat();

        DB::transaction(function () use ($pengajuan, $actor, $jenis) {
            LogAktivitas::query()->where('subjek_type', $pengajuan->getMorphClass())->where('subjek_id', $pengajuan->id)->delete();
            $pengajuan->delete();
            LogAktivitas::catat($actor, 'pengajuan_dihapus', "Draft pengajuan {$jenis} dihapus oleh mahasiswa.");
        });

        Storage::disk(self::DISK)->delete($berkas);
    }

    // ------------------------------------------------------------------
    // Operator Akademik — verifikasi administrasi, penetapan dospem, arsip
    // ------------------------------------------------------------------

    public function verifikasiAdministrasi(Pengajuan $pengajuan, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor) {
            if ($p->status !== StatusPengajuan::Diajukan || $p->status_administrasi !== StatusAdministrasi::Menunggu) {
                throw new WorkflowException("Verifikasi administrasi tidak dapat dilakukan pada status \"{$p->status->label()}\".");
            }

            $kurang = $p->persyaratanWajibKurang();
            if ($kurang->isNotEmpty()) {
                throw new WorkflowException('Dokumen wajib belum lengkap: '.$kurang->pluck('nama')->implode(', ').'.');
            }

            $p->status_administrasi = StatusAdministrasi::Terverifikasi;
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'verifikasi_administrasi', 'Kelengkapan administrasi diverifikasi.', $p);
        });
    }

    public function kembalikanAdministrasi(Pengajuan $pengajuan, User $actor, string $catatan): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor, $catatan) {
            $statusBoleh = in_array($p->status, [StatusPengajuan::Diajukan, StatusPengajuan::Disetujui], true);
            $admBoleh = in_array($p->status_administrasi, [StatusAdministrasi::Menunggu, StatusAdministrasi::Terverifikasi], true);
            if (! $statusBoleh || ! $admBoleh) {
                throw new WorkflowException("Pengajuan tidak dapat dikembalikan pada status \"{$p->status->label()}\".");
            }

            $p->status_administrasi = StatusAdministrasi::Revisi;
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'revisi_administrasi', $catatan, $p);
        });
    }

    public function tetapkanDospem(Pengajuan $pengajuan, Dosen $dosen, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($dosen, $actor) {
            $boleh = [StatusPengajuan::Diajukan, StatusPengajuan::PerluRevisi, StatusPengajuan::Disetujui];
            if (! in_array($p->status, $boleh, true)) {
                throw new WorkflowException("Dosen pembimbing tidak dapat ditetapkan pada status \"{$p->status->label()}\".");
            }
            if (! $dosen->layakMembimbing()) {
                throw new WorkflowException('Dosen tersebut tidak valid sebagai pembimbing (tidak aktif atau belum memiliki akun Dospem).');
            }
            if ($p->dospem_id === $dosen->id) {
                return;
            }

            $p->dospem_id = $dosen->id;
            // Pembimbing baru memeriksa dari awal.
            if ($p->status_akademik !== StatusAkademik::Menunggu) {
                $p->status_akademik = StatusAkademik::Menunggu;
            }
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'tetapkan_dospem', "Dosen pembimbing ditetapkan: {$dosen->nama} (NIP {$dosen->nip}).", $p);
        });
    }

    /** KP tidak menerbitkan SK; Operator menutup administrasinya setelah disetujui. */
    public function selesaikanKp(Pengajuan $pengajuan, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor) {
            if ($p->jenis !== JenisPengajuan::Kp || $p->status !== StatusPengajuan::Disetujui) {
                throw new WorkflowException('Hanya pengajuan KP yang sudah disetujui yang dapat diselesaikan.');
            }

            $p->status = StatusPengajuan::Selesai;
            $p->selesai_pada = now();
            $p->save();

            LogAktivitas::catat($actor, 'kp_selesai', 'Administrasi KP diselesaikan dan diarsipkan.', $p);
        });
    }

    // ------------------------------------------------------------------
    // Dosen Pembimbing — pemeriksaan akademik
    // ------------------------------------------------------------------

    public function setujuiAkademik(Pengajuan $pengajuan, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor) {
            if (! $p->dospem_id) {
                throw new WorkflowException('Dosen pembimbing belum ditetapkan.');
            }
            if ($p->status !== StatusPengajuan::Diajukan || $p->status_akademik !== StatusAkademik::Menunggu) {
                throw new WorkflowException("Persetujuan akademik tidak dapat diberikan pada status \"{$p->status->label()}\".");
            }

            $p->status_akademik = StatusAkademik::Disetujui;
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'setujui_akademik', 'Pengajuan disetujui secara akademik.', $p);
        });
    }

    public function revisiAkademik(Pengajuan $pengajuan, User $actor, string $catatan): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor, $catatan) {
            $statusBoleh = in_array($p->status, [StatusPengajuan::Diajukan, StatusPengajuan::Disetujui], true);
            $akdBoleh = in_array($p->status_akademik, [StatusAkademik::Menunggu, StatusAkademik::Disetujui], true);
            if (! $p->dospem_id || ! $statusBoleh || ! $akdBoleh) {
                throw new WorkflowException("Revisi tidak dapat diminta pada status \"{$p->status->label()}\".");
            }

            $p->status_akademik = StatusAkademik::Revisi;
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'revisi_akademik', $catatan, $p);
        });
    }

    // ------------------------------------------------------------------

    /** Turunkan status gabungan dari dua dimensi persetujuan. SK/arsip menetapkan Selesai sendiri. */
    public function sinkronkan(Pengajuan $p): void
    {
        if ($p->status === StatusPengajuan::Selesai) {
            return;
        }

        $p->status = match (true) {
            $p->diajukan_pada === null => StatusPengajuan::Draft,
            $p->status_administrasi === StatusAdministrasi::Revisi,
            $p->status_akademik === StatusAkademik::Revisi => StatusPengajuan::PerluRevisi,
            $p->status_administrasi === StatusAdministrasi::Terverifikasi
                && $p->status_akademik === StatusAkademik::Disetujui => StatusPengajuan::Disetujui,
            default => StatusPengajuan::Diajukan,
        };
    }

    /** Jalankan perubahan pada baris yang dikunci agar tidak balapan dengan aksi lain. */
    private function dalamTransaksi(Pengajuan $pengajuan, callable $aksi): Pengajuan
    {
        return DB::transaction(function () use ($pengajuan, $aksi) {
            $segar = Pengajuan::query()->whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
            $aksi($segar);

            return $segar->fresh();
        });
    }

    private function pastikanDataLengkap(Pengajuan $p): void
    {
        if ($p->jenis === JenisPengajuan::Ta) {
            if (blank($p->judul)) {
                throw new WorkflowException('Judul Tugas Akhir wajib diisi sebelum pengajuan dikirim.');
            }
        } else {
            foreach (['instansi_nama' => 'Nama instansi', 'instansi_alamat' => 'Alamat instansi', 'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai'] as $kolom => $label) {
                if (blank($p->{$kolom})) {
                    throw new WorkflowException("{$label} KP wajib diisi sebelum pengajuan dikirim.");
                }
            }
        }

        $kurang = $p->persyaratanWajibKurang();
        if ($kurang->isNotEmpty()) {
            throw new WorkflowException('Dokumen wajib belum lengkap: '.$kurang->pluck('nama')->implode(', ').'.');
        }
    }

    private function pastikanPersyaratanBerlaku(Pengajuan $pengajuan, Persyaratan $persyaratan): void
    {
        if (! $persyaratan->aktif || ($persyaratan->prodi_id !== null && $persyaratan->prodi_id !== $pengajuan->prodi_id)) {
            throw new WorkflowException('Persyaratan tersebut tidak berlaku untuk pengajuan Anda.');
        }

        if ($persyaratan->jenis === JenisPersyaratan::KpLampiran) {
            if ($pengajuan->jenis !== JenisPengajuan::Kp) {
                throw new WorkflowException('Lampiran laporan hanya untuk pengajuan KP.');
            }
            if (! Pengaturan::lampiranKpAktif()) {
                throw new WorkflowException('Pengumpulan lampiran laporan KP belum dibuka.');
            }
            if (! in_array($pengajuan->status, [StatusPengajuan::Disetujui, StatusPengajuan::Selesai], true)) {
                throw new WorkflowException('Lampiran dapat diunggah setelah pengajuan KP disetujui.');
            }

            return;
        }

        if ($persyaratan->jenis !== $pengajuan->jenis->jenisPersyaratan()) {
            throw new WorkflowException('Persyaratan tersebut bukan untuk jenis pengajuan ini.');
        }
        if (! $pengajuan->bolehDiubahMahasiswa()) {
            throw new WorkflowException("Dokumen tidak dapat diubah karena pengajuan berstatus \"{$pengajuan->status->label()}\".");
        }
    }
}
