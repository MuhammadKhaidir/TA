<?php

namespace App\Enums;

enum JenisPersyaratan: string
{
    case TaPengajuan = 'ta_pengajuan';
    case KpPengajuan = 'kp_pengajuan';
    case KpLampiran = 'kp_lampiran';
    /** Dokumen Kumpulan A pada surat pengantar TA. */
    case TaSuratPengantar = 'ta_surat_pengantar';
    /** Dokumen pendaftaran sidang TA. */
    case TaSidang = 'ta_sidang';

    public function label(): string
    {
        return match ($this) {
            self::TaPengajuan => 'Dokumen pengajuan TA',
            self::KpPengajuan => 'Dokumen pengajuan KP',
            self::KpLampiran => 'Lampiran laporan KP',
            self::TaSuratPengantar => 'Dokumen surat pengantar TA',
            self::TaSidang => 'Dokumen pendaftaran sidang TA',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $j) => [$j->value => $j->label()])->all();
    }
}
