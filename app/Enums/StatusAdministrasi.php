<?php

namespace App\Enums;

/** Hasil verifikasi kelengkapan administrasi oleh Operator Akademik. */
enum StatusAdministrasi: string
{
    case Menunggu = 'menunggu';
    case Revisi = 'revisi';
    case Terverifikasi = 'terverifikasi';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu verifikasi',
            self::Revisi => 'Dikembalikan untuk diperbaiki',
            self::Terverifikasi => 'Terverifikasi',
        };
    }
}
