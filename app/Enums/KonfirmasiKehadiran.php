<?php

namespace App\Enums;

enum KonfirmasiKehadiran: string
{
    case Menunggu = 'menunggu';
    case Hadir = 'hadir';
    case Berhalangan = 'berhalangan';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Konfirmasi',
            self::Hadir => 'Hadir',
            self::Berhalangan => 'Berhalangan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-500/15',
            self::Hadir => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::Berhalangan => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        };
    }
}
