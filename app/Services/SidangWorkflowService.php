<?php

namespace App\Services;

use App\Enums\JenisDokumen;
use App\Enums\KonfirmasiKehadiran;
use App\Enums\PerananPenguji;
use App\Enums\SidangStatus;
use App\Exceptions\WorkflowException;
use App\Models\Dokumen;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Sidang;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mengimplementasikan seluruh 16 langkah pada Bagan Alir POS
 * "Pendaftaran Ujian Akhir Program" (No. POS 020/POS/FASILKOM/2026)
 * sebagai transisi status yang eksplisit dan tercatat (auditable).
 *
 * Setiap method di sini merepresentasikan satu (atau sekelompok kecil)
 * baris pada Bagan Alir, dan selalu:
 *  1. Memvalidasi bahwa sidang sedang berada pada status yang tepat.
 *  2. Melakukan perubahan data yang relevan.
 *  3. Mencatat jejak audit ke tabel sidang_riwayats.
 */
class SidangWorkflowService
{
    private function pastikanStatus(Sidang $sidang, SidangStatus ...$statusValid): void
    {
        if (! in_array($sidang->status, $statusValid, true)) {
            throw new WorkflowException(
                "Tindakan ini tidak dapat dilakukan karena sidang sedang berstatus \"{$sidang->status->label()}\"."
            );
        }
    }

    /**
     * Langkah 1 — Mahasiswa menyerahkan berkas persyaratan sidang.
     */
    public function ajukanSidang(Mahasiswa $mahasiswa, array $data, User $actor): Sidang
    {
        $sudahAda = Sidang::where('mahasiswa_id', $mahasiswa->id)
            ->where('status', '!=', SidangStatus::Selesai->value)
            ->exists();

        if ($sudahAda) {
            throw new WorkflowException('Anda masih memiliki proses pendaftaran sidang yang sedang berjalan.');
        }

        return DB::transaction(function () use ($mahasiswa, $data, $actor) {
            $memenuhiSyarat = $mahasiswa->memenuhiSyaratKonsultasi();

            $sidang = Sidang::create([
                'mahasiswa_id' => $mahasiswa->id,
                'jenis' => $data['jenis'],
                'status' => $memenuhiSyarat ? SidangStatus::Diajukan : SidangStatus::Ditunda,
                'tanggal_pengajuan' => now()->toDateString(),
                'jumlah_konsultasi_saat_ajukan' => $mahasiswa->jumlah_konsultasi,
                'judul_ta' => $data['judul_ta'] ?? null,
            ]);

            if (! empty($data['dkn_file'])) {
                $this->unggahDokumen($sidang, JenisDokumen::Dkn, $data['dkn_file'], $actor);
            }
            if (! empty($data['usep_file'])) {
                $this->unggahDokumen($sidang, JenisDokumen::BuktiKelulusanUsep, $data['usep_file'], $actor);
            }
            if (! empty($data['sk_pembimbing_file'])) {
                $this->unggahDokumen($sidang, JenisDokumen::SkPembimbingTa, $data['sk_pembimbing_file'], $actor);
            }

            if ($memenuhiSyarat) {
                $sidang->catatRiwayat(
                    1,
                    SidangStatus::judulLangkah()[1],
                    'Berkas DKN dan bukti kelulusan USEP diserahkan ke SekDep/Koor. Prodi.',
                    'mahasiswa',
                    $actor->id,
                );
            } else {
                $sidang->catatRiwayat(
                    1,
                    'Pengajuan ditunda — syarat konsultasi belum terpenuhi',
                    "Sesuai Peringatan POS: mahasiswa baru melakukan {$mahasiswa->jumlah_konsultasi} dari minimal ".Mahasiswa::MINIMAL_KONSULTASI.' kali konsultasi. Pengajuan sidang ditunda sampai syarat terpenuhi.',
                    'mahasiswa',
                    $actor->id,
                );
            }

            return $sidang->fresh();
        });
    }

