<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Menu sidebar per role. Satu-satunya tempat yang menentukan menu tiap aktor.
 * Menu hanya tampilan; pembatasan akses sebenarnya ada di middleware role dan policy.
 */
class Menu
{
    /**
     * @return list<array{label: string, url: string, icon: string, aktif: bool}>
     */
    public static function untuk(User $user): array
    {
        $butir = match ($user->role) {
            Role::Mahasiswa => [
                ['Dashboard', route('mahasiswa.dashboard'), 'home', request()->routeIs('mahasiswa.dashboard')],
                ['Pengajuan KP', route('mahasiswa.pengajuan.index', 'kp'), 'briefcase', self::halamanPengajuan('kp')],
                ['Pengajuan TA', route('mahasiswa.pengajuan.index', 'ta'), 'academic', self::halamanPengajuan('ta')],
                ['Status Pengajuan', route('mahasiswa.status.index'), 'list', request()->routeIs('mahasiswa.status.*')],
                ['Berkas Saya', route('mahasiswa.berkas.index'), 'folder', request()->routeIs('mahasiswa.berkas.*')],
            ],
            Role::Dosen => [
                ['Dashboard', route('dosen.dashboard'), 'home', request()->routeIs('dosen.dashboard')],
                ['Pemeriksaan Berkas', route('dosen.pemeriksaan.index'), 'clipboard', request()->routeIs('dosen.pemeriksaan.*')],
                ['Mahasiswa Bimbingan', route('dosen.bimbingan.index'), 'users', request()->routeIs('dosen.bimbingan.*')],
                ['Riwayat Pemeriksaan', route('dosen.riwayat.index'), 'clock', request()->routeIs('dosen.riwayat.*')],
            ],
            Role::Admin => [
                ['Dashboard', route('admin.dashboard'), 'home', request()->routeIs('admin.dashboard')],
                ['Verifikasi Administrasi', route('admin.verifikasi.index'), 'shield', request()->routeIs('admin.verifikasi.*')],
                ['Pengelolaan KP/TA', route('admin.pengajuan.index'), 'inbox', request()->routeIs('admin.pengajuan.*')],
                ['Dokumen Resmi', route('admin.dokumen-resmi.index'), 'document', request()->routeIs('admin.dokumen-resmi.*')],
                ['Arsip Berkas', route('admin.arsip.index'), 'archive', request()->routeIs('admin.arsip.*')],
                ['Manajemen Pengguna', route('admin.users.index'), 'users', request()->routeIs('admin.users.*')],
                ['Data Master', route('admin.program-studi.index'), 'database', request()->routeIs('admin.program-studi.*')],
                ['Pengaturan Persyaratan', route('admin.persyaratan.index'), 'sliders', request()->routeIs('admin.persyaratan.*')],
                ['Log Aktivitas', route('admin.log.index'), 'activity', request()->routeIs('admin.log.*')],
            ],
        };

        return array_map(
            fn (array $b) => ['label' => $b[0], 'url' => $b[1], 'icon' => $b[2], 'aktif' => (bool) $b[3]],
            $butir,
        );
    }

    /** Halaman pengajuan (daftar, buat, detail) yang jenisnya sesuai menu. */
    private static function halamanPengajuan(string $jenis): bool
    {
        $rute = request()->route();

        if ($rute === null || ! request()->routeIs('mahasiswa.pengajuan.*', 'mahasiswa.dokumen.*')) {
            return false;
        }

        $pengajuan = $rute->parameter('pengajuan');
        if ($pengajuan instanceof Pengajuan) {
            return $pengajuan->jenis->value === $jenis;
        }

        return $rute->parameter('jenis') === $jenis;
    }
}
