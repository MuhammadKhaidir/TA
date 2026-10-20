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

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu Verifikasi',
            self::PerluRevisi => 'Perlu Revisi',
            self::Disetujui => 'Disetujui',
            self::Selesai => 'Selesai',
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
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
