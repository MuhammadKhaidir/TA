<?php

namespace App\Enums;

enum StatusBimbingan: string
{
    case Menunggu = 'menunggu';
    case Divalidasi = 'divalidasi';
    case PerluRevisi = 'perlu_revisi';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu validasi',
            self::Divalidasi => 'Divalidasi',
            self::PerluRevisi => 'Perlu diperbaiki',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/15',
            self::Divalidasi => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::PerluRevisi => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        };
    }
}
