<?php

namespace App\Support\Pdf;

/**
 * Tata letak PDF "Kartu Konsultasi" (Kerja Praktik dan Tugas Akhir) satu halaman A4.
 *
 * Kartu berisi identitas mahasiswa, judul, dan dosen pembimbing yang diisi dari sistem,
 * serta tabel kosong untuk mencatat konsultasi dengan tulisan tangan yang diparaf pembimbing.
 *
 * Kelas ini hanya menyusun dokumen dari data biasa (array) sehingga bisa diuji tanpa Laravel.
 * Data diambil dari Pengajuan::dataKartuKonsultasi().
 */
final class KartuKonsultasi
{
    private const UNIVERSITAS = 'UNIVERSITAS SRIWIJAYA';

    private const FAKULTAS = 'FAKULTAS ILMU KOMPUTER';

    private const SISTEM = 'Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA';

    private const NAVY = [27, 49, 96];

    private const ABU = [100, 116, 139];

    private const GARIS = [148, 163, 184];

    private const MARGIN = 50;

    /** Tinggi satu baris tabel konsultasi, cukup untuk tulisan tangan. */
    private const TINGGI_BARIS = 32;

    private const TINGGI_HEADER = 26;

    /** Jarak garis dasar label tanda tangan dari tepi bawah halaman; blok ini tetap, tabel yang menyesuaikan. */
    private const TTD_DARI_BAWAH = 150;

    /** Jarak kosong antara dasar tabel dan label tanda tangan. */
    private const JEDA_TTD = 22;

    private const BARIS_MIN = 5;

    private const BARIS_MAKS = 14;

    /**
     * @param  array{jenis: string, nama: string, nim: ?string, prodi: ?string, judul: string, instansi: ?string, pembimbing: ?string, pembimbing_nomor: ?string, dicetak: string}  $data
     * @param  string|null  $logoPng  path berkas PNG logo; kop dicetak tanpa logo bila null, tidak ditemukan, atau ekstensi GD tidak aktif
     * @return string isi berkas PDF
     */
    public static function buat(array $data, ?string $logoPng = null): string
    {
        $d = new Dokumen('Kartu Konsultasi '.$data['jenis'].' - '.$data['nama']);

        $kiri = self::MARGIN;
        $kanan = Dokumen::LEBAR - self::MARGIN;
        $lebar = $kanan - $kiri;

        self::kop($d, $kiri, $lebar, $logoPng);

        // Judul dokumen
        $d->teks($kiri, 136, 'KARTU KONSULTASI '.mb_strtoupper($data['jenis']), 14, true, self::NAVY, 'tengah', $lebar);
        $d->teks($kiri, 151, 'Dibawa pada setiap konsultasi dan diparaf oleh dosen pembimbing.', 8.5, false, self::ABU, 'tengah', $lebar);

        $yTtd = Dokumen::TINGGI - self::TTD_DARI_BAWAH;

        $y = self::identitas($d, $data, $kiri, $kanan, 182);
        self::tabel($d, $kiri, $lebar, $y + 12, $yTtd - self::JEDA_TTD);
        self::tandaTangan($d, $data, $kiri, $kanan, $yTtd);

        $d->teks($kiri, Dokumen::TINGGI - 38, 'Dicetak dari '.self::SISTEM.' pada '.$data['dicetak'].'.', 8, false, self::ABU, 'tengah', $lebar);

        return $d->hasil();
    }

    private static function kop(Dokumen $d, float $kiri, float $lebar, ?string $logoPng): void
    {
        $jpeg = self::logoJpeg($logoPng);
        if ($jpeg !== null) {
            [$w, $h] = getimagesizefromstring($jpeg);
            $tinggi = 58;
            $d->gambarJpeg($jpeg, $kiri, 34, $tinggi * $w / $h, $tinggi);
        }

        $d->teks($kiri, 58, self::UNIVERSITAS, 15, true, self::NAVY, 'tengah', $lebar);
        $d->teks($kiri, 75, self::FAKULTAS, 12.5, true, self::NAVY, 'tengah', $lebar);
        $d->teks($kiri, 90, self::SISTEM, 8.5, false, self::ABU, 'tengah', $lebar);

        // Garis ganda penutup kop surat.
        $d->garis($kiri, 100, $kiri + $lebar, 100, 1.8, self::NAVY);
        $d->garis($kiri, 104, $kiri + $lebar, 104, 0.5, self::NAVY);
    }

    /**
     * Blok identitas (label, titik dua, nilai).
     *
     * @param  array<string, mixed>  $data
     * @return float posisi y tepat di bawah blok
     */
    private static function identitas(Dokumen $d, array $data, float $kiri, float $kanan, float $y): float
    {
        $kolomTitikDua = $kiri + 118;
        $kolomNilai = $kolomTitikDua + 10;
        $lebarNilai = $kanan - $kolomNilai;

        $baris = [
            ['Nama Mahasiswa', $data['nama'], true],
            ['NIM', $data['nim'] ?: '-', false],
            ['Program Studi', $data['prodi'] ?: '-', false],
            ['Judul '.$data['jenis'], $data['judul'], false],
        ];
        if (! empty($data['instansi'])) {
            $baris[] = ['Instansi', $data['instansi'], false];
        }
        $baris[] = ['Dosen Pembimbing', $data['pembimbing'] ?: '-', true];
        if (! empty($data['pembimbing_nomor'])) {
            $baris[] = ['NIP/NIDN Pembimbing', $data['pembimbing_nomor'], false];
        }

        foreach ($baris as [$label, $nilai, $tebal]) {
            $teks = $d->bungkus((string) $nilai, $lebarNilai, 10.5, $tebal);
            $teks = $teks === [] ? ['-'] : $teks;

            $d->teks($kiri, $y, $label, 10.5);
            $d->teks($kolomTitikDua, $y, ':', 10.5);
            foreach ($teks as $i => $potongan) {
                $d->teks($kolomNilai, $y + $i * 14, $potongan, 10.5, $tebal);
            }

            $y += count($teks) * 14 + 5;
        }

        return $y;
    }

