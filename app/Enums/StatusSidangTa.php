<?php

namespace App\Enums;

/**
 * Tahap pengajuan sidang TA. Nama enum sengaja berbeda dari tabel/enum sidang lama
 * (`sidangs`, `sidang_pengujis`, ...) yang tetap dibiarkan utuh.
 */
enum StatusSidangTa: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case PerluRevisi = 'perlu_revisi';
    case Terverifikasi = 'terverifikasi';
    case Disetujui = 'disetujui';
    case Dijadwalkan = 'dijadwalkan';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';
    case Digantikan = 'digantikan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu verifikasi Operator',
            self::PerluRevisi => 'Perlu perbaikan',
            self::Terverifikasi => 'Menunggu persetujuan pembimbing',
            self::Disetujui => 'Disetujui, menunggu penjadwalan',
            self::Dijadwalkan => 'Dijadwalkan',
            self::Selesai => 'Sidang selesai',
            self::Dibatalkan => 'Dibatalkan',
            self::Digantikan => 'Digantikan pengajuan baru',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/15',
            self::Diajukan, self::Terverifikasi => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            self::PerluRevisi => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
            self::Disetujui, self::Dijadwalkan => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/30',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::Dibatalkan, self::Digantikan => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/15',
        };
    }

    /** Pengajuan sidang yang masih "hidup": menghalangi pengajuan sidang kedua untuk TA yang sama. */
    public function aktif(): bool
    {
        return ! in_array($this, [self::Dibatalkan, self::Digantikan], true);
    }

    public function bolehDiubahMahasiswa(): bool
    {
        return in_array($this, [self::Draft, self::PerluRevisi], true);
    }

    /** @return array<int, string> */
    public static function nilaiAktif(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->aktif()));
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
