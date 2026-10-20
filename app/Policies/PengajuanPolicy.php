<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Aturan akses pengajuan. Admin sengaja TIDAK punya akses ke isi pengajuan:
 * Admin mengelola sistem, bukan memproses atau menilai pengajuan.
 */
class PengajuanPolicy
{
    /** Daftar pemantauan read-only di panel Admin. Tidak membuka detail, dokumen, atau SK. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Admin->value);
    }

    /** Boleh melihat detail, dokumen, dan SK (aturan tunggal ada di scope model). */
    public function view(User $user, Pengajuan $pengajuan): bool
    {
        return Pengajuan::query()->dapatDilihatOleh($user)->whereKey($pengajuan->id)->exists();
    }

    /** Mahasiswa pemilik: ubah data, unggah dokumen, kirim — hanya saat draft/perlu revisi. */
    public function ubah(User $user, Pengajuan $pengajuan): bool
    {
        return $user->hasRole(Peran::Mahasiswa->value)
            && $user->mahasiswa?->id === $pengajuan->mahasiswa_id
            && $pengajuan->bolehDiubahMahasiswa();
    }

    /** Mahasiswa pemilik (apa pun statusnya): hapus draft, unggah lampiran KP, ajukan surat. */
    public function milik(User $user, Pengajuan $pengajuan): bool
    {
        return $user->hasRole(Peran::Mahasiswa->value) && $user->mahasiswa?->id === $pengajuan->mahasiswa_id;
    }

    /** Operator Akademik dalam cakupan prodi pengajuan. */
    public function kelola(User $user, Pengajuan $pengajuan): bool
    {
        return $user->bolehMengelolaProdi($pengajuan->prodi_id);
    }

    /** Hanya Dospem yang ditetapkan pada pengajuan ini. */
    public function periksa(User $user, Pengajuan $pengajuan): bool
    {
        return $user->hasRole(Peran::Dospem->value)
            && $pengajuan->dospem_id !== null
            && $user->dosen?->id === $pengajuan->dospem_id;
    }
}