    /**
     * Tabel konsultasi kosong. Jumlah baris menyesuaikan sisa ruang antara $atas dan $batasBawah.
     */
    private static function tabel(Dokumen $d, float $kiri, float $lebar, float $atas, float $batasBawah): void
    {
        $sisa = $batasBawah - $atas - self::TINGGI_HEADER;
        $jumlah = max(self::BARIS_MIN, min(self::BARIS_MAKS, (int) floor($sisa / self::TINGGI_BARIS)));

        $kolom = ['No' => 30, 'Hari / Tanggal' => 95, 'Materi / Pokok Bahasan Konsultasi' => 270];
        $kolom['Paraf Pembimbing'] = $lebar - array_sum($kolom);

        $tinggiTotal = self::TINGGI_HEADER + $jumlah * self::TINGGI_BARIS;

        // Kepala tabel
        $d->kotak($kiri, $atas, $lebar, self::TINGGI_HEADER, self::NAVY, self::NAVY);
        $x = $kiri;
        foreach ($kolom as $judul => $lebarKolom) {
            $d->teks($x, $atas + 17, $judul, 9.5, true, [255, 255, 255], 'tengah', $lebarKolom);
            $x += $lebarKolom;
        }

        // Garis dalam: horizontal per baris, vertikal per kolom.
        for ($i = 1; $i < $jumlah; $i++) {
            $yGaris = $atas + self::TINGGI_HEADER + $i * self::TINGGI_BARIS;
            $d->garis($kiri, $yGaris, $kiri + $lebar, $yGaris, 0.5, self::GARIS);
        }
        $x = $kiri;
        foreach (array_slice($kolom, 0, -1, true) as $lebarKolom) {
            $x += $lebarKolom;
            $d->garis($x, $atas + self::TINGGI_HEADER, $x, $atas + $tinggiTotal, 0.5, self::GARIS);
        }

        // Nomor urut dicetak agar pembimbing dapat menghitung pertemuan; kolom lain dikosongkan.
        for ($i = 0; $i < $jumlah; $i++) {
            $d->teks(
                $kiri,
                $atas + self::TINGGI_HEADER + $i * self::TINGGI_BARIS + 20,
                (string) ($i + 1),
                9.5,
                false,
                self::ABU,
                'tengah',
                $kolom['No'],
            );
        }

        // Bingkai luar paling akhir agar tidak tertimpa garis dalam.
        $d->kotak($kiri, $atas, $lebar, $tinggiTotal, null, self::NAVY, 0.8);
    }

    /**
     * Blok tanda tangan mahasiswa (kiri) dan dosen pembimbing (kanan).
     *
     * @param  array<string, mixed>  $data
     */
    private static function tandaTangan(Dokumen $d, array $data, float $kiri, float $kanan, float $y): void
    {
        $lebarBlok = 190;
        $kolom = [
            [$kiri, 'Mahasiswa,', $data['nama'], $data['nim'] ? 'NIM. '.$data['nim'] : null],
            [$kanan - $lebarBlok, 'Dosen Pembimbing,', $data['pembimbing'] ?: '', ! empty($data['pembimbing_nomor']) ? 'NIP/NIDN. '.$data['pembimbing_nomor'] : null],
        ];

        foreach ($kolom as [$x, $peran, $nama, $nomor]) {
            $d->teks($x, $y, $peran, 10, false, [0, 0, 0], 'tengah', $lebarBlok);

            // Ruang tanda tangan 54 pt, lalu nama bergaris bawah dan nomor induk.
            $yNama = $y + 66;
            foreach ($d->bungkus((string) $nama, $lebarBlok, 10, true) as $i => $potongan) {
                $d->teks($x, $yNama + $i * 12, $potongan, 10, true, [0, 0, 0], 'tengah', $lebarBlok);
                $garisBawah = $d->lebarTeks($potongan, 10, true);
                $mulai = $x + ($lebarBlok - $garisBawah) / 2;
                $d->garis($mulai, $yNama + $i * 12 + 1.5, $mulai + $garisBawah, $yNama + $i * 12 + 1.5, 0.5);
            }

            if ($nomor !== null) {
                $d->teks($x, $yNama + 14 + max(0, count($d->bungkus((string) $nama, $lebarBlok, 10, true)) - 1) * 12, $nomor, 9.5, false, [0, 0, 0], 'tengah', $lebarBlok);
            }
        }
    }

    /**
     * Logo PNG (umumnya bertransparansi) diratakan ke latar putih dan dijadikan JPEG agar bisa
     * ditanam langsung ke PDF. Null bila logo tidak tersedia atau ekstensi GD tidak aktif.
     */
    private static function logoJpeg(?string $png): ?string
    {
        if ($png === null || ! is_file($png) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $sumber = @imagecreatefrompng($png);
        if ($sumber === false) {
            return null;
        }

        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);

        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        imagefill($kanvas, 0, 0, (int) imagecolorallocate($kanvas, 255, 255, 255));
        imagecopy($kanvas, $sumber, 0, 0, 0, 0, $lebar, $tinggi);

        ob_start();
        imagejpeg($kanvas, null, 90);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
}
