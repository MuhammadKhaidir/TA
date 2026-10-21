<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Enums\TugasAdministrasi;
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
use App\Support\HakAkses;
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
 *
 * Revisi PDF: pengajuan TA kini punya Pembimbing 1 dan 2 yang masing-masing harus
 * menyetujui [K-07], dikirim hanya setelah surat balasan valid, dan dapat dibatalkan
 * lalu diajukan ulang (status Dibatalkan / Digantikan).
 */
class PengajuanService
{
    public const DISK = 'local'; // disk privat: berkas hanya terbuka lewat route terotorisasi

    public function __construct(private readonly NotifikasiService $notifikasi) {}

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
            // Cegah duplikasi: SK TA yang sudah terbit tidak diganti dengan pengajuan TA baru.
            if ($jenis === JenisPengajuan::Ta
                && $mahasiswa->pengajuans()->where('jenis', JenisPengajuan::Ta->value)->whereHas('suratKeputusan')->exists()) {
                throw new WorkflowException('SK TA Anda sudah terbit, pengajuan TA baru tidak dapat dibuat.');
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
        $jenisDokumen = $dokumen->persyaratan?->jenis;

        if ($jenisDokumen === JenisPersyaratan::KpLampiran) {
            // Lampiran laporan KP boleh dihapus pemiliknya kapan pun (aturan lama KP, tidak diubah).
        } elseif ($jenisDokumen === JenisPersyaratan::TaSuratPengantar) {
            $surat = $pengajuan->suratPengantar;
            if (! $surat || ! $surat->status->bolehDiubahMahasiswa()) {
                throw new WorkflowException('Dokumen surat pengantar tidak dapat dihapus karena surat sedang diproses.');
            }
        } elseif (! $pengajuan->bolehDiubahMahasiswa()) {
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

            // Gerbang: pengajuan SK TA baru boleh dikirim setelah surat balasan dinyatakan valid.
            if ($p->jenis === JenisPengajuan::Ta && $p->gerbangSuratBalasan() && ! $p->suratBalasanValid()) {
                throw new WorkflowException('Pengajuan SK dapat dikirim setelah surat balasan Anda dinyatakan valid oleh Operator.');
            }

            $this->pastikanDataLengkap($p);

            $ulang = $p->diajukan_pada !== null;

            // Bagian yang diminta revisi kembali menunggu pemeriksaan.
            if ($p->status_administrasi === StatusAdministrasi::Revisi) {
                $p->status_administrasi = StatusAdministrasi::Menunggu;
            }
            if ($p->status_akademik === StatusAkademik::Revisi) {
                $p->status_akademik = StatusAkademik::Menunggu;
                // Pemeriksaan akademik diulang dari awal: persetujuan sebelumnya tidak berlaku lagi.
                $p->setuju_dospem1_pada = null;
                $p->setuju_dospem2_pada = null;
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

            $this->beri(
                HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $p->prodi_id),
                "Pengajuan {$p->jenis->singkat()} menunggu verifikasi",
                "{$p->mahasiswa->nama} ({$p->mahasiswa->nim}) mengirim pengajuan {$p->jenis->singkat()}.",
                $p,
                $actor,
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
    // Pembatalan dan pengajuan ulang (pengajuan yang sudah pernah dikirim)
    // ------------------------------------------------------------------

    /**
     * Membatalkan pengajuan yang sudah dikirim tetapi belum selesai (SK belum terbit).
     * Dilakukan mahasiswa pemilik atau Operator; alasan wajib dan tercatat di riwayat.
     */
    public function batalkanPengajuan(Pengajuan $pengajuan, User $actor, string $alasan): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor, $alasan) {
            if (! in_array($p->status, [StatusPengajuan::Diajukan, StatusPengajuan::PerluRevisi, StatusPengajuan::Disetujui], true)) {
                throw new WorkflowException("Pengajuan berstatus \"{$p->status->label()}\" tidak dapat dibatalkan.");
            }
            if (blank($alasan)) {
                throw new WorkflowException('Alasan pembatalan wajib diisi.');
            }

            $p->forceFill([
                'status' => StatusPengajuan::Dibatalkan,
                'dibatalkan_pada' => now(),
                'alasan_batal' => trim($alasan),
            ])->save();

            LogAktivitas::catat($actor, 'pengajuan_dibatalkan', trim($alasan), $p);

            $penerima = $actor->id === $p->mahasiswa->user_id
                ? HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $p->prodi_id)
                : [$p->mahasiswa->user];
            $this->beri($penerima, "Pengajuan {$p->jenis->singkat()} dibatalkan", trim($alasan), $p, $actor);
        });
    }

    /**
     * Mengajukan ulang pengajuan yang dibatalkan: dibuat draft baru (judul/data dibawa), pengajuan lama
     * menjadi Digantikan dan menunjuk yang baru. Tidak bisa dua kali dan tidak bisa bila masih ada
     * pengajuan berjalan, sehingga tidak ada pengajuan ganda.
     */
    public function ajukanUlang(Pengajuan $lama, User $actor): Pengajuan
    {
        return DB::transaction(function () use ($lama, $actor) {
            $mahasiswa = Mahasiswa::query()->whereKey($lama->mahasiswa_id)->lockForUpdate()->firstOrFail();
            $p = Pengajuan::query()->whereKey($lama->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== StatusPengajuan::Dibatalkan || $p->digantikan_oleh_id !== null) {
                throw new WorkflowException('Hanya pengajuan yang dibatalkan dan belum digantikan yang dapat diajukan ulang.');
            }

            $baru = $this->buat($mahasiswa, $p->jenis, $p->only($p->getFillable()), $actor);

            $p->forceFill(['status' => StatusPengajuan::Digantikan, 'digantikan_oleh_id' => $baru->id])->save();
            LogAktivitas::catat($actor, 'pengajuan_digantikan', 'Pengajuan diganti dengan pengajuan ulang yang baru.', $p);
            LogAktivitas::catat($actor, 'pengajuan_ulang_dibuat', "Draft ini adalah pengajuan ulang dari pengajuan #{$p->id}.", $baru);

            return $baru;
        });
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
            $this->beri([$p->mahasiswa->user], "Administrasi {$p->jenis->singkat()} diverifikasi", 'Kelengkapan administrasi pengajuan Anda sudah diverifikasi.', $p, $actor);
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
            $this->beri([$p->mahasiswa->user], "Pengajuan {$p->jenis->singkat()} perlu diperbaiki", $catatan, $p, $actor);
        });
    }

    /**
     * Menetapkan pembimbing. Untuk TA: Pembimbing 1 dan Pembimbing 2 dipilih dari master dosen dan harus
     * dosen yang berbeda; Pembimbing 2 wajib selama pengaturan `ta.pembimbing_kedua_wajib` menyatakannya.
     * KP tetap memakai satu pembimbing. Pergantian pembimbing mengulang persetujuan akademik dari awal.
     */
    public function tetapkanDospem(Pengajuan $pengajuan, Dosen $dosen, User $actor, ?Dosen $dosen2 = null): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($dosen, $dosen2, $actor) {
            $boleh = [StatusPengajuan::Diajukan, StatusPengajuan::PerluRevisi, StatusPengajuan::Disetujui];
            if (! in_array($p->status, $boleh, true)) {
                throw new WorkflowException("Dosen pembimbing tidak dapat ditetapkan pada status \"{$p->status->label()}\".");
            }
            if (! $dosen->layakMembimbing()) {
                throw new WorkflowException('Dosen tersebut tidak valid sebagai pembimbing (tidak aktif atau belum memiliki akun Dospem).');
            }

            if ($p->jenis === JenisPengajuan::Ta) {
                if ($dosen2 === null && Pengaturan::pembimbingKeduaWajib()) {
                    throw new WorkflowException('Pembimbing 2 wajib ditetapkan.');
                }
                if ($dosen2 !== null) {
                    if ($dosen2->id === $dosen->id) {
                        throw new WorkflowException('Pembimbing 1 dan Pembimbing 2 harus dosen yang berbeda.');
                    }
                    if (! $dosen2->layakMembimbing()) {
                        throw new WorkflowException('Pembimbing 2 tidak valid sebagai pembimbing (tidak aktif atau belum memiliki akun Dospem).');
                    }
                }
            } elseif ($dosen2 !== null) {
                throw new WorkflowException('Pembimbing 2 hanya berlaku untuk pengajuan Tugas Akhir.');
            }

            if ($p->dospem_id === $dosen->id && $p->dospem2_id === $dosen2?->id) {
                return;
            }

            $p->dospem_id = $dosen->id;
            $p->dospem2_id = $dosen2?->id;
            // Pembimbing baru memeriksa dari awal.
            $p->setuju_dospem1_pada = null;
            $p->setuju_dospem2_pada = null;
            if ($p->status_akademik !== StatusAkademik::Menunggu) {
                $p->status_akademik = StatusAkademik::Menunggu;
            }
            $this->sinkronkan($p);
            $p->save();

            $keterangan = "Pembimbing 1: {$dosen->nama} (NIP {$dosen->nip})".($dosen2 ? "; Pembimbing 2: {$dosen2->nama} (NIP {$dosen2->nip})" : '').'.';
            LogAktivitas::catat($actor, 'tetapkan_dospem', "Dosen pembimbing ditetapkan. {$keterangan}", $p);

            $this->beri(
                collect([$dosen->user, $dosen2?->user])->filter()->all(),
                "Anda ditetapkan sebagai pembimbing {$p->jenis->singkat()}",
                "{$p->mahasiswa->nama} ({$p->mahasiswa->nim}) menunggu pemeriksaan akademik Anda.",
                $p,
                $actor,
            );
            $this->beri([$p->mahasiswa->user], 'Dosen pembimbing ditetapkan', $keterangan, $p, $actor);
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

    /**
     * Persetujuan akademik dicatat per pembimbing. Status akademik baru Disetujui setelah SEMUA
     * pembimbing yang ditetapkan (1 dan 2) menyetujui [K-07].
     */
    public function setujuiAkademik(Pengajuan $pengajuan, User $actor): Pengajuan
    {
        return $this->dalamTransaksi($pengajuan, function (Pengajuan $p) use ($actor) {
            if (! $p->dospem_id) {
                throw new WorkflowException('Dosen pembimbing belum ditetapkan.');
            }
            if ($p->status !== StatusPengajuan::Diajukan || $p->status_akademik !== StatusAkademik::Menunggu) {
                throw new WorkflowException("Persetujuan akademik tidak dapat diberikan pada status \"{$p->status->label()}\".");
            }

            $posisi = $p->posisiPembimbing($actor->dosen?->id);
            if ($posisi === null) {
                throw new WorkflowException('Anda bukan pembimbing pada pengajuan ini.');
            }
            $kolom = $posisi === 1 ? 'setuju_dospem1_pada' : 'setuju_dospem2_pada';
            if ($p->{$kolom} !== null) {
                throw new WorkflowException('Anda sudah menyetujui pengajuan ini.');
            }

            $p->{$kolom} = now();
            $semua = $p->semuaPembimbingSetuju();
            if ($semua) {
                $p->status_akademik = StatusAkademik::Disetujui;
                $this->sinkronkan($p);
            }
            $p->save();

            LogAktivitas::catat($actor, 'setujui_akademik', $semua
                ? "Pembimbing {$posisi} menyetujui; seluruh pembimbing telah menyetujui."
                : "Pembimbing {$posisi} menyetujui; menunggu pembimbing lainnya.", $p);

            if ($semua) {
                $this->beri([$p->mahasiswa->user], "Pengajuan {$p->jenis->singkat()} disetujui secara akademik", 'Seluruh pembimbing telah menyetujui pengajuan Anda.', $p, $actor);
                $this->beri(HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $p->prodi_id), "Pengajuan {$p->jenis->singkat()} siap diproses", "{$p->mahasiswa->nama} ({$p->mahasiswa->nim}) telah disetujui seluruh pembimbing.", $p, $actor);
            }
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
            if ($p->posisiPembimbing($actor->dosen?->id) === null) {
                throw new WorkflowException('Anda bukan pembimbing pada pengajuan ini.');
            }

            $p->status_akademik = StatusAkademik::Revisi;
            $p->setuju_dospem1_pada = null;
            $p->setuju_dospem2_pada = null;
            $this->sinkronkan($p);
            $p->save();

            LogAktivitas::catat($actor, 'revisi_akademik', $catatan, $p);
            $this->beri([$p->mahasiswa->user], "Pengajuan {$p->jenis->singkat()} perlu direvisi", $catatan, $p, $actor);
        });
    }

    // ------------------------------------------------------------------

    /** Turunkan status gabungan dari dua dimensi persetujuan. SK/arsip/pembatalan menetapkan statusnya sendiri. */
    public function sinkronkan(Pengajuan $p): void
    {
        if ($p->status->berhenti()) {
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
            $segar = Pengajuan::query()->whereKey($pengajuan->id)->lockForUpdate()->with(['mahasiswa.user'])->firstOrFail();
            $aksi($segar);

            return $segar->fresh();
        });
    }

    /** @param  iterable<User>  $penerima */
    private function beri(iterable $penerima, string $judul, ?string $isi, Pengajuan $p, ?User $actor): void
    {
        $this->notifikasi->kirim($penerima, $judul, $isi, route('pengajuan.show', $p, false), $p, $actor);
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

        if ($persyaratan->jenis === JenisPersyaratan::TaSuratPengantar) {
            if ($pengajuan->jenis !== JenisPengajuan::Ta) {
                throw new WorkflowException('Dokumen surat pengantar hanya untuk pengajuan Tugas Akhir.');
            }
            if ($pengajuan->status->berhenti()) {
                throw new WorkflowException("Pengajuan berstatus \"{$pengajuan->status->label()}\", dokumen tidak dapat diubah.");
            }
            $surat = $pengajuan->suratPengantar;
            if (! $surat) {
                throw new WorkflowException('Simpan data surat pengantar terlebih dulu sebelum mengunggah dokumen.');
            }
            if (! $surat->status->bolehDiubahMahasiswa()) {
                throw new WorkflowException("Dokumen tidak dapat diubah karena surat pengantar berstatus \"{$surat->status->label()}\".");
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
