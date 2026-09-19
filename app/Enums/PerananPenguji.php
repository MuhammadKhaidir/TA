<?php

namespace App\Enums;

enum PerananPenguji: string
{
    case Ketua = 'ketua';
    case Anggota = 'anggota';
    case Pembimbing = 'pembimbing';

    public function label(): string
    {
        return match ($this) {
            self::Ketua => 'Ketua Sidang',
            self::Anggota => 'Anggota Penguji',
            self::Pembimbing => 'Pembimbing TA',
        };
    }
}
