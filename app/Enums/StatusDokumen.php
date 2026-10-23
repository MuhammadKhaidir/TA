<?php

namespace App\Enums;

/**
 * Status satu berkas yang diunggah. Nilai yang sama dipakai sebagai keputusan pemeriksaan
 * (hanya Diterima atau Revisi yang valid sebagai keputusan).
 */
enum StatusDokumen: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Revisi = 'revisi';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Pemeriksaan',
            self::Diterima => 'Diterima',
            self::Revisi => 'Perlu Revisi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::Diterima => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Revisi => 'bg-amber-50 text-amber-800 ring-amber-200',
        };
    }
}
