<?php

namespace App\Enums;

enum JenisPengajuan: string
{
    case Ta = 'ta';
    case Kp = 'kp';

    public function label(): string
    {
        return match ($this) {
            self::Ta => 'Tugas Akhir (TA)',
            self::Kp => 'Kerja Praktik (KP)',
        };
    }

    public function singkat(): string
    {
        return strtoupper($this->value);
    }

    public function jenisPersyaratan(): JenisPersyaratan
    {
        return match ($this) {
            self::Ta => JenisPersyaratan::TaPengajuan,
            self::Kp => JenisPersyaratan::KpPengajuan,
        };
    }
}
