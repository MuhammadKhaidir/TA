<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\TugasAdministrasi;
use App\Models\SidangTa;
use App\Models\User;
use App\Support\HakAkses;

/**
 * Aturan akses pengajuan sidang TA. Pelaku tiap tahap dibaca dari HakAkses (Pengaturan) [K-01]:
 * bawaannya Operator memeriksa kelengkapan, Pembimbing 1/2 menyetujui, Admin menjadwalkan dan
 * mengonfirmasi selesai.
 */
class SidangTaPolicy
{
    /** Antrean sidang di panel Admin: Admin, atau siapa pun yang memegang tugas penjadwalan/konfirmasi. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Admin->value)
            || HakAkses::boleh($user, TugasAdministrasi::JadwalkanSidang)
            || HakAkses::boleh($user, TugasAdministrasi::KonfirmasiSidangSelesai);
    }

    /** Melihat detail & berkas: aturan tunggal ada di scope SidangTa. */
    public function view(User $user, SidangTa $sidang): bool
    {
        return SidangTa::query()->dapatDilihatOleh($user)->whereKey($sidang->id)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SidangTa $sidang): bool
    {
        return false;
    }

    public function delete(User $user, SidangTa $sidang): bool
    {
        return false;
    }

    public function milik(User $user, SidangTa $sidang): bool
    {
        return $user->hasRole(Peran::Mahasiswa->value) && $user->mahasiswa?->id === $sidang->mahasiswa_id;
    }

    /** Mahasiswa pemilik: unggah/hapus dokumen dan kirim, hanya saat draft / perlu perbaikan. */
    public function ubah(User $user, SidangTa $sidang): bool
    {
        return $this->milik($user, $sidang) && $sidang->bolehDiubahMahasiswa();
    }

    public function periksa(User $user, SidangTa $sidang): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::PeriksaKelengkapan, $sidang->pengajuan->prodi_id);
    }

    /** Pembimbing 1 atau 2 pada TA terkait. */
    public function setujui(User $user, SidangTa $sidang): bool
    {
        return $user->hasRole(Peran::Dospem->value) && $sidang->pengajuan->posisiPembimbing($user->dosen) !== null;
    }

    public function jadwalkan(User $user, SidangTa $sidang): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::JadwalkanSidang, $sidang->pengajuan->prodi_id);
    }

    public function selesai(User $user, SidangTa $sidang): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::KonfirmasiSidangSelesai, $sidang->pengajuan->prodi_id);
    }

    /** Pembatalan: mahasiswa pemilik atau pemeriksa kelengkapan. */
    public function batalkan(User $user, SidangTa $sidang): bool
    {
        return $this->milik($user, $sidang) || $this->periksa($user, $sidang);
    }
}
