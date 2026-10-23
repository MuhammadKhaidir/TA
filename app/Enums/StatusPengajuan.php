<?php

namespace App\Enums;

enum StatusPengajuan: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case RevisiAdministrasi = 'revisi_administrasi';
    case MenungguDosen = 'menunggu_dosen';
    case RevisiAkademik = 'revisi_akademik';
    case Disetujui = 'disetujui';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu Verifikasi Administrasi',
            self::RevisiAdministrasi => 'Revisi Administrasi',
            self::MenungguDosen => 'Menunggu Pemeriksaan Dosen',
            self::RevisiAkademik => 'Revisi Akademik',
            self::Disetujui => 'Disetujui, Menunggu Dokumen Resmi',
            self::Diarsipkan => 'Selesai dan Diarsipkan',
        };
    }

    /** Penjelasan singkat untuk mahasiswa tentang langkah berikutnya. */
    public function keterangan(): string
    {
        return match ($this) {
            self::Draft => 'Lengkapi data dan unggah semua dokumen wajib, lalu kirim pengajuan.',
            self::Diajukan => 'Operator akademik sedang memeriksa kelengkapan administrasi.',
            self::RevisiAdministrasi => 'Perbaiki dokumen yang ditandai revisi, lalu kirim ulang.',
            self::MenungguDosen => 'Dosen pembimbing sedang memeriksa berkas akademik.',
            self::RevisiAkademik => 'Perbaiki dokumen yang ditandai revisi oleh dosen, lalu kirim ulang.',
            self::Disetujui => 'Persyaratan terpenuhi. Operator akademik sedang memproses dokumen resmi.',
            self::Diarsipkan => 'Proses selesai. Dokumen final tersimpan di arsip digital.',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::Diajukan => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::RevisiAdministrasi, self::RevisiAkademik => 'bg-amber-50 text-amber-800 ring-amber-200',
            self::MenungguDosen => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            self::Disetujui => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Diarsipkan => 'bg-navy-50 text-navy-800 ring-navy-200',
        };
    }

    /** Status di mana mahasiswa masih boleh mengubah data dan mengunggah dokumen. */
    public function bisaDiubahMahasiswa(): bool
    {
        return in_array($this, [self::Draft, self::RevisiAdministrasi, self::RevisiAkademik], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $status) {
            $hasil[$status->value] = $status->label();
        }

        return $hasil;
    }
}
