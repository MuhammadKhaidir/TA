<?php

namespace App\Enums;

/**
 * Status gabungan yang dilihat semua pihak. Nilainya DITURUNKAN dari
 * status_administrasi dan status_akademik oleh PengajuanService, bukan diisi
 * langsung dari request, sehingga tidak bisa dimanipulasi dari luar.
 */
enum StatusPengajuan: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case PerluRevisi = 'perlu_revisi';
    case Disetujui = 'disetujui';
    case Selesai = 'selesai';
    /** Dibatalkan sebelum selesai (oleh mahasiswa atau Operator); dapat diajukan ulang. */
    case Dibatalkan = 'dibatalkan';
    /** Sudah diganti pengajuan baru hasil "ajukan ulang". Hanya arsip. */
    case Digantikan = 'digantikan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu Verifikasi',
            self::PerluRevisi => 'Perlu Revisi',
            self::Disetujui => 'Disetujui',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
            self::Digantikan => 'Digantikan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/15',
            self::Diajukan => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            self::PerluRevisi => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
            self::Disetujui => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/30',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::Dibatalkan, self::Digantikan => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/15',
        };
    }

    /** Pengajuan yang sudah berhenti (selesai, dibatalkan, atau digantikan): tidak lagi dihitung "berjalan". */
    public function berhenti(): bool
    {
        return in_array($this, [self::Selesai, self::Dibatalkan, self::Digantikan], true);
    }

    /** @return array<int, string> */
    public static function nilaiBerhenti(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->berhenti()));
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
