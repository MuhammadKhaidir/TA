# Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA

Aplikasi web untuk mengelola pengajuan, pemeriksaan, dan pengarsipan berkas Kerja Praktik (KP) dan Tugas Akhir (TA) di Fakultas Ilmu Komputer, Universitas Sriwijaya.

Dibangun dengan Laravel 13, Blade, Tailwind CSS v4 (lewat Vite), dan MySQL.

## Peran pengguna

| Peran | Menu |
|---|---|
| Mahasiswa | Dashboard, Pengajuan KP, Pengajuan TA, Status Pengajuan, Berkas Saya |
| Dosen | Dashboard, Pemeriksaan Berkas, Mahasiswa Bimbingan, Riwayat Pemeriksaan |
| Operator Akademik | Dashboard, Verifikasi Administrasi, Pengelolaan KP/TA, Dokumen Resmi, Arsip Berkas |
| Admin | Dashboard, Manajemen Pengguna, Data Master, Pengaturan Persyaratan, Log Aktivitas |

Semua peran juga punya Profil dan Logout. Hak akses dijaga di backend (middleware peran + Policy), bukan hanya dengan menyembunyikan menu.

## Kebutuhan

- PHP 8.3 atau lebih baru, dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `ctype`, `tokenizer`, `xml`
- Composer 2
- Node.js 20.19+ atau 22.12+ (untuk Vite 8) dan npm
- MySQL 8 atau MariaDB 10.6+

## Cara menjalankan

Jalankan dari folder proyek (terminal VS Code).

```bash
# 1. Pasang dependensi PHP
composer install

# 2. Salin konfigurasi (Windows: copy .env.example .env)
cp .env.example .env
php artisan key:generate

# 3. Buat database kosong di MySQL, mis. lewat phpMyAdmin atau:
#    CREATE DATABASE arsip_kp_ta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    lalu sesuaikan DB_USERNAME dan DB_PASSWORD di file .env

# 4. Buat tabel dan isi data awal
php artisan migrate --seed

# 5. Pasang dan bangun aset Tailwind
npm install
npm run build        # atau: npm run dev (sambil mengembangkan)

# 6. Jalankan
php artisan serve
```

Buka http://localhost:8000.

Jika muncul error `Vite manifest not found`, langkah 5 belum dijalankan (`npm run build`).

### Batas ukuran unggahan

Batas ukuran per dokumen diatur admin di menu Pengaturan Persyaratan (contoh seeder: 5 MB). PHP juga punya batas sendiri. Jika dokumen berukuran besar gagal diunggah, naikkan di `php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

Lalu restart server PHP.

## Akun contoh

Dibuat oleh `php artisan migrate --seed`. Kata sandi semuanya `password`.

| Peran | Email | 
|---|---|
| Admin | admin@fasilkom.test |
| Operator | operator@fasilkom.test |
| Dosen | dosen1@fasilkom.test, dosen2@fasilkom.test |
| Mahasiswa | mahasiswa1@fasilkom.test, mahasiswa2@fasilkom.test |

Login juga bisa memakai nomor induk (NIM/NIP). **Ganti kata sandi atau hapus akun ini sebelum aplikasi dipakai sungguhan.**

Seeder memakai `firstOrCreate`, jadi aman dijalankan ulang dan tidak menimpa data yang sudah diubah admin. Program studi dan persyaratan contoh di seeder dapat diubah dari menu Admin.

## Alur sistem

1. Mahasiswa membuat pengajuan KP/TA (judul, instansi; untuk TA ditambah dosen pembimbing pilihan) dan mengunggah dokumen persyaratan. Pengajuan masih berstatus Draft. Pembimbing KP tidak dipilih mahasiswa, lihat bagian Dosen pembimbing.
2. Setelah semua dokumen wajib lengkap, mahasiswa mengirim pengajuan (Diajukan).
3. Operator memeriksa dokumen yang menjadi tugas operator. Dokumen bisa Diterima atau Perlu Revisi (catatan wajib). Ada revisi: status Revisi Administrasi, mahasiswa memperbaiki hanya dokumen yang diminta, lalu mengirim ulang.
4. Jika semua diterima, pengajuan Menunggu Dosen. Dosen pembimbing memeriksa dokumen akademik dengan cara yang sama (Revisi Akademik atau Disetujui).
5. Setelah Disetujui, operator mengunggah dokumen resmi (surat pengantar KP, SK KP, SK TA, dan lain-lain) lalu mengarsipkan pengajuan (Diarsipkan).
6. Berkas final tersimpan di Arsip Berkas dan dapat dibuka oleh pihak yang berwenang.

```
draft -> diajukan -> menunggu_dosen -> disetujui -> diarsipkan
              |  ^        |  ^
              v  |        v  |
     revisi_administrasi   revisi_akademik
