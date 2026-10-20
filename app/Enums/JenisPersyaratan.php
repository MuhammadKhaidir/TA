<?php

namespace App\Enums;

enum JenisPersyaratan: string
{
    case TaPengajuan = 'ta_pengajuan';
    case KpPengajuan = 'kp_pengajuan';
    case KpLampiran = 'kp_lampiran';

    public function label(): string
    {
        return match ($this) {
            self::TaPengajuan => 'Dokumen pengajuan TA',
            self::KpPengajuan => 'Dokumen pengajuan KP',
            self::KpLampiran => 'Lampiran laporan KP',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $j) => [$j->value => $j->label()])->all();
    }
}
