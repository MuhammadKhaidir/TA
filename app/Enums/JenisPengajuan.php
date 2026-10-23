<?php

namespace App\Enums;

enum JenisPengajuan: string
{
    case Kp = 'kp';
    case Ta = 'ta';

    public function label(): string
    {
        return match ($this) {
            self::Kp => 'Kerja Praktik',
            self::Ta => 'Tugas Akhir',
        };
    }

    public function singkat(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Jenis dokumen resmi yang dapat diterbitkan operator untuk jenis pengajuan ini.
     *
     * @return array<string, string> kode => label
     */
    public function jenisDokumenResmi(): array
    {
        return match ($this) {
            self::Kp => [
                'surat_pengantar_kp' => 'Surat Pengantar KP',
                'sk_kp' => 'SK / Surat Tugas KP',
                'lainnya' => 'Dokumen Resmi Lainnya',
            ],
            self::Ta => [
                'sk_ta' => 'SK Tugas Akhir',
                'lainnya' => 'Dokumen Resmi Lainnya',
            ],
        };
    }

    public static function labelDokumenResmi(string $kode): string
    {
        foreach (self::cases() as $jenis) {
            if (isset($jenis->jenisDokumenResmi()[$kode])) {
                return $jenis->jenisDokumenResmi()[$kode];
            }
        }

        return $kode;
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $jenis) {
            $hasil[$jenis->value] = $jenis->label();
        }

        return $hasil;
    }
}
