<?php

namespace App\Enums;

/**
 * Empat aktor yang boleh login. Nilai enum = nama role pada tabel roles.
 */
enum Peran: string
{
    case Mahasiswa = 'mahasiswa';
    case Dospem = 'dospem';
    case Admin = 'admin';
    case OperatorAkademik = 'operator_akademik';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Dospem => 'Dosen Pembimbing',
            self::Admin => 'Admin',
            self::OperatorAkademik => 'Operator Akademik',
        };
    }

    /** URL tujuan setelah login. Admin memakai panel Filament di /admin. */
    public function halamanAwal(): string
    {
        return match ($this) {
            self::Mahasiswa => route('mahasiswa.dashboard'),
            self::Dospem => route('dospem.dashboard'),
            self::OperatorAkademik => route('operator.dashboard'),
            self::Admin => '/admin',
        };
    }

    /** @return array<int, string> */
    public static function nilai(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
