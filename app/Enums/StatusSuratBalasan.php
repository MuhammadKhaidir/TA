<?php

namespace App\Enums;

/** Hasil validasi Operator atas surat balasan yang diunggah mahasiswa. */
enum StatusSuratBalasan: string
{
    case Menunggu = 'menunggu';
    case Valid = 'valid';
    case TidakValid = 'tidak_valid';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu validasi',
            self::Valid => 'Valid',
            self::TidakValid => 'Tidak valid, perlu diunggah ulang',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            self::Valid => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::TidakValid => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        };
    }
}
