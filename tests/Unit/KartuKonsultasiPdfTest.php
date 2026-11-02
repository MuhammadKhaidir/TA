<?php

namespace Tests\Unit;

use App\Support\Pdf\Dokumen;
use App\Support\Pdf\KartuKonsultasi;
use PHPUnit\Framework\TestCase;

/**
 * Penyusun PDF tidak bergantung pada Laravel, jadi diuji sebagai unit murni.
 * Teks pada PDF buatan Dokumen tidak dikompres, sehingga isinya bisa dicari langsung di dalam berkas.
 */
class KartuKonsultasiPdfTest extends TestCase
{
    /** @return array<string, string|null> */
    private function data(array $ubah = []): array
    {
        return $ubah + [
            'jenis' => 'Kerja Praktik',
            'nama' => 'Mahasiswa Uji',
            'nim' => '09021282227001',
            'prodi' => 'S1 Sistem Informasi, angkatan 2022',
            'judul' => 'Sistem Informasi Inventori',
            'instansi' => 'PT Contoh Nusantara',
            'pembimbing' => 'Dr. Dosen Alpha, M.Kom.',
            'pembimbing_nomor' => '198001012005011001',
            'dicetak' => '29 Oktober 2026',
        ];
    }

    public function test_menghasilkan_pdf_a4_satu_halaman_dengan_data_pengajuan(): void
    {
        $pdf = KartuKonsultasi::buat($this->data());

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $pdf);
        $this->assertSame(1, preg_match_all('#/Type /Page /#', $pdf));

        foreach ([
            'KARTU KONSULTASI KERJA PRAKTIK', 'Mahasiswa Uji', '09021282227001', 'S1 Sistem Informasi, angkatan 2022',
            'Sistem Informasi Inventori', 'PT Contoh Nusantara', 'Dr. Dosen Alpha, M.Kom.', '198001012005011001',
        ] as $teks) {
            $this->assertStringContainsString('('.$teks.')', $pdf, $teks);
        }
    }

    public function test_tabel_xref_menunjuk_tepat_ke_awal_setiap_objek(): void
    {
        $pdf = KartuKonsultasi::buat($this->data());

        $this->assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $mulai));
        $this->assertSame('xref', substr($pdf, (int) $mulai[1], 4));

        $this->assertGreaterThan(0, preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $baris));
        foreach ($baris[1] as $i => $posisi) {
            $id = $i + 1;
            $this->assertSame("{$id} 0 obj", substr($pdf, (int) $posisi, strlen("{$id} 0 obj")), "objek {$id}");
        }
    }

    public function test_karakter_khusus_di_dalam_teks_aman(): void
    {
        $pdf = KartuKonsultasi::buat($this->data(['judul' => 'Judul (uji) \\ dengan café']));

        // Kurung dan garis miring di-escape; huruf beraksen dicetak dalam Windows-1252 (é = 0xE9).
        $this->assertStringContainsString("Judul \\(uji\\) \\\\ dengan caf\xE9", $pdf);
    }

    public function test_data_opsional_boleh_kosong(): void
    {
        $pdf = KartuKonsultasi::buat($this->data([
            'nim' => null, 'prodi' => null, 'instansi' => null, 'pembimbing_nomor' => null,
        ]));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('NIP/NIDN', $pdf);
        $this->assertStringNotContainsString('(Instansi)', $pdf);
    }

    public function test_judul_panjang_tetap_satu_halaman_dan_tabel_tidak_kurang_dari_batas_minimum(): void
    {
        $pendek = KartuKonsultasi::buat($this->data());
        $panjang = KartuKonsultasi::buat($this->data(['judul' => str_repeat('Judul sangat panjang ', 12).str_repeat('X', 120)]));

        $this->assertSame(1, preg_match_all('#/Type /Page /#', $panjang));

        $barisPendek = $this->jumlahBaris($pendek);
        $barisPanjang = $this->jumlahBaris($panjang);

        $this->assertGreaterThanOrEqual(5, $barisPanjang);
        $this->assertLessThanOrEqual($barisPendek, $barisPanjang);
        $this->assertLessThanOrEqual(14, $barisPendek);
    }

    public function test_logo_ditanam_bila_tersedia_dan_dilewati_bila_tidak(): void
    {
        $tanpa = KartuKonsultasi::buat($this->data(), __DIR__.'/tidak-ada.png');
        $this->assertStringNotContainsString('/DCTDecode', $tanpa);

        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('Ekstensi GD tidak aktif.');
        }

        $dengan = KartuKonsultasi::buat($this->data(), __DIR__.'/../../public/images/UnsriLogo.png');
        $this->assertStringContainsString('/DCTDecode', $dengan);
        $this->assertStringStartsWith('%PDF-', $dengan);
    }

    public function test_dokumen_mengukur_dan_membungkus_teks_sesuai_lebar_huruf(): void
    {
        $d = new Dokumen;

        // Helvetica: H=722, e=556, l=222, l=222, o=556 (per 1000 em) = 2278.
        $this->assertEqualsWithDelta(22.78, $d->lebarTeks('Hello', 10), 0.001);

        $baris = $d->bungkus('satu dua tiga empat lima enam tujuh delapan sembilan sepuluh', 100, 10);
        $this->assertGreaterThan(1, count($baris));
        foreach ($baris as $b) {
            $this->assertLessThanOrEqual(100, $d->lebarTeks($b, 10), $b);
        }

        // Kata tanpa spasi yang lebih panjang dari satu baris dipotong, tidak hilang.
        $potong = $d->bungkus(str_repeat('W', 40), 100, 10);
        $this->assertGreaterThan(1, count($potong));
        $this->assertSame(str_repeat('W', 40), implode('', $potong));

        $this->assertSame([], $d->bungkus("  \n ", 100, 10));
    }

    /** Jumlah baris tabel = nomor urut tertinggi yang dicetak di kolom "No". */
    private function jumlahBaris(string $pdf): int
    {
        preg_match_all('/\((\d{1,2})\) Tj/', $pdf, $cocok);

        return (int) max($cocok[1]);
    }
}
