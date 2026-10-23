<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatusDokumen;
use App\Enums\StatusPengajuan;
use App\Models\Arsip;
use App\Models\Pemeriksaan;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\RiwayatStatus;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh perpindahan status pengajuan lewat kelas ini, sehingga aturan alur
 * dan pencatatan riwayat status hanya ada di satu tempat.
 *
 * Alur:
 *   draft -> diajukan -> (revisi_administrasi -> diajukan)* -> menunggu_dosen
 *         -> (revisi_akademik -> menunggu_dosen)* -> disetujui -> diarsipkan
 */
class PengajuanWorkflow
{
    /** @return Collection<int, Persyaratan> */
    public function persyaratanAktif(Pengajuan $pengajuan): Collection
    {
        return Persyaratan::aktif()
            ->untuk($pengajuan->jenis)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }

    /**
     * Data dokumen untuk ditampilkan: persyaratan yang aktif ditambah persyaratan nonaktif
     * yang sudah pernah diunggah dokumennya, beserta dokumen versi terbaru per persyaratan.
     *
     * @return array{persyaratan: Collection<int, Persyaratan>, dokumenTerbaru: Collection<int, \App\Models\DokumenPengajuan>}
     */
    public function dataDokumen(Pengajuan $pengajuan): array
    {
        $sudahAda = $pengajuan->dokumen()->pluck('persyaratan_id');

        $persyaratan = Persyaratan::untuk($pengajuan->jenis)
            ->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', $sudahAda))
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $dokumenTerbaru = $pengajuan->dokumenTerbaru()
            ->with('pemeriksaan.pemeriksa')
            ->get()
            ->keyBy('persyaratan_id');

        return ['persyaratan' => $persyaratan, 'dokumenTerbaru' => $dokumenTerbaru];
    }

    /** Mencatat status awal (draft) ketika pengajuan baru dibuat. */
    public function catatAwal(Pengajuan $pengajuan, User $oleh): void
    {
        RiwayatStatus::create([
            'pengajuan_id' => $pengajuan->id,
            'user_id' => $oleh->id,
            'status_lama' => null,
            'status_baru' => $pengajuan->status,
            'catatan' => 'Pengajuan dibuat sebagai draft.',
        ]);

        Aktivitas::catat(
            'pengajuan.buat',
            "Membuat pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}",
            $pengajuan,
            $oleh,
        );
    }

    public function pindahStatus(Pengajuan $pengajuan, StatusPengajuan $baru, ?User $oleh, ?string $catatan = null): void
    {
        $lama = $pengajuan->status;

        $pengajuan->status = $baru;
        if ($baru === StatusPengajuan::Diajukan && $pengajuan->diajukan_at === null) {
            $pengajuan->diajukan_at = now();
        }
        $pengajuan->save();

        RiwayatStatus::create([
            'pengajuan_id' => $pengajuan->id,
            'user_id' => $oleh?->id,
            'status_lama' => $lama,
            'status_baru' => $baru,
            'catatan' => $catatan,
        ]);

        Aktivitas::catat(
            'pengajuan.status',
            "Status pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}: {$lama->label()} menjadi {$baru->label()}",
            $pengajuan,
            $oleh,
        );
    }

