<?php

namespace App\Models;

use App\Enums\TugasAdministrasi;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi key-value yang dikelola Admin (format nomor SK, penandatangan,
 * jumlah minimum bimbingan, dst). Nilai kosong berarti belum ditetapkan kampus.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturans';

    protected $fillable = ['kunci', 'nilai'];

    public const SK_WEBSITE = 'website';

    public const SK_OFFLINE = 'offline';

    /**
     * Label, petunjuk, dan (opsional) pilihan nilai tiap kunci; dipakai panel Admin.
     * Kunci `akses.*` dibangun dari enum TugasAdministrasi di bawah.
     */
    public const DAFTAR = [
        'sk.format_nomor' => ['Format nomor SK', 'Penanda: {urut}, {tahun}, {kode_prodi}, {bulan_romawi}. Contoh: {urut}/SK/{kode_prodi}/{tahun}'],
        'sk.kota' => ['Kota penerbitan SK', 'Tampil pada tanggal SK, misalnya nama kota kampus.'],
        'sk.penandatangan_nama' => ['Nama penandatangan SK', 'Diisi sesuai pejabat yang berwenang menurut kampus.'],
        'sk.penandatangan_jabatan' => ['Jabatan penandatangan SK', 'Diisi sesuai pejabat yang berwenang menurut kampus.'],
        'sk.mode' => ['Jalur penerbitan SK', 'website = SK dibuat dari template di website. offline = SK resmi terbit di luar sistem, Admin hanya mencatat nomor, tanggal, dan salinannya.', ['website' => 'Diterbitkan dari website', 'offline' => 'Dicatat dari SK resmi offline']],
        'bimbingan.minimal_ta' => ['Minimal bimbingan TA', 'Kosongkan bila tidak ada batas minimum. Dicek saat Dosen Pembimbing menyetujui pengajuan sidang.'],
        'bimbingan.minimal_kp' => ['Minimal bimbingan KP', 'Kosongkan sampai kampus menetapkan jumlah minimum.'],
        'kp.pengumpulan_lampiran_aktif' => ['Pengumpulan lampiran laporan KP', '1 = mahasiswa dapat mengunggah lampiran, 0 = hanya melihat daftar.'],
        'ta.pembimbing_kedua_wajib' => ['Pembimbing 2 wajib', '1 = Pembimbing 2 wajib ditetapkan dan ikut menyetujui, 0 = boleh hanya Pembimbing 1.', ['1' => 'Wajib', '0' => 'Tidak wajib']],
        'kartu.butuh_sk' => ['Kartu konsultasi terkunci sebelum SK terbit', '1 = kartu konsultasi PDF baru dapat diunduh setelah SK TA terbit, 0 = selalu dapat diunduh.', ['1' => 'Terkunci sampai SK terbit', '0' => 'Tidak dikunci']],
        'sidang.durasi_menit' => ['Durasi sidang (menit)', 'Dipakai untuk memeriksa benturan jadwal ruang dan dosen.'],
        'sidang.minimal_penguji' => ['Jumlah minimal penguji', 'Jumlah penguji yang harus ditetapkan saat menjadwalkan sidang.'],
    ];

    /** Kelompok yang tampil sebagai menu terfilter pada panel Admin: kunci awalan => label. */
    public const KELOMPOK = [
        'sk' => 'SK TA',
        'bimbingan' => 'Bimbingan',
        'kp' => 'KP',
        'ta' => 'TA',
        'kartu' => 'Kartu konsultasi',
        'sidang' => 'Sidang',
        'akses' => 'Hak akses tugas',
    ];

    /** @return array{0: string, 1: string, 2?: array<string, string>} */
    public static function keterangan(string $kunci): array
    {
        foreach (TugasAdministrasi::cases() as $tugas) {
            if ($tugas->kunciPengaturan() === $kunci) {
                return [
                    'Pelaku: '.$tugas->label(),
                    'Peran yang mengerjakan tugas ini [K-01, belum dikonfirmasi fakultas]. Bawaan: '.$tugas->bawaan()->label().'.',
                    TugasAdministrasi::peranDiizinkan(),
                ];
            }
        }

        return self::DAFTAR[$kunci] ?? [$kunci, ''];
    }

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

    /** Jalur penerbitan SK aktif; nilai tak dikenal jatuh ke 'website' (perilaku sebelum revisi). */
    public static function skMode(): string
    {
        return static::ambil('sk.mode') === self::SK_OFFLINE ? self::SK_OFFLINE : self::SK_WEBSITE;
    }

    public static function pembimbingKeduaWajib(): bool
    {
        return static::ambil('ta.pembimbing_kedua_wajib', '1') !== '0';
    }

    public static function kartuButuhSk(): bool
    {
        return static::ambil('kartu.butuh_sk', '1') !== '0';
    }

    public static function durasiSidangMenit(): int
    {
        $nilai = static::ambil('sidang.durasi_menit');

        return ($nilai !== null && ctype_digit($nilai) && (int) $nilai > 0) ? (int) $nilai : 90;
    }

    public static function minimalPenguji(): int
    {
        $nilai = static::ambil('sidang.minimal_penguji');

        return ($nilai !== null && ctype_digit($nilai) && (int) $nilai > 0) ? (int) $nilai : 1;
    }
}
