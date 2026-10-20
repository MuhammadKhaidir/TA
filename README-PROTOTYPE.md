# Sistem Administrasi Tugas Akhir (TA) dan Kerja Praktik (KP)

Aplikasi Laravel 13 + Filament 4 + spatie/laravel-permission untuk mengelola
administrasi TA dan KP di lingkungan fakultas. Repositori ini awalnya
mengimplementasikan POS "Pendaftaran Ujian Akhir Program" dengan 7 role dan 16
langkah; sekarang disederhanakan menjadi **empat aktor** dan alur TA sampai SK.

## Empat aktor

| Role (`roles.name`) | Tanggung jawab | Tidak boleh |
|---|---|---|
| `mahasiswa` | Mengajukan TA/KP, unggah dokumen, memantau status, mencatat bimbingan, mengunduh SK, mengajukan surat KP | Melihat/mengubah data mahasiswa lain, mengubah status/prodi/dospem sendiri |
| `dospem` | Memeriksa pengajuan mahasiswa bimbingannya, menyetujui atau meminta revisi, memvalidasi bimbingan | Menerbitkan SK, melihat mahasiswa yang bukan bimbingannya |
| `operator_akademik` | Verifikasi administrasi, penetapan dospem, penerbitan SK TA (PDF), proses surat KP, arsip | Menilai substansi akademik; memproses prodi di luar cakupannya |
| `admin` | Akun & role, prodi, periode, persyaratan, template SK, jenis surat, struktur laporan, pengaturan, log aktivitas (`/admin`) | Menyetujui pengajuan atau menilai akademik |

Hak akses ditegakkan di backend: middleware `role:`, `PastikanAkunAktif`
(memutus akun nonaktif / tanpa role valid), policy (`PengajuanPolicy`,
`BimbinganPolicy`, `SuratPermohonanPolicy`), dan satu scope query
`Pengajuan::dapatDilihatOleh()` yang menjadi sumber tunggal aturan "siapa melihat
apa". Berkas dokumen/SK disimpan di disk privat (`local`) dan hanya diunduh lewat
route yang terotorisasi.

## Menu panel Admin (`/admin`)

- **Pengguna:** Pengguna (akun + 4 role + cakupan operator), Dosen, Mahasiswa
- **Master Data:** Program Studi, Periode Akademik
- **Tugas Akhir (TA):** Persyaratan Pengajuan TA, Template SK TA, Penomoran dan Penandatangan SK, Daftar Pengajuan TA (read-only), Riwayat SK TA Terbit (read-only)
- **Kerja Praktik (KP):** Persyaratan Pengajuan KP, Jenis Surat KP, Struktur Laporan KP, Lampiran Laporan KP, Pengumpulan Lampiran KP, Daftar Pengajuan KP (read-only)
- **Sistem:** Minimum Bimbingan, Log Aktivitas

Dashboard Admin menampilkan ringkasan TA, ringkasan KP, dan kesiapan konfigurasi SK.
Daftar pengajuan hanya untuk pemantauan: Admin tidak dapat menyetujui, mengubah, atau
membuka dokumen mahasiswa.

## Alur TA

1. Mahasiswa membuat draft (judul), mengunggah dokumen persyaratan, lalu **Kirim**.
2. Operator Akademik **memverifikasi administrasi** (atau mengembalikan dengan catatan).
3. Operator **menetapkan dosen pembimbing** (dosen harus aktif dan punya akun Dospem).
4. Dospem **menyetujui secara akademik** (atau meminta revisi dengan catatan).
5. Setelah administrasi terverifikasi + akademik disetujui + dospem ada, Operator
   **menerbitkan SK**: pratinjau, lalu PDF dibuat dari template, nomor dikunci,
   file dan metadata tersimpan, status menjadi *Selesai*, mahasiswa dapat mengunduh.

`status_administrasi` (Operator) dan `status_akademik` (Dospem) disimpan terpisah;
`status` gabungan (Draft, Menunggu Verifikasi, Perlu Revisi, Disetujui, Selesai)
selalu diturunkan oleh `PengajuanService`, bukan dari input pengguna. Semua
perubahan tercatat di `log_aktivitas` (siapa, kapan, apa, plus catatan revisi).

