<?php

namespace App\Enums;

/**
 * Status dosen pembimbing pada sebuah pengajuan.
 *
 *  KP : belum -> ditetapkan (operator menetapkan dari daftar dosen)
 *  TA : menunggu (dipilih mahasiswa) -> diterima | ditolak -> (bila ditolak) ditetapkan oleh operator
 */
enum StatusPembimbing: string
{
    case Belum = 'belum';
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';
    case Ditetapkan = 'ditetapkan';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum ditentukan',
            self::Menunggu => 'Menunggu konfirmasi dosen',
            self::Diterima => 'Diterima dosen',
            self::Ditolak => 'Ditolak dosen',
            self::Ditetapkan => 'Ditetapkan operator',
        };
    }

    /** Label untuk baris riwayat pembimbing. */
    public function labelRiwayat(): string
    {
        return match ($this) {
            self::Belum => 'Belum ditentukan',
            self::Menunggu => 'Dipilih mahasiswa',
            self::Diterima => 'Diterima dosen',
            self::Ditolak => 'Ditolak dosen',
            self::Ditetapkan => 'Ditetapkan operator',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Belum => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::Menunggu => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::Diterima => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Ditolak => 'bg-red-50 text-red-700 ring-red-200',
            self::Ditetapkan => 'bg-navy-50 text-navy-800 ring-navy-200',
        };
    }

    /** Pembimbing sudah sah: dosen boleh memeriksa berkas pengajuan. */
    public function aktif(): bool
    {
        return in_array($this, [self::Diterima, self::Ditetapkan], true);
    }
}
