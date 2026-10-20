<?php

namespace App\Enums;

/** Hasil pemeriksaan akademik oleh Dosen Pembimbing. */
enum StatusAkademik: string
{
    case Menunggu = 'menunggu';
    case Revisi = 'revisi';
    case Disetujui = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pemeriksaan',
            self::Revisi => 'Perlu revisi',
            self::Disetujui => 'Disetujui',
        };
    }
}
