<?php

namespace App\Enums;

/**
 * Jenis dokumen yang muncul di kolom "Kelengkapan" / "Keluaran (Output)"
 * pada Bagan Alir POS, ditambah dokumen pada bagian PERLENGKAPAN/PERALATAN.
 */
enum JenisDokumen: string
{
    case Dkn = 'dkn';
    case BuktiKelulusanUsep = 'bukti_kelulusan_usep';
    case FormChecklistSyarat = 'form_checklist_syarat';
    case SkPembimbingTa = 'sk_pembimbing_ta';
    case DokumenTa = 'dokumen_ta';
    case DraftSkPenguji = 'draft_sk_penguji';
    case SkPenguji = 'sk_penguji';
    case DokumenAdministrasi = 'dokumen_administrasi';
    case DaftarHadir = 'daftar_hadir';
    case FormNilai = 'form_nilai';
    case FormRevisi = 'form_revisi';
    case BeritaAcara = 'berita_acara';
    case SuratPernyataanYudisium = 'surat_pernyataan_yudisium';
    case FormPergantian = 'form_pergantian';

    public function label(): string
    {
        return match ($this) {
            self::Dkn => 'DKN (Daftar Kumpulan Nilai)',
            self::BuktiKelulusanUsep => 'Bukti Kelulusan USEP',
            self::FormChecklistSyarat => 'Form Ceklist Syarat Sidang TA',
            self::SkPembimbingTa => 'SK Pembimbing TA',
            self::DokumenTa => 'Dokumen Tugas Akhir (PDF)',
            self::DraftSkPenguji => 'Draft SK Penguji',
            self::SkPenguji => 'SK Penguji (Disahkan)',
            self::DokumenAdministrasi => 'Dokumen Administrasi Sidang TA',
            self::DaftarHadir => 'Daftar Hadir Pelaksanaan Sidang',
            self::FormNilai => 'Form Penilaian Dewan Penguji',
            self::FormRevisi => 'Form Revisi',
            self::BeritaAcara => 'Berita Acara Sidang',
            self::SuratPernyataanYudisium => 'Surat Pernyataan Yudisium',
            self::FormPergantian => 'Form Pergantian Dosen Penguji',
        };
    }

    /**
     * Dokumen yang wajib dilampirkan mahasiswa saat pengajuan (Langkah 1).
     *
     * @return array<int, self>
     */
    public static function syaratPengajuan(): array
    {
        return [self::Dkn, self::BuktiKelulusanUsep];
    }
}
