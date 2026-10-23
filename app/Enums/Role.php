<?php

namespace App\Enums;

enum Role: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Operator = 'operator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Dosen => 'Dosen',
            self::Operator => 'Operator Akademik',
            self::Admin => 'Admin',
        };
    }

    /** Nama identitas utama yang dipakai role ini (disimpan di kolom users.nomor_induk). */
    public function labelNomorInduk(): string
    {
        return match ($this) {
            self::Mahasiswa => 'NIM',
            self::Dosen => 'NIP / NIDN',
            self::Operator, self::Admin => 'NIP / ID Pegawai',
        };
    }

    /** @return array<string, string> nilai => label, untuk elemen select */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $role) {
            $hasil[$role->value] = $role->label();
        }

        return $hasil;
    }
}
