<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\TugasAdministrasi;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\HakAkses;

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

    /** Pembimbing 1 atau 2 yang ditetapkan pada pengajuan ini (persetujuan akademik, validasi). */
    public function periksa(User $user, Pengajuan $pengajuan): bool
    {
        return $user->hasRole(Peran::Dospem->value)
            && $pengajuan->posisiPembimbing($user->dosen) !== null;
    }

    /** Pemeriksa kelengkapan (bawaan Operator Akademik, bisa dialihkan lewat Pengaturan) pada prodi terkait. */
    public function periksaKelengkapan(User $user, Pengajuan $pengajuan): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::PeriksaKelengkapan, $pengajuan->prodi_id);
    }

    public function validasiSuratBalasan(User $user, Pengajuan $pengajuan): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::ValidasiSuratBalasan, $pengajuan->prodi_id);
    }

    /** Mencatat SK resmi (jalur offline). */
    public function catatSk(User $user, Pengajuan $pengajuan): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::CatatSk, $pengajuan->prodi_id);
    }

    /** Pembatalan pengajuan: pemilik (mahasiswa) atau pemeriksa kelengkapan. */
    public function batalkan(User $user, Pengajuan $pengajuan): bool
    {
        return $this->milik($user, $pengajuan) || $this->periksaKelengkapan($user, $pengajuan);
    }
}
