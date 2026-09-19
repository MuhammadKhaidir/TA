<?php

namespace App\Enums;

enum JenisUjian: string
{
    case Komprehensif = 'komprehensif';
    case Skripsi = 'skripsi';

    public function label(): string
    {
        return match ($this) {
            self::Komprehensif => 'Ujian Komprehensif',
            self::Skripsi => 'Sidang Skripsi',
        };
    }

    /**
     * Batas maksimal durasi pelaksanaan sidang, dalam menit.
     * Peringatan POS #5: sidang skripsi tidak boleh melebihi 1 jam.
     */
    public function batasDurasiMenit(): ?int
    {
        return match ($this) {
            self::Skripsi => 60,
            self::Komprehensif => null,
        };
    }
}
