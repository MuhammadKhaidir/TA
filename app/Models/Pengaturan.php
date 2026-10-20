<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi key-value yang dikelola Admin (format nomor SK, penandatangan,
 * jumlah minimum bimbingan, dst). Nilai kosong berarti belum ditetapkan kampus.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturans';

    protected $fillable = ['kunci', 'nilai'];

    /** Label dan petunjuk tiap kunci; dipakai panel Admin. */
    public const DAFTAR = [
        'sk.format_nomor' => ['Format nomor SK', 'Penanda: {urut}, {tahun}, {kode_prodi}, {bulan_romawi}. Contoh: {urut}/SK/{kode_prodi}/{tahun}'],
        'sk.kota' => ['Kota penerbitan SK', 'Tampil pada tanggal SK, misalnya nama kota kampus.'],
        'sk.penandatangan_nama' => ['Nama penandatangan SK', 'Diisi sesuai pejabat yang berwenang menurut kampus.'],
        'sk.penandatangan_jabatan' => ['Jabatan penandatangan SK', 'Diisi sesuai pejabat yang berwenang menurut kampus.'],
        'bimbingan.minimal_ta' => ['Minimal bimbingan TA', 'Kosongkan bila tidak ada batas minimum.'],
        'bimbingan.minimal_kp' => ['Minimal bimbingan KP', 'Kosongkan sampai kampus menetapkan jumlah minimum.'],
        'kp.pengumpulan_lampiran_aktif' => ['Pengumpulan lampiran laporan KP', '1 = mahasiswa dapat mengunggah lampiran, 0 = hanya melihat daftar.'],
    ];

    public static function ambil(string $kunci, ?string $default = null): ?string
    {
        $nilai = static::query()->where('kunci', $kunci)->value('nilai');

        return ($nilai === null || trim($nilai) === '') ? $default : trim($nilai);
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }

    public static function minimalBimbingan(string $jenis): ?int
    {
        $nilai = static::ambil("bimbingan.minimal_{$jenis}");

        return ($nilai !== null && ctype_digit($nilai) && (int) $nilai > 0) ? (int) $nilai : null;
    }

    public static function lampiranKpAktif(): bool
    {
        return static::ambil('kp.pengumpulan_lampiran_aktif', '0') === '1';
    }
}
