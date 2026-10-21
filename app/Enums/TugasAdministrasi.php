<?php

namespace App\Enums;

/**
 * Tugas administrasi yang pelakunya dapat diatur lewat Pengaturan (kunci `akses.<nilai>`).
 *
 * Nilai bawaan mengikuti pembagian tanggung jawab pada Bagian 3 PDF revisi. Pembagian itu
 * belum dikonfirmasi fakultas [K-01], jadi TIDAK ditanam di kode: pelaku sebenarnya dibaca
 * dari Pengaturan oleh App\Support\HakAkses, dan Admin dapat mengubahnya tanpa menyentuh kode.
 *
 * Persetujuan akademik tetap milik Dosen Pembimbing dan tidak termasuk di sini.
 */
enum TugasAdministrasi: string
{
    case PeriksaKelengkapan = 'periksa_kelengkapan';
    case ValidasiSuratBalasan = 'validasi_surat_balasan';
    case ProsesSuratPengantar = 'proses_surat_pengantar';
    case CatatSk = 'catat_sk';
    case JadwalkanSidang = 'jadwalkan_sidang';
    case KonfirmasiSidangSelesai = 'konfirmasi_sidang_selesai';

    public function label(): string
    {
        return match ($this) {
            self::PeriksaKelengkapan => 'Memeriksa kelengkapan pengajuan (surat pengantar, SK, sidang)',
            self::ValidasiSuratBalasan => 'Memvalidasi surat balasan',
            self::ProsesSuratPengantar => 'Memproses surat pengantar TA secara offline',
            self::CatatSk => 'Mencatat nomor dan tanggal SK resmi (jalur offline)',
            self::JadwalkanSidang => 'Menjadwalkan sidang dan menetapkan penguji',
            self::KonfirmasiSidangSelesai => 'Mengonfirmasi sidang selesai',
        };
    }

    /** Pelaku bawaan menurut Bagian 3 PDF revisi. */
    public function bawaan(): Peran
    {
        return match ($this) {
            self::PeriksaKelengkapan, self::ValidasiSuratBalasan => Peran::OperatorAkademik,
            self::ProsesSuratPengantar, self::CatatSk, self::JadwalkanSidang, self::KonfirmasiSidangSelesai => Peran::Admin,
        };
    }

    public function kunciPengaturan(): string
    {
        return 'akses.'.$this->value;
    }

    /** Peran yang boleh dipilih sebagai pelaku tugas. */
    public static function peranDiizinkan(): array
    {
        return [Peran::OperatorAkademik->value => Peran::OperatorAkademik->label(), Peran::Admin->value => Peran::Admin->label()];
    }
}
