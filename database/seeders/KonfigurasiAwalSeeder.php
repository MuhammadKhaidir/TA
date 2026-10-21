<?php

namespace Database\Seeders;

use App\Models\JenisSurat;
use App\Models\Persyaratan;
use App\Models\StrukturLaporan;
use App\Models\TemplateSk;
use Illuminate\Database\Seeder;

/**
 * Contoh awal konfigurasi administrasi TA/KP (persyaratan, struktur laporan, jenis surat,
 * kerangka template SK). Aman dijalankan di database yang sudah berisi data:
 *
 *     php artisan db:seed --class=KonfigurasiAwalSeeder
 *
 * Hanya menambah yang belum ada (tidak menimpa hasil edit Admin). Seeder ini SENGAJA tidak
 * mengisi format nomor SK, kota, dan penandatangan: itu data resmi kampus yang harus diisi
 * Admin, dan penerbitan SK tetap diblokir sampai semuanya terisi.
 */
class KonfigurasiAwalSeeder extends Seeder
{
    public function run(): void
    {
        Persyaratan::firstOrCreate(
            ['jenis' => 'ta_pengajuan', 'nama' => 'Proposal Tugas Akhir'],
            ['deskripsi' => 'Contoh awal: berkas proposal TA (PDF). Sesuaikan dengan ketentuan kampus.', 'wajib' => true, 'urutan' => 1],
        );
        Persyaratan::firstOrCreate(
            ['jenis' => 'kp_pengajuan', 'nama' => 'Dokumen pengajuan KP'],
            ['deskripsi' => 'Contoh awal. Prosedur KP masih akan ditetapkan kampus.', 'wajib' => false, 'urutan' => 1],
        );

        // Revisi PDF: dokumen Kumpulan A pada surat pengantar TA (8 dokumen). NAMA DI BAWAH HANYA PENGISI TEMPAT:
        // ganti dengan nama dokumen sesuai PDF lewat menu "Dokumen Surat Pengantar TA" di panel Admin.
        foreach (range(1, 8) as $i) {
            Persyaratan::firstOrCreate(
                ['jenis' => 'ta_surat_pengantar', 'nama' => "Dokumen Kumpulan A nomor {$i} (ganti dengan nama dokumen sesuai ketentuan)"],
                ['deskripsi' => 'Contoh awal. Nama dan keterangan dokumen belum ditetapkan di sini; Admin menyesuaikannya.', 'wajib' => true, 'urutan' => $i],
            );
        }

        // Dokumen pengajuan sidang TA (4 dokumen). Dua dokumen pertama ditautkan otomatis oleh sistem; dua lainnya
        // diunggah mahasiswa (nama pengisi tempat, sesuaikan dengan ketentuan).
        Persyaratan::firstOrCreate(
            ['jenis' => 'ta_sidang', 'nama' => 'SK TA yang sudah terbit'],
            ['deskripsi' => 'Ditautkan otomatis dari SK TA mahasiswa.', 'wajib' => true, 'urutan' => 1, 'tautan_otomatis' => Persyaratan::TAUTAN_SK_TA],
        );
        Persyaratan::firstOrCreate(
            ['jenis' => 'ta_sidang', 'nama' => 'Kartu konsultasi'],
            ['deskripsi' => 'Ditautkan otomatis: PDF disusun dari catatan bimbingan.', 'wajib' => true, 'urutan' => 2, 'tautan_otomatis' => Persyaratan::TAUTAN_KARTU_KONSULTASI],
        );
        foreach ([3, 4] as $i) {
            Persyaratan::firstOrCreate(
                ['jenis' => 'ta_sidang', 'nama' => "Dokumen sidang nomor {$i} (ganti dengan nama dokumen sesuai ketentuan)"],
                ['deskripsi' => 'Contoh awal. Dokumen yang diunggah mahasiswa; Admin menyesuaikan namanya.', 'wajib' => true, 'urutan' => $i],
            );
        }

        foreach ([
            ['Surat pengantar atau persetujuan tempat KP', 'Saat laporan diserahkan'],
            ['Surat keterangan selesai KP', 'Setelah KP selesai (jika diwajibkan)'],
            ['Logbook atau jurnal kegiatan', 'Saat laporan diserahkan'],
            ['Dokumentasi kegiatan', 'Saat laporan diserahkan'],
            ['Lembar bimbingan', 'Saat laporan diserahkan'],
            ['Lembar penilaian atau pengesahan', 'Sesuai pedoman kampus'],
        ] as $i => [$nama, $waktu]) {
            Persyaratan::firstOrCreate(
                ['jenis' => 'kp_lampiran', 'nama' => $nama],
                ['deskripsi' => 'Contoh awal, sesuaikan dengan pedoman laporan kampus.', 'wajib' => false, 'waktu_penyerahan' => $waktu, 'urutan' => $i + 1],
            );
        }

        foreach ([
            'Halaman sampul', 'Lembar pengesahan', 'Kata pengantar', 'Daftar isi', 'BAB I Pendahuluan',
            'BAB II Gambaran umum perusahaan atau instansi', 'BAB III Pelaksanaan kegiatan KP',
            'BAB IV Hasil dan pembahasan', 'BAB V Penutup', 'Daftar pustaka', 'Lampiran',
        ] as $i => $judul) {
            StrukturLaporan::firstOrCreate(['judul' => $judul], ['urutan' => $i + 1, 'deskripsi' => 'Contoh awal; ikuti pedoman laporan resmi kampus.']);
        }

        foreach ([
            ['surat-pengantar-kp', 'Surat pengantar KP'],
            ['surat-permohonan-kp', 'Surat permohonan KP kepada perusahaan/instansi'],
            ['surat-keterangan-selesai-kp', 'Surat keterangan selesai KP'],
        ] as [$kode, $nama]) {
            JenisSurat::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'deskripsi' => 'Contoh awal; format surat mengikuti standar kampus.', 'aktif' => true]);
        }

        if (! TemplateSk::query()->exists()) {
            TemplateSk::create(['nama' => 'Kerangka SK Pembimbing TA (contoh)', 'aktif' => true, 'isi' => self::TEMPLATE_CONTOH]);
        }
    }

    private const TEMPLATE_CONTOH = <<<'HTML'
<h3 class="tengah">KEPUTUSAN PENETAPAN DOSEN PEMBIMBING TUGAS AKHIR</h3>
<p class="tengah">Nomor: {{nomor_sk}}</p>
<p>Menetapkan dosen pembimbing Tugas Akhir bagi mahasiswa berikut pada periode akademik {{periode_akademik}}:</p>
<table>
  <tr><td>Nama</td><td>:</td><td>{{nama_mahasiswa}}</td></tr>
  <tr><td>NIM</td><td>:</td><td>{{nim}}</td></tr>
  <tr><td>Program Studi</td><td>:</td><td>{{prodi}}</td></tr>
  <tr><td>Judul</td><td>:</td><td>{{judul}}</td></tr>
  <tr><td>Dosen Pembimbing</td><td>:</td><td>{{dosen_pembimbing}} (NIP {{nip_pembimbing}})</td></tr>
</table>
<p>Keputusan ini berlaku sejak tanggal ditetapkan.</p>
<p class="kanan">{{kota}}, {{tanggal}}<br>{{penandatangan_jabatan}}<br><br><br><br>{{penandatangan_nama}}</p>
HTML;
}
