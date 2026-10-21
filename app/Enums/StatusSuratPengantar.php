<?php

namespace App\Enums;

/**
 * Tahap surat pengantar TA: mahasiswa mengisi dan mengirim -> Operator memeriksa kelengkapan
 * -> surat diproses offline -> selesai/diambil mahasiswa. Surat resmi dicetak dan
 * ditandatangani di luar sistem; sistem hanya mencatat tahapnya.
 */
enum StatusSuratPengantar: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case PerluRevisi = 'perlu_revisi';
    case Lengkap = 'lengkap';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu pemeriksaan kelengkapan',
            self::PerluRevisi => 'Perlu perbaikan',
            self::Lengkap => 'Lengkap, menunggu diproses',
            self::Diproses => 'Sedang diproses',
            self::Selesai => 'Selesai / sudah diambil',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/15',
            self::Diajukan => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            self::PerluRevisi => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
            self::Lengkap, self::Diproses => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/30',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::Dibatalkan => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/15',
        };
    }

    /** Mahasiswa masih boleh mengubah isian dan dokumen. */
    public function bolehDiubahMahasiswa(): bool
    {
        return in_array($this, [self::Draft, self::PerluRevisi], true);
    }
}
