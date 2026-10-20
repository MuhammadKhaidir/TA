<?php

namespace App\Enums;

enum StatusSurat: string
{
    case Diajukan = 'diajukan';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Diproses => 'Sedang diproses',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Diajukan => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            self::Diproses => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/30',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::Ditolak => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        };
    }
}
