<?php

namespace App\Policies;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
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

    /**
     * Dosen yang dipilih mahasiswa menjawab permintaan pembimbing TA.
     * Baru berlaku setelah pengajuan dikirim, dan hanya selama permintaan masih menunggu.
     */
    public function konfirmasiPembimbing(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Dosen
            && $pengajuan->jenis === JenisPengajuan::Ta
            && $pengajuan->status_pembimbing === StatusPembimbing::Menunggu
            && $pengajuan->pembimbing_pilihan_id !== null
            && (int) $pengajuan->pembimbing_pilihan_id === (int) $user->id
            && $pengajuan->status !== StatusPengajuan::Draft;
    }

    /** Operator menetapkan atau mengganti dosen pembimbing selama belum diarsipkan. */
    public function kelola(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator && $pengajuan->status !== StatusPengajuan::Diarsipkan;
    }

    /** Operator mengunggah dokumen resmi dan mengarsipkan setelah pengajuan disetujui. */
    public function prosesDokumenResmi(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator && $pengajuan->status === StatusPengajuan::Disetujui;
    }

    /*
    | Alur surat Tugas Akhir: surat pengantar (operator) -> surat balasan (mahasiswa, diperiksa operator)
    | -> SK pembimbing (operator, hanya bila balasan diterima; lihat Pengajuan::hambatanSk()).
    */

    /** Operator menerbitkan surat pengantar TA, satu kali, setelah pengajuan lolos verifikasi. */
    public function terbitkanSuratPengantar(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator
            && $pengajuan->tahapSuratAktif()
            && $pengajuan->suratPengantarTa() === null;
    }

    /** Surat pengantar boleh ditarik selama mahasiswa belum mengunggah surat balasan. */
    public function hapusSuratPengantar(User $user, Pengajuan $pengajuan): bool
    {
        return $user->role === Role::Operator
            && $pengajuan->tahapSuratAktif()
            && $pengajuan->suratPengantarTa() !== null
            && $pengajuan->suratBalasan->isEmpty();
    }

    /**
     * Mahasiswa pemilik mengunggah surat balasan setelah surat pengantar terbit. Boleh mengunggah ulang
     * selama belum diterima (menggantikan yang menunggu, atau memperbaiki yang diminta revisi).
     */
    public function unggahSuratBalasan(User $user, Pengajuan $pengajuan): bool
    {
        if ($user->role !== Role::Mahasiswa || (int) $pengajuan->mahasiswa_id !== (int) $user->id) {
            return false;
        }

        if (! $pengajuan->tahapSuratAktif() || $pengajuan->suratPengantarTa() === null || $pengajuan->skPembimbing() !== null) {
            return false;
        }

        return $pengajuan->balasanTerbaru()?->status !== StatusDokumen::Diterima;
    }

    /**
     * Operator memeriksa surat balasan. Keputusan masih bisa diubah (mis. salah menerima)
     * selama SK pembimbing belum terbit, sebab SK bergantung pada surat balasan yang diterima.
     */
    public function periksaSuratBalasan(User $user, Pengajuan $pengajuan): bool
    {
        if ($user->role !== Role::Operator || ! $pengajuan->tahapSuratAktif() || $pengajuan->skPembimbing() !== null) {
            return false;
        }

        $balasan = $pengajuan->balasanTerbaru();

        return $balasan !== null && $balasan->status !== StatusDokumen::Revisi;
    }
}