## KP (fondasi, prosedur menunggu keputusan kampus)

Pengajuan KP memakai tabel/alur yang sama (kolom `jenis`) dengan data tempat KP
dan kontak pembimbing lapangan (bukan akun login). Tersedia: permohonan surat KP
(jenis surat dikelola Admin; Operator mengunggah surat hasil), pencatatan
bimbingan + validasi Dospem (jumlah tercatat/divalidasi dihitung, minimum
dikonfigurasi), panduan struktur laporan, dan daftar lampiran + pengumpulan
(dibuka/ditutup Admin). Website **tidak** mengarang format surat KP.

## Yang harus diisi pihak kampus (panel Admin)

Penerbitan SK sengaja diblokir sampai ini lengkap, supaya tidak ada dokumen
resmi dengan data karangan: **template SK aktif**, **format nomor SK**, **kota**,
**nama dan jabatan penandatangan**. Isi `database/seeders/DatabaseSeeder.php`
(persyaratan, struktur laporan, jenis surat, template SK, nilai pengaturan) hanyalah
**contoh awal**. Tanda tangan digital/stempel tidak ada; template hanya HTML.

Database yang sudah berisi data tidak perlu di-reset untuk mendapatkan contoh konfigurasinya
(persyaratan TA/KP, struktur laporan, jenis surat, kerangka template SK):

    php artisan db:seed --class=KonfigurasiAwalSeeder

Seeder ini idempotent dan sengaja **tidak** mengisi format nomor, kota, maupun penandatangan SK.

## Menjalankan

Instalasi baru: `composer require dompdf/dompdf` (mesin PDF), lalu `SETUP.cmd`.
Upgrade database yang sudah berisi data: **jangan** `migrate:fresh`; jalankan
`php artisan migrate` (migrasi aman terhadap data, lihat di bawah).

Akun demo (password `password`) dibuat oleh seeder: `mahasiswa@ta.test`,
`dospem@ta.test`, `operator@ta.test` (semua prodi), `admin@ta.test`; juga
`operator.si@ta.test` (hanya prodi SI), `dospem2@ta.test`, `mahasiswa2@ta.test`,
`mahasiswa3@ta.test` (pengajuan TA pada tahap berbeda).

## Migrasi dan data lama

- `..._100000_add_prodi_periode_and_operator_scope`: tabel `prodis` (TI, SI, SK
  sebagai data awal), periode akademik, cakupan operator. Kolom teks `prodi` di
  mahasiswa/dosen di-backfill ke `prodi_id` (nama dicocokkan tanpa peduli huruf
  besar/kecil; nama yang belum dikenal otomatis dibuat sebagai prodi baru), lalu dihapus.
- `..._100100_consolidate_roles_to_four_actors`: `dosen_penguji` -> `dospem`;
  `sekdep_koor_prodi`, `penata`, `pengelola_layanan`, `pengadministrasi_perkantoran`
  -> `operator_akademik` (dengan `semua_prodi = true` agar cakupan tidak menyempit);
  role lain dihapus. Tidak dapat dibalik.
- `..._100200_create_pengajuan_and_administrasi_tables`: tabel baru.
- `..._100300_convert_konsultasis_to_bimbingans`: `konsultasis` -> `bimbingans`
  (baris lama tetap, jenis `ta`); foto bukti dipindah dari disk publik ke privat.
- Tabel lama `sidangs`, `sidang_pengujis`, `dokumens`, `sidang_riwayats`, dan kolom
  `mahasiswas.jumlah_konsultasi` **tidak dihapus** (tidak lagi dipakai aplikasi).
  Hapus lewat migrasi tersendiri setelah dipastikan tidak diperlukan.

## Pengujian

`php artisan test` (PHPUnit, SQLite in-memory): akses per peran, role lama,
akun nonaktif, otorisasi data lintas pengguna, cakupan prodi operator, alur TA
penuh sampai PDF SK, kegagalan PDF, penomoran, modul KP, dan panel Admin.