```

Setiap perubahan status disimpan di tabel `riwayat_status` (siapa, kapan, catatan) dan tampil sebagai riwayat di halaman detail. Tindakan penting juga masuk ke Log Aktivitas.

Dokumen yang diunggah ulang disimpan sebagai versi baru; versi lama tetap ada sebagai jejak audit.

## Dosen pembimbing

Pembimbing KP dan TA berasal dari sumber yang berbeda.

| | KP | TA |
|---|---|---|
| Siapa yang menentukan | Operator akademik, dari daftar dosen di database | Mahasiswa memilih saat membuat pengajuan |
| Persetujuan dosen | Tidak perlu, langsung berlaku | Dosen pilihan menerima atau menolak (alasan wajib bila menolak) |
| Bila ditolak | - | Operator menetapkan dosen pengganti |

Alur TA: mahasiswa memilih dosen (status *Menunggu konfirmasi*), mengirim pengajuan, lalu permintaan muncul di halaman **Mahasiswa Bimbingan** milik dosen itu dan di kartu pada dashboard dosen. Dosen menerima (*Diterima*) atau menolak (*Ditolak*). Setelah ditolak, pengajuan masuk daftar **Perlu Penetapan Dosen Pembimbing** di dashboard operator dan dapat difilter di menu Pengelolaan KP/TA (filter Pembimbing: Perlu penetapan).

Penetapan oleh operator ada di halaman Kelola pada pengajuan. Daftar dosen diurutkan dari **bimbingan aktif paling sedikit** (pengajuan yang sudah diarsipkan tidak dihitung) dan dosen dengan bimbingan paling sedikit sudah terpilih. Dosen yang menolak pengajuan itu tidak dapat dipilih lagi. Catatan wajib diisi bila operator menggantikan dosen yang masih menunggu konfirmasi atau yang sudah menerima.

Hal lain yang perlu diketahui:

- Dosen baru berwenang membuka dokumen dan memeriksa berkas setelah menjadi pembimbing yang sah (diterima atau ditetapkan). Dosen yang baru dipilih mahasiswa hanya melihat ringkasan permintaan.
- Mahasiswa dapat mengganti dosen pilihan TA hanya selama pengajuan masih Draft.
- Seluruh pemilihan, penerimaan, penolakan, dan penetapan tersimpan di riwayat dosen pembimbing beserta pelaku dan catatannya, tampil di halaman detail mahasiswa dan operator.
- Dashboard mahasiswa menampilkan pembimbing KP dan TA beserta statusnya. Data diambil dari tabel yang sama dengan halaman lain.
- Yang menetapkan pembimbing adalah operator, bukan admin. Bila di fakultas Anda berbeda, ubah ability `kelola` di `app/Policies/PengajuanPolicy.php` dan route `operator.pengajuan.pembimbing`.

## Keamanan berkas

- Berkas disimpan di `storage/app/private`, tidak punya URL publik.
- Berkas hanya bisa dibuka lewat `/berkas/...` setelah dicek Policy: pemilik pengajuan, dosen pembimbing, dan operator. Admin tidak dapat membuka isi berkas pengajuan.
- Format dan ukuran berkas divalidasi dari pengaturan persyaratan (whitelist pdf, doc, docx, jpg, jpeg, png), nama file di disk diacak, dan respons membawa header `X-Content-Type-Options: nosniff`.
- Pengguna yang dinonaktifkan admin langsung dikeluarkan dari sesi berjalan.

## Satu sumber data

Tidak ada data statis atau salinan. Halaman profil mahasiswa, daftar bimbingan dosen, antrean operator, dan halaman admin membaca tabel yang sama lewat relasi Eloquent. Contoh: admin mengubah nama, NIM, program studi, atau angkatan mahasiswa, atau mengganti nama program studi, maka halaman lain langsung menampilkan nilai baru setelah dimuat ulang. Persyaratan dokumen yang diubah admin langsung dipakai pada form dan validasi unggahan mahasiswa.

Pembatasan integritas: pengguna, program studi, dan persyaratan yang sudah dipakai data lain tidak bisa dihapus (nonaktifkan saja). Admin tidak dapat menurunkan peran atau menonaktifkan akunnya sendiri.

## Struktur kode

```
app/Enums            Role, JenisPengajuan, StatusPengajuan, StatusDokumen
app/Models           Model dan relasi Eloquent
app/Services         PengajuanWorkflow: seluruh perpindahan status ada di sini
app/Policies         Hak akses per pengajuan, dokumen, dan dokumen resmi
app/Http/Middleware  EnsureRole, EnsureUserActive
app/Http/Controllers Dipisah per peran: Mahasiswa, Dosen, Operator, Admin
app/Support          Menu per peran, pencatat log aktivitas
database/migrations  Skema (migrasi tambahan, tidak mengubah migrasi bawaan Laravel)
database/seeders     Program studi, persyaratan, dan akun contoh
resources/views      Blade: layouts, partials, components, halaman per peran
lang/id              Terjemahan validasi dan teks antarmuka
tests/Feature        Uji otomatis
```

## Menjalankan uji otomatis

```bash
php artisan test
```

Uji memakai SQLite in-memory (butuh ekstensi `pdo_sqlite`), jadi tidak menyentuh database MySQL Anda.

Hasil terakhir di lingkungan pengembangan: **52 uji, 1040 assertion, semuanya lulus.** Cakupannya:

- Login (email atau nomor induk), logout, akun nonaktif, pengalihan dashboard per peran, pencatatan log login.
- Matriks akses: setiap peran hanya dapat membuka halamannya sendiri (200) dan ditolak (403) di halaman peran lain, termasuk aksi tulis lintas peran dan ID yang tidak ada.
- Alur KP lengkap dari draft sampai arsip, termasuk validasi unggahan (format dan ukuran), revisi administrasi dan akademik, versi dokumen, urutan riwayat status, dan penguncian setelah diarsipkan.
- Akses berkas privat: pemilik, pembimbing, dan operator dapat membuka; mahasiswa lain, dosen lain, admin, dan tamu ditolak; tidak ada URL publik.
- Sinkronisasi data setelah admin mengubah mahasiswa, dosen, program studi, dan persyaratan.
- CRUD admin (pengguna, program studi, persyaratan), validasi, perlindungan data yang dipakai, log aktivitas, profil, dan ganti kata sandi.
- Alur dosen pembimbing: KP ditetapkan operator, TA dipilih mahasiswa lalu diterima atau ditolak dosen, penetapan pengganti dengan bimbingan paling sedikit, hak akses, dan dashboard mahasiswa.
- Render semua halaman untuk setiap peran pada setiap status pengajuan dan status pembimbing.
- Migrasi (naik dan rollback) serta seeder, termasuk aman dijalankan ulang.

## Yang belum diuji

Hal berikut belum bisa diuji di lingkungan pengembangan dan sebaiknya Anda cek sendiri saat pertama menjalankan:

- **MySQL.** Seluruh pengujian memakai SQLite. Migrasi sudah ditinjau terhadap batasan MySQL (panjang indeks, urutan kunci asing, `down()`), tetapi belum dijalankan di MySQL.
- **Build Tailwind (`npm install && npm run build`).** Registry npm tidak terjangkau di lingkungan pengembangan, jadi build dan tampilan visual di browser belum dilihat langsung. Uji otomatis hanya memeriksa HTML yang dihasilkan, bukan hasil CSS.
- **Unggahan berkas nyata dari browser** (uji memakai berkas palsu), dan tampilan di perangkat seluler sungguhan.

## Keterbatasan saat ini

- Dokumen resmi (surat pengantar, SK) diunggah operator sebagai berkas. Aplikasi belum membuat dokumen dari templat.
- Data Master baru berisi Program Studi.
- Belum ada notifikasi email, pencarian lintas arsip tingkat lanjut, atau ekspor laporan.
- Satu pengajuan hanya memiliki satu dosen pembimbing (belum ada pembimbing kedua) dan belum ada batas kuota bimbingan per dosen.

## Menempelkan ke proyek Laravel yang sudah ada

Kode ini ditulis untuk Laravel 13 dan Tailwind v4. Jika Anda sudah punya proyek Laravel 13 dengan skeleton standar, salin folder `app`, `database`, `lang`, `resources`, `routes`, `tests`, serta file `bootstrap/app.php`, `config/app.php`, `config/filesystems.php`, `vite.config.js`, `phpunit.xml`, dan `.env.example` (cocokkan isinya dengan milik Anda, jangan menimpa begitu saja jika sudah ada perubahan). Migrasi bersifat tambahan: tabel `users` ditambah kolom, bukan dibuat ulang. Setelah itu jalankan `php artisan migrate --seed`.