    /**
     * Memperbarui jumlah konsultasi mahasiswa. Jika sidang berstatus
     * "ditunda" dan syarat kini terpenuhi, otomatis melanjutkan ke Langkah 1
     * selesai (siap diproses SekDep pada Langkah 2).
     */
    public function perbaruiJumlahKonsultasi(Sidang $sidang, int $jumlah, User $actor): Sidang
    {
        $mahasiswa = $sidang->mahasiswa;
        $mahasiswa->update(['jumlah_konsultasi' => $jumlah]);
        $sidang->update(['jumlah_konsultasi_saat_ajukan' => $jumlah]);

        if ($sidang->status === SidangStatus::Ditunda && $mahasiswa->memenuhiSyaratKonsultasi()) {
            $sidang->update(['status' => SidangStatus::Diajukan]);
            $sidang->catatRiwayat(
                1,
                'Syarat konsultasi terpenuhi — pengajuan dilanjutkan',
                "Jumlah konsultasi diperbarui menjadi {$jumlah} kali. Pengajuan sidang dapat diproses kembali.",
                'sekdep_koor_prodi',
                $actor->id,
            );
        } else {
            $sidang->catatRiwayat(
                0,
                'Jumlah konsultasi diperbarui',
                "Jumlah konsultasi mahasiswa diperbarui menjadi {$jumlah} kali.",
                'sekdep_koor_prodi',
                $actor->id,
            );
        }

        return $sidang->fresh();
    }

    /**
     * Langkah 2 — SekDep/Koor. Prodi mengusulkan jadwal ujian dan
     * penetapan tim penguji.
     */
    public function usulkanJadwal(Sidang $sidang, array $data, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::Diajukan);

        if (empty($data['pengujis']) || count($data['pengujis']) < 1) {
            throw new WorkflowException('Tim penguji wajib ditentukan sebelum jadwal diusulkan.');
        }

