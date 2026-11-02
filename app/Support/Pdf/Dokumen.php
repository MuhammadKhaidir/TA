<?php

namespace App\Support\Pdf;

use InvalidArgumentException;

/**
 * Penulis PDF sederhana tanpa dependensi: halaman A4, teks (Helvetica), garis, kotak, dan gambar JPEG.
 *
 * Cukup untuk dokumen berbentuk formulir seperti kartu konsultasi. Untuk dokumen panjang atau
 * tata letak yang kompleks, pakai pustaka PDF penuh.
 *
 * Koordinat dalam satuan poin (1 pt = 1/72 inci) dengan titik asal di KIRI ATAS halaman dan
 * sumbu y mengarah ke bawah. Untuk teks, y adalah garis dasar (baseline).
 * Teks diterima sebagai UTF-8 dan dicetak dalam Windows-1252 (huruf Latin lengkap dengan aksen);
 * karakter di luar itu diganti "?".
 */
final class Dokumen
{
    /** A4: 210 x 297 mm. */
    public const LEBAR = 595.28;

    public const TINGGI = 841.89;

    /** Lebar glyph Helvetica (satuan 1/1000 em) untuk kode 32..255 (WinAnsi). */
    private const LEBAR_HELVETICA = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 761,
        556, 556, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 556, 611, 556,
        556, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 556, 500, 667,
        278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 333, 737, 333,
        400, 584, 333, 333, 333, 556, 537, 278, 333, 333, 365, 556, 834, 834, 834, 611,
        667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500,
    ];

    /** Lebar glyph Helvetica-Bold (satuan 1/1000 em) untuk kode 32..255 (WinAnsi). */
    private const LEBAR_HELVETICA_BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 761,
        556, 556, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 556, 611, 556,
        556, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 556, 500, 667,
        278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 333, 737, 333,
        400, 584, 333, 333, 333, 611, 556, 278, 333, 333, 365, 556, 834, 834, 834, 611,
        722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278,
        611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556,
    ];

    /** @var list<string> isi (content stream) halaman yang sudah selesai */
    private array $halamanSelesai = [];

    /** Isi halaman yang sedang digarap. */
    private string $isi = '';

    /** @var list<array{data: string, lebar: int, tinggi: int, kanal: int}> */
    private array $gambar = [];

    public function __construct(private string $judul = '') {}

    /** Menutup halaman saat ini dan memulai halaman kosong berikutnya. */
    public function halamanBaru(): void
    {
        $this->halamanSelesai[] = $this->isi;
        $this->isi = '';
    }

    /**
     * Menulis satu baris teks.
     *
     * @param  float  $x  tepi kiri; bila $rata bukan "kiri", tepi kiri kotak selebar $lebar
     * @param  float  $y  garis dasar teks, diukur dari atas halaman
     * @param  array{0: int, 1: int, 2: int}  $warna  RGB 0..255
     * @param  'kiri'|'tengah'|'kanan'  $rata
     */
    public function teks(
        float $x,
        float $y,
        string $teks,
        float $ukuran = 10,
        bool $tebal = false,
        array $warna = [0, 0, 0],
        string $rata = 'kiri',
        ?float $lebar = null,
    ): void {
        if ($teks === '') {
            return;
        }

        if ($rata !== 'kiri' && $lebar !== null) {
            $sisa = $lebar - $this->lebarTeks($teks, $ukuran, $tebal);
            $x += $rata === 'tengah' ? $sisa / 2 : $sisa;
        }

        $this->isi .= sprintf(
            "BT %s /%s %s Tf %s %s Td (%s) Tj ET\n",
            $this->warnaIsi($warna),
            $tebal ? 'F2' : 'F1',
            $this->angka($ukuran),
            $this->angka($x),
            $this->angka(self::TINGGI - $y),
            $this->escape($this->enkode($teks)),
        );
    }

    /** Lebar teks dalam poin pada ukuran huruf tertentu. */
    public function lebarTeks(string $teks, float $ukuran = 10, bool $tebal = false): float
    {
        $tabel = $tebal ? self::LEBAR_HELVETICA_BOLD : self::LEBAR_HELVETICA;
        $total = 0;

        foreach (str_split($this->enkode($teks)) as $karakter) {
            $kode = ord($karakter);
            $total += $kode >= 32 ? $tabel[$kode - 32] : 0;
        }

        return $total * $ukuran / 1000;
    }

    /**
     * Memecah teks menjadi baris-baris yang masing-masing muat dalam $lebarMaks.
     * Kata yang lebih panjang dari satu baris dipotong per karakter.
     *
     * @return list<string>
     */
    public function bungkus(string $teks, float $lebarMaks, float $ukuran = 10, bool $tebal = false): array
    {
        $teks = trim((string) preg_replace('/\s+/u', ' ', mb_scrub($teks)));
        if ($teks === '') {
            return [];
        }

        $baris = [];
        $saatIni = '';

        foreach (explode(' ', $teks) as $kata) {
            $calon = $saatIni === '' ? $kata : $saatIni.' '.$kata;

            if ($this->lebarTeks($calon, $ukuran, $tebal) <= $lebarMaks) {
                $saatIni = $calon;

                continue;
            }

            if ($saatIni !== '') {
                $baris[] = $saatIni;
                $saatIni = '';
            }

            // Kata panjang: potong per karakter sampai muat.
            $potongan = '';
            foreach (mb_str_split($kata) as $huruf) {
                if ($potongan !== '' && $this->lebarTeks($potongan.$huruf, $ukuran, $tebal) > $lebarMaks) {
                    $baris[] = $potongan;
                    $potongan = '';
                }
                $potongan .= $huruf;
            }
            $saatIni = $potongan;
        }

        if ($saatIni !== '') {
            $baris[] = $saatIni;
        }

        return $baris;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $warna
     * @param  list<float>|null  $putus  pola garis putus-putus, mis. [3, 2]
     */
    public function garis(float $x1, float $y1, float $x2, float $y2, float $tebal = 0.5, array $warna = [0, 0, 0], ?array $putus = null): void
    {
        $pola = $putus === null ? '' : '['.implode(' ', array_map($this->angka(...), $putus)).'] 0 d ';

        $this->isi .= sprintf(
            "q %s %s w %s%s %s m %s %s l S Q\n",
            $this->warnaGaris($warna),
            $this->angka($tebal),
            $pola,
            $this->angka($x1), $this->angka(self::TINGGI - $y1),
            $this->angka($x2), $this->angka(self::TINGGI - $y2),
        );
    }

    /**
     * Kotak dengan sudut kiri atas di ($x, $y).
     *
     * @param  array{0: int, 1: int, 2: int}|null  $isi  warna isi, null = tanpa isi
     * @param  array{0: int, 1: int, 2: int}|null  $garis  warna garis tepi, null = tanpa garis tepi
     */
    public function kotak(float $x, float $y, float $lebar, float $tinggi, ?array $isi = null, ?array $garis = [0, 0, 0], float $tebal = 0.5): void
    {
        if ($isi === null && $garis === null) {
            return;
        }

        $operator = match (true) {
            $isi !== null && $garis !== null => 'B',
            $isi !== null => 'f',
            default => 'S',
        };

        $this->isi .= sprintf(
            "q %s %s %s w %s %s %s %s re %s Q\n",
            $isi !== null ? $this->warnaIsi($isi) : '',
            $garis !== null ? $this->warnaGaris($garis) : '',
            $this->angka($tebal),
            $this->angka($x), $this->angka(self::TINGGI - $y - $tinggi),
            $this->angka($lebar), $this->angka($tinggi),
            $operator,
        );
    }

    /**
     * Menempatkan gambar JPEG (RGB atau grayscale) pada kotak ($x, $y, $lebar, $tinggi).
     * Data yang sama dipakai ulang, tidak disimpan dua kali di dalam berkas.
     */
    public function gambarJpeg(string $dataJpeg, float $x, float $y, float $lebar, float $tinggi): void
    {
        $info = @getimagesizefromstring($dataJpeg);
        $kanal = $info['channels'] ?? 3;

        if ($info === false || $info[2] !== IMAGETYPE_JPEG || ! in_array($kanal, [1, 3], true)) {
            throw new InvalidArgumentException('Gambar harus berupa JPEG RGB atau grayscale yang valid.');
        }

        $indeks = array_search($dataJpeg, array_column($this->gambar, 'data'), true);
        if ($indeks === false) {
            $this->gambar[] = ['data' => $dataJpeg, 'lebar' => $info[0], 'tinggi' => $info[1], 'kanal' => $kanal];
            $indeks = array_key_last($this->gambar);
        }

        $this->isi .= sprintf(
            "q %s 0 0 %s %s %s cm /Im%d Do Q\n",
            $this->angka($lebar), $this->angka($tinggi),
            $this->angka($x), $this->angka(self::TINGGI - $y - $tinggi),
            $indeks + 1,
        );
    }

    /** Menyusun seluruh berkas PDF dan mengembalikannya sebagai string biner. */
    public function hasil(): string
    {
        $halaman = [...$this->halamanSelesai, $this->isi];

        // Nomor objek: 1 katalog, 2 daftar halaman, 3-4 font, 5 info, lalu gambar, lalu pasangan (isi, halaman).
        $idGambar = [];
        $berikut = 6;
        foreach (array_keys($this->gambar) as $i) {
            $idGambar[$i] = $berikut++;
        }

        $sumber = '/Font << /F1 3 0 R /F2 4 0 R >>';
        if ($idGambar !== []) {
            $sumber .= ' /XObject << '.implode(' ', array_map(
                fn (int $i, int $id) => '/Im'.($i + 1).' '.$id.' 0 R',
                array_keys($idGambar),
                $idGambar,
            )).' >>';
        }

        $objek = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Title <'.$this->hexUtf16($this->judul).'> /Producer (Arsip Berkas KP dan TA) >>',
        ];

        foreach ($this->gambar as $i => $g) {
            $objek[$idGambar[$i]] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $g['lebar'], $g['tinggi'], $g['kanal'] === 1 ? '/DeviceGray' : '/DeviceRGB', strlen($g['data']), $g['data'],
            );
        }

        $idHalaman = [];
        foreach ($halaman as $isi) {
            $idIsi = $berikut++;
            $idHalaman[] = $idPage = $berikut++;

            $objek[$idIsi] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($isi), $isi);
            $objek[$idPage] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << %s >> /Contents %d 0 R >>',
                $this->angka(self::LEBAR), $this->angka(self::TINGGI), $sumber, $idIsi,
            );
        }

        $objek[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(fn (int $id) => $id.' 0 R', $idHalaman)),
            count($idHalaman),
        );

        ksort($objek);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posisi = [];
        foreach ($objek as $id => $badan) {
            $posisi[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$badan}\nendobj\n";
        }

        $jumlah = max(array_keys($objek)) + 1;
        $mulaiXref = strlen($pdf);
        $pdf .= "xref\n0 {$jumlah}\n0000000000 65535 f \n";
        for ($id = 1; $id < $jumlah; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $posisi[$id]);
        }

        return $pdf."trailer\n<< /Size {$jumlah} /Root 1 0 R /Info 5 0 R >>\nstartxref\n{$mulaiXref}\n%%EOF\n";
    }

    /** UTF-8 ke Windows-1252; aman terhadap UTF-8 rusak dan karakter kontrol. */
    private function enkode(string $teks): string
    {
        $teks = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', mb_scrub($teks));

        return mb_convert_encoding($teks, 'Windows-1252', 'UTF-8');
    }

    private function escape(string $teks): string
    {
        return strtr($teks, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    /** String Unicode PDF (UTF-16BE dengan BOM) dalam heksadesimal, untuk judul dokumen. */
    private function hexUtf16(string $teks): string
    {
        return 'FEFF'.strtoupper(bin2hex(mb_convert_encoding(mb_scrub($teks), 'UTF-16BE', 'UTF-8')));
    }

    /** Bilangan desimal dengan titik, tidak terpengaruh locale. */
    private function angka(float $nilai): string
    {
        return rtrim(rtrim(sprintf('%.2F', $nilai), '0'), '.') ?: '0';
    }

    /** @param  array{0: int, 1: int, 2: int}  $rgb */
    private function warnaIsi(array $rgb): string
    {
        return implode(' ', array_map(fn (int $k) => $this->angka($k / 255), $rgb)).' rg';
    }

    /** @param  array{0: int, 1: int, 2: int}  $rgb */
    private function warnaGaris(array $rgb): string
    {
        return implode(' ', array_map(fn (int $k) => $this->angka($k / 255), $rgb)).' RG';
    }
}
