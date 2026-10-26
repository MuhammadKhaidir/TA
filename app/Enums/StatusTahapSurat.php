<?php

namespace App\Enums;

/**
 * Keadaan satu tahap pada alur surat Tugas Akhir (surat pengantar, surat balasan, SK pembimbing).
 * Hanya dipakai untuk tampilan; aturan sebenarnya ada di PengajuanPolicy dan Pengajuan::hambatanSk().
 */
enum StatusTahapSurat: string
{
    /** Belum bisa dikerjakan karena tahap sebelumnya belum selesai. */
    case Terkunci = 'terkunci';
    /** Menunggu tindakan mahasiswa. */
    case Giliran = 'giliran';
    /** Menunggu tindakan operator. */
    case Menunggu = 'menunggu';
    case Revisi = 'revisi';
    case Selesai = 'selesai';

    /** Warna titik penanda pada daftar ringkas (dashboard). */
    public function titik(): string
    {
        return match ($this) {
            self::Terkunci => 'bg-slate-300',
            self::Giliran => 'bg-sky-500',
            self::Menunggu => 'bg-amber-400',
            self::Revisi => 'bg-rose-500',
            self::Selesai => 'bg-emerald-500',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Terkunci => 'bg-slate-100 text-slate-600 ring-slate-300',
            self::Giliran => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::Menunggu => 'bg-amber-50 text-amber-800 ring-amber-200',
            self::Revisi => 'bg-rose-50 text-rose-700 ring-rose-200',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        };
    }
}