        return DB::transaction(function () use ($sidang, $data, $actor) {
            $sidang->update([
                'tanggal_sidang' => $data['tanggal_sidang'],
                'jam_sidang' => $data['jam_sidang'],
                'media' => $data['media'],
                'tempat' => $data['tempat'] ?? null,
                'status' => SidangStatus::MenungguVerifikasiPenata,
            ]);

            $sync = [];
            foreach ($data['pengujis'] as $item) {
                $sync[$item['dosen_id']] = [
                    'peran' => $item['peran'] ?? PerananPenguji::Anggota->value,
                    'konfirmasi' => KonfirmasiKehadiran::Menunggu->value,
                ];
            }
            $sidang->pengujis()->sync($sync);

            $daftarPenguji = Dosen::whereIn('id', array_keys($sync))->pluck('nama')->implode(', ');

            $sidang->catatRiwayat(
                2,
                SidangStatus::judulLangkah()[2],
                "Jadwal diusulkan: {$sidang->tanggal_sidang->format('d-m-Y')} pukul {$data['jam_sidang']}. Tim penguji: {$daftarPenguji}.",
                'sekdep_koor_prodi',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 3 & 4 — Penata memverifikasi kelulusan USEP/DKN serta jadwal
     * ujian, lalu meneruskan ke Pengelola Layanan untuk diproses SK Penguji.
     */
    public function verifikasiDanTeruskanPengelola(Sidang $sidang, array $data, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenungguVerifikasiPenata);

        if (empty($data['dkn_terverifikasi'])) {
            throw new WorkflowException('Kelulusan USEP dan kelengkapan DKN harus dinyatakan valid sebelum diteruskan.');
        }

        return DB::transaction(function () use ($sidang, $data, $actor) {
            $sidang->update([
                'nilai_usep' => $data['nilai_usep'] ?? $sidang->nilai_usep,
                'dkn_terverifikasi' => true,
                'status' => SidangStatus::MenungguProsesSk,
            ]);

            $sidang->catatRiwayat(
                3,
                SidangStatus::judulLangkah()[3],
                'Kelulusan USEP dan kelengkapan DKN dinyatakan valid.'.(! empty($data['catatan']) ? ' Catatan: '.$data['catatan'] : ''),
                'penata',
                $actor->id,
            );
            $sidang->catatRiwayat(
                4,
                SidangStatus::judulLangkah()[4],
                'Usulan jadwal sidang diteruskan ke Pengelola Layanan untuk penerbitan SK Penguji.',
                'penata',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 5 — Pengelola Layanan mengesahkan dan menerbitkan SK Penguji.
     */
    public function sahkanSkPenguji(Sidang $sidang, array $data, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenungguProsesSk);

        if (empty($data['nomor_sk'])) {
            throw new WorkflowException('Nomor SK Penguji wajib diisi.');
        }

        return DB::transaction(function () use ($sidang, $data, $actor) {
            $sidang->update([
                'nomor_sk_penguji' => $data['nomor_sk'],
                'status' => SidangStatus::SkTerbit,
            ]);

            if (! empty($data['sk_file'])) {
                $this->unggahDokumen($sidang, JenisDokumen::SkPenguji, $data['sk_file'], $actor, $data['nomor_sk']);
            }

            $sidang->catatRiwayat(
                5,
                SidangStatus::judulLangkah()[5],
                "SK Penguji Nomor {$data['nomor_sk']} telah disahkan dan disampaikan ke Penata serta Pengadministrasi Perkantoran.",
                'pengelola_layanan',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 6 (bagian pertama) — Penata meneruskan SK Penguji ke
     * SekDep/Koor. Prodi.
     */
    public function teruskanSkKeSekdep(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::SkTerbit);

        $sidang->update(['status' => SidangStatus::SkDiteruskanSekdep]);

        $sidang->catatRiwayat(
            6,
            'SK Penguji diteruskan Penata ke SekDep/Koor. Prodi',
            'Penata menyampaikan SK Penguji yang telah disahkan kepada SekDep/Koordinator Prodi.',
            'penata',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 6 (bagian kedua) — SekDep/Koor. Prodi menyampaikan SK Penguji
     * ke Dosen Penguji.
     */
    public function distribusikanSkKeDosen(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::SkDiteruskanSekdep);

        $sidang->update(['status' => SidangStatus::MenyiapkanAdministrasi]);

        $sidang->catatRiwayat(
            6,
            SidangStatus::judulLangkah()[6],
            'SK Penguji telah disampaikan SekDep/Koordinator Prodi kepada seluruh Dosen Penguji.',
            'sekdep_koor_prodi',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 7 — Pengadministrasi Perkantoran menyiapkan kelengkapan
     * administrasi ujian.
     */
    public function siapkanAdministrasi(Sidang $sidang, array $data, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenyiapkanAdministrasi);

        return DB::transaction(function () use ($sidang, $data, $actor) {
            if (! empty($data['dokumen_file'])) {
                $this->unggahDokumen($sidang, JenisDokumen::DokumenAdministrasi, $data['dokumen_file'], $actor);
            }

            $sidang->update(['status' => SidangStatus::MenungguInfoJadwal]);

            $sidang->catatRiwayat(
                7,
                SidangStatus::judulLangkah()[7],
                'Kelengkapan dokumen administrasi sidang (form nilai, berita acara, form revisi, surat pernyataan yudisium) telah disiapkan.',
                'pengadministrasi_perkantoran',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 8 — Informasi jadwal ujian disampaikan ke mahasiswa.
     */
    public function informasikanJadwal(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenungguInfoJadwal);

        $sidang->update(['status' => SidangStatus::MenungguPengecekanBerkas]);

        $sidang->catatRiwayat(
            8,
            SidangStatus::judulLangkah()[8],
            'Informasi jadwal dan SK Penguji telah disampaikan ke mahasiswa.',
            'pengadministrasi_perkantoran',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 9 — Pengecekan kelengkapan berkas sebelum pelaksanaan ujian.
     */
    public function cekKelengkapanBerkas(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenungguPengecekanBerkas);

        $sidang->update(['status' => SidangStatus::SiapUjian]);

        $sidang->catatRiwayat(
            9,
            SidangStatus::judulLangkah()[9],
            'Seluruh berkas ujian (form nilai, revisi, berita acara, surat pernyataan yudisium) dinyatakan lengkap. Sidang siap dilaksanakan.',
            'pengadministrasi_perkantoran',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 10 (bagian pertama) — Dosen Penguji mengonfirmasi
     * berhalangan hadir.
     */
    public function konfirmasiBerhalangan(Sidang $sidang, Dosen $dosen, string $alasan, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::SiapUjian);

        $pivot = $sidang->sidangPengujis()->where('dosen_id', $dosen->id)->first();

        if (! $pivot) {
            throw new WorkflowException('Anda bukan bagian dari dewan penguji sidang ini.');
        }

        return DB::transaction(function () use ($sidang, $dosen, $alasan, $pivot, $actor) {
            $pivot->update([
                'konfirmasi' => KonfirmasiKehadiran::Berhalangan,
                'alasan_berhalangan' => $alasan,
            ]);

            Dokumen::create([
                'sidang_id' => $sidang->id,
                'jenis_dokumen' => JenisDokumen::FormPergantian->value,
                'file_path' => '',
                'keterangan' => "Dosen {$dosen->nama} berhalangan hadir. Alasan: {$alasan}",
                'uploaded_by' => $actor->id,
            ]);

            $sidang->update(['status' => SidangStatus::MenungguPenjadwalanUlang]);

            $sidang->catatRiwayat(
                10,
                'Dosen penguji konfirmasi berhalangan hadir',
                "{$dosen->nama} berhalangan hadir pada jadwal sidang yang telah ditetapkan. Alasan: {$alasan}. Menunggu SekDep/Koor. Prodi melakukan penjadwalan ulang.",
                'dosen_penguji',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 10 (bagian kedua) — SekDep/Koor. Prodi melakukan penjadwalan
     * ulang.
     */
    public function jadwalkanUlang(Sidang $sidang, array $data, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::MenungguPenjadwalanUlang);

        return DB::transaction(function () use ($sidang, $data, $actor) {
            $sidang->update([
                'tanggal_sidang' => $data['tanggal_sidang'],
                'jam_sidang' => $data['jam_sidang'],
                'jumlah_penjadwalan_ulang' => $sidang->jumlah_penjadwalan_ulang + 1,
                'alasan_penjadwalan_ulang' => $data['alasan'] ?? null,
                'status' => SidangStatus::SiapUjian,
            ]);

            if (! empty($data['dosen_pengganti_id']) && ! empty($data['dosen_diganti_id'])) {
                $pivotLama = $sidang->sidangPengujis()->where('dosen_id', $data['dosen_diganti_id'])->first();
                $peran = $pivotLama?->peran?->value ?? PerananPenguji::Anggota->value;
                $sidang->pengujis()->detach($data['dosen_diganti_id']);
                $sidang->pengujis()->attach($data['dosen_pengganti_id'], [
                    'peran' => $peran,
                    'konfirmasi' => KonfirmasiKehadiran::Menunggu->value,
                ]);
            }

            // Jadwal baru: reset konfirmasi kehadiran seluruh dewan penguji.
            $sidang->sidangPengujis()->update(['konfirmasi' => KonfirmasiKehadiran::Menunggu->value]);

            $sidang->catatRiwayat(
                10,
                'Sidang dijadwalkan ulang',
                "SekDep/Koor. Prodi menjadwalkan ulang sidang ke {$sidang->tanggal_sidang->format('d-m-Y')} pukul {$data['jam_sidang']}.".(! empty($data['alasan']) ? ' Alasan: '.$data['alasan'] : ''),
                'sekdep_koor_prodi',
                $actor->id,
            );

            return $sidang->fresh();
        });
    }

    /**
     * Langkah 11 — Berkas ujian diserahkan ke Dosen Penguji pada hari
     * pelaksanaan.
     */
    public function serahkanBerkasUjian(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::SiapUjian);

        $sidang->update(['status' => SidangStatus::PelaksanaanUjian]);

        $sidang->catatRiwayat(
            11,
            SidangStatus::judulLangkah()[11],
            'Berkas ujian (form nilai, revisi, berita acara, surat pernyataan yudisium) diserahkan kepada dosen penguji pada hari pelaksanaan.',
            'pengadministrasi_perkantoran',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 12 — Dosen menginput nilai ujian TA di SIMAK.
     */
    public function inputNilai(Sidang $sidang, Dosen $dosen, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::PelaksanaanUjian);

        $pivot = $sidang->sidangPengujis()->where('dosen_id', $dosen->id)->first();

        if (! $pivot) {
            throw new WorkflowException('Anda bukan bagian dari dewan penguji sidang ini.');
        }

        if ($pivot->nilai_diinput) {
            throw new WorkflowException('Nilai untuk sidang ini sudah pernah Anda input.');
        }

        $pivot->update([
            'nilai_diinput' => true,
            'waktu_input_nilai' => now(),
        ]);

        $sidang->catatRiwayat(
            12,
            SidangStatus::judulLangkah()[12],
            "{$dosen->nama} telah menginput nilai ujian TA di SIMAK dan menyerahkan berkas ujian ke Pengadministrasi Perkantoran.",
            'dosen_penguji',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 13 — Pengelola Layanan memeriksa kelengkapan nilai di SIMAK.
     * Jika seluruh dosen sudah menginput nilai, sidang dinyatakan selesai.
     */
    public function cekNilaiSimak(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::PelaksanaanUjian);

        $sidang->load('pengujis');
        $lengkap = $sidang->semuaNilaiSudahDiinput();
        $belum = $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ');

        $sidang->catatRiwayat(
            13,
            SidangStatus::judulLangkah()[13],
            $lengkap
                ? 'Seluruh nilai ujian TA telah lengkap tercatat di SIMAK V3.'
                : "Masih terdapat dosen yang belum menginput nilai: {$belum}.",
            'pengelola_layanan',
            $actor->id,
        );

        if ($lengkap) {
            $sidang->update(['status' => SidangStatus::Selesai]);
            $sidang->catatRiwayat(
                16,
                'Sidang Tugas Akhir dinyatakan selesai',
                'Seluruh rangkaian proses pendaftaran hingga pelaksanaan Ujian Akhir Program telah tuntas.',
                'pengelola_layanan',
                $actor->id,
            );
        }

        return $sidang->fresh();
    }

    /**
     * Langkah 14 — Pengelola Layanan melapor ke Penata bila ada dosen yang
     * belum menginput nilai.
     */
    public function laporKePenata(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::PelaksanaanUjian);

        $sidang->load('pengujis');
        $belum = $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ');

        if ($belum === '') {
            throw new WorkflowException('Seluruh dosen sudah menginput nilai, tidak perlu eskalasi.');
        }

        $sidang->catatRiwayat(
            14,
            SidangStatus::judulLangkah()[14],
            "Pengelola Layanan melapor ke Penata: dosen berikut belum menginput nilai — {$belum}.",
            'pengelola_layanan',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 15 — Penata melapor ke SekDep terkait dosen yang belum
     * mengisi nilai.
     */
    public function laporKeSekdep(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::PelaksanaanUjian);

        $sidang->load('pengujis');
        $belum = $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ');

        if ($belum === '') {
            throw new WorkflowException('Seluruh dosen sudah menginput nilai, tidak perlu eskalasi.');
        }

        $sidang->catatRiwayat(
            15,
            SidangStatus::judulLangkah()[15],
            "Penata melapor ke SekDep/Koor. Prodi: dosen berikut masih belum mengisi nilai — {$belum}.",
            'penata',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Langkah 16 — SekDep menghubungi dosen agar segera menginput nilai.
     */
    public function hubungiDosen(Sidang $sidang, User $actor): Sidang
    {
        $this->pastikanStatus($sidang, SidangStatus::PelaksanaanUjian);

        $sidang->load('pengujis');
        $belum = $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ');

        if ($belum === '') {
            throw new WorkflowException('Seluruh dosen sudah menginput nilai, tidak perlu eskalasi.');
        }

        $sidang->catatRiwayat(
            16,
            SidangStatus::judulLangkah()[16],
            "SekDep/Koor. Prodi telah menghubungi dosen berikut agar segera menginput nilai — {$belum}.",
            'sekdep_koor_prodi',
            $actor->id,
        );

        return $sidang->fresh();
    }

    /**
     * Unggah dokumen umum yang dilampirkan pada sidang (dipakai oleh
     * berbagai langkah: DKN, bukti USEP, dokumen TA, dsb).
     */
    public function unggahDokumen(
        Sidang $sidang,
        JenisDokumen $jenis,
        UploadedFile $file,
        User $actor,
        ?string $nomorSk = null,
        ?string $keterangan = null,
    ): Dokumen {
        $path = $file->store('sidang/'.$sidang->id, 'public');

        return Dokumen::create([
            'sidang_id' => $sidang->id,
            'jenis_dokumen' => $jenis->value,
            'nomor_sk' => $nomorSk,
            'file_path' => $path,
            'nama_file_asli' => $file->getClientOriginalName(),
            'keterangan' => $keterangan,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function hapusDokumen(Dokumen $dokumen): void
    {
        if ($dokumen->file_path && Storage::disk('public')->exists($dokumen->file_path)) {
            Storage::disk('public')->delete($dokumen->file_path);
        }

        $dokumen->delete();
    }
}