    /** Mahasiswa mengirim pengajuan (pertama kali) atau mengirim ulang setelah revisi. */
    public function ajukan(Pengajuan $pengajuan, User $oleh): void
    {
        $status = $pengajuan->status;

        if (! $status->bisaDiubahMahasiswa()) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tidak dapat dikirim pada status saat ini.',
            ]);
        }

        $terbaru = $pengajuan->dokumenTerbaru()->get()->keyBy('persyaratan_id');

        if ($status === StatusPengajuan::Draft) {
            $kurang = $this->persyaratanAktif($pengajuan)
                ->where('wajib', true)
                ->filter(fn (Persyaratan $syarat) => ! $terbaru->has($syarat->id));

            if ($kurang->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'dokumen' => 'Dokumen wajib belum lengkap: '.$kurang->pluck('nama')->implode(', ').'.',
                ]);
            }

            $tujuan = StatusPengajuan::Diajukan;
            $catatan = 'Pengajuan dikirim oleh mahasiswa.';
        } else {
            if ($terbaru->contains(fn ($dokumen) => $dokumen->status === StatusDokumen::Revisi)) {
                throw ValidationException::withMessages([
                    'dokumen' => 'Masih ada dokumen berstatus revisi yang belum diunggah ulang.',
                ]);
            }

            $tujuan = $status === StatusPengajuan::RevisiAdministrasi
                ? StatusPengajuan::Diajukan
                : StatusPengajuan::MenungguDosen;
            $catatan = 'Perbaikan dokumen dikirim ulang oleh mahasiswa.';
        }

        DB::transaction(fn () => $this->pindahStatus($pengajuan, $tujuan, $oleh, $catatan));
    }

    /**
     * Pemeriksaan dokumen oleh operator (administrasi) atau dosen (akademik).
     * Yang diperiksa hanya dokumen versi terbaru berstatus menunggu milik peran tersebut.
     *
     * @param  array<int|string, string|null>  $keputusan  id dokumen => diterima|revisi
     * @param  array<int|string, string|null>  $catatan  id dokumen => catatan
     */
    public function periksa(
        Pengajuan $pengajuan,
        User $pemeriksa,
        Role $peran,
        array $keputusan,
        array $catatan,
        ?string $catatanUmum = null,
    ): void {
        $menunggu = $pengajuan->dokumenTerbaru()
            ->with('persyaratan')
            ->where('status', StatusDokumen::Menunggu->value)
            ->get()
            ->filter(fn ($dokumen) => $dokumen->persyaratan->pemeriksa === $peran);

        $galat = [];
        foreach ($menunggu as $dokumen) {
            $nama = $dokumen->persyaratan->nama;
            $pilihan = $keputusan[$dokumen->id] ?? null;

            if (! in_array($pilihan, [StatusDokumen::Diterima->value, StatusDokumen::Revisi->value], true)) {
                $galat["keputusan.{$dokumen->id}"] = "Pilih keputusan untuk dokumen {$nama}.";

                continue;
            }

            if ($pilihan === StatusDokumen::Revisi->value && blank($catatan[$dokumen->id] ?? null)) {
                $galat["catatan.{$dokumen->id}"] = "Catatan revisi wajib diisi untuk dokumen {$nama}.";
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        DB::transaction(function () use ($pengajuan, $pemeriksa, $peran, $menunggu, $keputusan, $catatan, $catatanUmum) {
            foreach ($menunggu as $dokumen) {
                $hasil = StatusDokumen::from($keputusan[$dokumen->id]);

                $dokumen->update(['status' => $hasil]);

                Pemeriksaan::create([
                    'pengajuan_id' => $pengajuan->id,
                    'dokumen_id' => $dokumen->id,
                    'pemeriksa_id' => $pemeriksa->id,
                    'peran' => $peran,
                    'keputusan' => $hasil,
                    'catatan' => $catatan[$dokumen->id] ?? null,
                ]);
            }

            $adaRevisi = $pengajuan->dokumenTerbaru()
                ->whereHas('persyaratan', fn ($q) => $q->where('pemeriksa', $peran->value))
                ->where('status', StatusDokumen::Revisi->value)
                ->exists();

            $tujuan = match ($peran) {
                Role::Operator => $adaRevisi ? StatusPengajuan::RevisiAdministrasi : StatusPengajuan::MenungguDosen,
                default => $adaRevisi ? StatusPengajuan::RevisiAkademik : StatusPengajuan::Disetujui,
            };

            $this->pindahStatus($pengajuan, $tujuan, $pemeriksa, $catatanUmum);
        });
    }

    /** Operator memindahkan pengajuan yang sudah memiliki dokumen resmi ke arsip digital. */
    public function arsipkan(Pengajuan $pengajuan, User $oleh, ?string $catatan = null): void
    {
        if (! $pengajuan->dokumenResmi()->exists()) {
            throw ValidationException::withMessages([
                'dokumen_resmi' => 'Unggah minimal satu dokumen resmi sebelum mengarsipkan.',
            ]);
        }

        DB::transaction(function () use ($pengajuan, $oleh, $catatan) {
            Arsip::create([
                'pengajuan_id' => $pengajuan->id,
                'diarsipkan_oleh' => $oleh->id,
                'diarsipkan_at' => now(),
                'catatan' => $catatan,
            ]);

            $this->pindahStatus($pengajuan, StatusPengajuan::Diarsipkan, $oleh, $catatan ?: 'Berkas final diarsipkan.');
        });
    }
}
