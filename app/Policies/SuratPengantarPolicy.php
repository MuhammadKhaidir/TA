<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\TugasAdministrasi;
use App\Models\SuratPengantar;
use App\Models\User;
use App\Support\HakAkses;

/**
 * Aturan akses surat pengantar TA. Pelaku tiap tahap dibaca dari HakAkses (Pengaturan),
 * bukan ditanam: Operator memeriksa kelengkapan, Admin memproses offline (bawaan PDF Bagian 3).
 */
class SuratPengantarPolicy
{
    /** Antrean surat pengantar di panel Admin: Admin, atau siapa pun yang memegang tugas memprosesnya. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Admin->value) || HakAkses::boleh($user, TugasAdministrasi::ProsesSuratPengantar);
    }

    public function view(User $user, SuratPengantar $surat): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SuratPengantar $surat): bool
    {
        return false;
    }

    public function delete(User $user, SuratPengantar $surat): bool
    {
        return false;
    }

    /** Mahasiswa pemilik: ubah isian, unggah dokumen, kirim. Hanya saat draft / perlu perbaikan. */
    public function ubah(User $user, SuratPengantar $surat): bool
    {
        return $this->milik($user, $surat) && $surat->status->bolehDiubahMahasiswa();
    }

    public function milik(User $user, SuratPengantar $surat): bool
    {
        return $user->hasRole(Peran::Mahasiswa->value)
            && $user->mahasiswa?->id === $surat->pengajuan->mahasiswa_id;
    }

    /** Memeriksa kelengkapan / mengembalikan: pemegang tugas PeriksaKelengkapan pada prodi terkait. */
    public function periksa(User $user, SuratPengantar $surat): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::PeriksaKelengkapan, $surat->pengajuan->prodi_id);
    }

    /** Memproses offline / menyelesaikan: pemegang tugas ProsesSuratPengantar. */
    public function proses(User $user, SuratPengantar $surat): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::ProsesSuratPengantar, $surat->pengajuan->prodi_id);
    }
}
