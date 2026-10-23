<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Pusat aturan kewenangan atas pengajuan dan seluruh berkas di dalamnya.
 * Admin sengaja tidak diberi akses ke isi pengajuan maupun berkas mahasiswa.
 */
class PengajuanPolicy
{
    public function view(User $user, Pengajuan $pengajuan): bool
    {
        return match ($user->role) {
            Role::Mahasiswa => (int) $pengajuan->mahasiswa_id === (int) $user->id,
            Role::Dosen => $pengajuan->pembimbing_id !== null && (int) $pengajuan->pembimbing_id === (int) $user->id,
            Role::Operator => true,
            default => false,
        };
    }

    /** Mahasiswa pemilik mengubah data dan mengunggah dokumen. */
    public function update(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Mahasiswa
            && (int) $pengajuan->mahasiswa_id === (int) $user->id
            && $pengajuan->status->bisaDiubahMahasiswa();
    }

    public function delete(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Mahasiswa
            && (int) $pengajuan->mahasiswa_id === (int) $user->id
            && $pengajuan->status === StatusPengajuan::Draft;
    }

    /** Operator memeriksa kelengkapan administrasi. */
    public function verifikasi(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator && $pengajuan->status === StatusPengajuan::Diajukan;
    }

    /** Dosen pembimbing memeriksa berkas akademik. */
    public function periksa(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Dosen
            && $pengajuan->pembimbing_id !== null
            && (int) $pengajuan->pembimbing_id === (int) $user->id
            && $pengajuan->status === StatusPengajuan::MenungguDosen;
    }

    /** Operator mengganti dosen pembimbing selama belum diarsipkan. */
    public function kelola(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator && $pengajuan->status !== StatusPengajuan::Diarsipkan;
    }

    /** Operator mengunggah dokumen resmi dan mengarsipkan setelah pengajuan disetujui. */
    public function prosesDokumenResmi(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator && $pengajuan->status === StatusPengajuan::Disetujui;
    }
}
