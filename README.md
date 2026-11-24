# Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA

Aplikasi web untuk mengelola pengajuan, pemeriksaan, dan pengarsipan berkas Kerja Praktik (KP) dan Tugas Akhir (TA) di Fakultas Ilmu Komputer, Universitas Sriwijaya.

Dibangun dengan Laravel 13, Blade, Tailwind CSS v4 (melalui Vite), dan SQLite (MySQL tetap dapat digunakan).

## Peran pengguna

| Peran | Menu |
|---|---|
| Mahasiswa | Dashboard, Pengajuan KP, Pengajuan TA, Status Pengajuan, Berkas Saya |
| Dosen | Dashboard, Pemeriksaan Berkas, Mahasiswa Bimbingan, Riwayat Pemeriksaan |
| Admin | Dashboard, Verifikasi Administrasi, Pengelolaan KP/TA, Dokumen Resmi, Arsip Berkas, Manajemen Pengguna, Data Master, Pengaturan Persyaratan |

Admin memegang seluruh fungsi administrasi akademik (verifikasi, penetapan dosen pembimbing, dokumen resmi, arsip) sekaligus pengelolaan sistem. Peran operator akademik telah dihapus dan digabungkan ke dalam peran admin.

Semua peran juga memiliki menu Profil dan Logout. Hak akses diterapkan pada sisi server (middleware peran dan Policy), bukan sekadar dengan menyembunyikan menu.

## Kebutuhan

- PHP 8.3 atau lebih baru, dengan ekstensi `pdo_sqlite`, `mbstring`, `fileinfo`, `openssl`, `ctype`, `tokenizer`, `xml`
- Composer 2
- Node.js 20.19+ atau 22.12+ (untuk Vite 8) dan npm
- Opsional: MySQL 8 atau MariaDB 10.6+ (hanya bila tidak memakai SQLite; tambahkan ekstensi `pdo_mysql`)

## Cara menjalankan

Jalankan dari folder proyek (terminal VS Code).

```bash
# 1. Pasang dependensi PHP
composer install

# 2. Salin konfigurasi (Windows: copy .env.example .env)
cp .env.example .env
php artisan key:generate

# 3. Database: tidak perlu membuat database baru. Berkas database/database.sqlite telah berisi tabel
#    dan data awal (dapat dibuka langsung di DB Browser for SQLite). Pastikan .env menggunakan
#    DB_CONNECTION=sqlite. Apabila Anda telah memiliki .env dari versi MySQL, berkas tersebut tidak
#    disertakan dalam git dan tidak berubah dengan sendirinya; baca bagian "Data tidak muncul di
#    DB Browser?" di bawah.
#    Apabila menggunakan MySQL, lihat bagian "Berkas SQL database".

# 5. Pasang dan bangun aset Tailwind
npm install
npm run build        # atau: npm run dev (sambil mengembangkan)

# 6. Jalankan
php artisan serve
```

Buka http://localhost:8000.

Apabila muncul pesan kesalahan `Vite manifest not found`, berarti langkah 5 belum dijalankan (`npm run build`).

### Batas ukuran unggahan

Batas ukuran per dokumen diatur admin di menu Pengaturan Persyaratan (contoh seeder: 5 MB). PHP juga memiliki batas tersendiri. Apabila dokumen berukuran besar gagal diunggah, naikkan nilainya di `php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

Kemudian mulai ulang server PHP.

## Akun contoh

Akun dibuat melalui perintah `php artisan migrate --seed`. Seluruh akun menggunakan kata sandi `password`.

| Peran | Email | 
|---|---|
| Admin | admin@fasilkom.test |
| Dosen | dosen1@fasilkom.test, dosen2@fasilkom.test |
| Mahasiswa | mahasiswa1@fasilkom.test, mahasiswa2@fasilkom.test, mahasiswa3@fasilkom.test |

Apabila database Anda dibuat sebelum peran operator digabungkan ke admin, jalankan `php artisan migrate`. Migrasi `gabungkan_operator_ke_admin` mengubah akun operator lama menjadi admin (akun, kata sandi, dan riwayat kerjanya tidak berubah).

Proses masuk (login) juga dapat menggunakan nomor induk (NIM/NIP). **Ganti kata sandi atau hapus akun ini sebelum aplikasi digunakan di lingkungan produksi.**

## Pendaftaran mahasiswa

Mahasiswa dapat membuat akun secara mandiri melalui halaman `/daftar` (tautan "Daftar" tersedia di halaman login). Data yang diisi meliputi nama, email, NIM (hanya angka), angkatan, program studi (hanya program studi aktif), dan kata sandi minimal 8 karakter. Akun yang berhasil dibuat selalu berperan sebagai mahasiswa dan langsung aktif, kemudian pendaftar otomatis diarahkan ke dashboard. Email disimpan dalam huruf kecil, dan email atau NIM yang sudah terdaftar akan ditolak.

Akun dosen dan admin tidak dapat dibuat melalui halaman ini; akun tersebut dibuat oleh admin di Manajemen Pengguna. Akun mahasiswa hasil pendaftaran ditampilkan di Manajemen Pengguna dan dapat dinonaktifkan oleh admin melalui menu Ubah.

Seeder menggunakan `firstOrCreate`, sehingga aman dijalankan ulang dan tidak menimpa data yang telah diubah oleh admin. Program studi dan persyaratan contoh pada seeder dapat diubah melalui menu Admin.

## Alur sistem

1. Mahasiswa membuat pengajuan KP/TA (judul, instansi; untuk TA ditambah dosen pembimbing pilihan) dan mengunggah dokumen persyaratan. Pengajuan masih berstatus Draft. Pembimbing KP tidak dipilih oleh mahasiswa; lihat bagian Dosen pembimbing.
2. Setelah seluruh dokumen wajib lengkap, mahasiswa mengirimkan pengajuan (berstatus Diajukan).
3. Admin memeriksa dokumen yang menjadi tanggung jawab admin. Dokumen dapat berstatus Diterima atau Perlu Revisi (catatan wajib diisi). Apabila terdapat revisi, status pengajuan menjadi Revisi Administrasi; mahasiswa memperbaiki dokumen yang diminta saja, kemudian mengirimkannya kembali.
4. Apabila seluruh dokumen diterima, pengajuan berstatus Menunggu Dosen. Dosen pembimbing memeriksa dokumen akademik dengan cara yang sama (hasilnya Revisi Akademik atau Disetujui).
5. Setelah Disetujui, admin mengunggah dokumen resmi (surat pengantar KP, SK KP, dan lain-lain) kemudian mengarsipkan pengajuan (Diarsipkan). Untuk TA terdapat alur surat tersendiri; lihat bagian Alur surat Tugas Akhir.
6. Berkas final tersimpan di Arsip Berkas dan dapat dibuka oleh pihak yang berwenang.

```
draft -> diajukan -> menunggu_dosen -> disetujui -> diarsipkan
              |  ^        |  ^
              v  |        v  |
     revisi_administrasi   revisi_akademik
```

Setiap perubahan status disimpan di tabel `riwayat_status` (siapa, kapan, catatan) dan ditampilkan sebagai riwayat pada halaman detail.

Dokumen yang diunggah ulang disimpan sebagai versi baru; versi lama tetap ada sebagai jejak audit.

## Database SQLite (yang dipakai aplikasi)

Aplikasi menggunakan berkas `database/database.sqlite` yang telah berisi struktur 19 tabel dan data awal (3 program studi, 10 persyaratan, 6 akun contoh dengan kata sandi `password`). Berkas tersebut dapat dibuka di DB Browser for SQLite (File > Open Database). Tutup DB Browser atau pilih Write Changes sebelum menjalankan aplikasi agar database tidak terkunci.

Untuk mengembalikan ke kondisi awal (seluruh data yang telah ditambahkan akan hilang):

```
git checkout -- database/database.sqlite
```

Atau bangun ulang dari awal melalui Laravel: kosongkan isi berkas tersebut, kemudian jalankan `php artisan migrate:fresh --seed`. Cadangan dalam bentuk teks tersedia di `database/sql/arsip_kp_ta.sqlite.sql` (impor melalui DB Browser: File > Import > Database from SQL file, atau `sqlite3 database/database.sqlite < database/sql/arsip_kp_ta.sqlite.sql` pada berkas yang kosong).

### Data tidak muncul di DB Browser?

Gejala: pengajuan dan dokumen terlihat di aplikasi (misalnya di dashboard admin), tetapi tabel `pengajuan` atau `dokumen_pengajuan` di DB Browser kosong. Aplikasi hanya menulis ke database yang ditunjuk oleh `.env`, dan `.env` tidak disertakan dalam git (lihat `.gitignore`). Versi lama proyek ini menggunakan MySQL, sehingga `.env` yang dibuat pada saat itu tidak berubah dengan sendirinya meskipun `.env.example` sudah menggunakan SQLite.

Penyebab yang paling sering ialah `DB_CONNECTION` sudah diganti ke `sqlite`, tetapi baris `DB_DATABASE=arsip_kp_ta` masih tertinggal. Pada SQLite, nilai tersebut dibaca sebagai nama berkas database, sehingga Laravel menggunakan berkas bernama `arsip_kp_ta` di folder proyek, bukan `database/database.sqlite`. Berkas itu dapat terbentuk dengan sendirinya: `php artisan migrate` menawarkan untuk membuatnya ("Would you like to create it?"), kemudian mengisinya dengan tabel dan data awal. Cirinya, terdapat berkas `arsip_kp_ta` tanpa ekstensi di folder proyek (terlihat sebagai untracked pada `git status`). Penyebab lain: konfigurasi lama masih tersimpan (`config:cache`), aplikasi dijalankan dari folder salinan lain, atau tampilan DB Browser belum disegarkan (refresh).

Periksa database yang sedang digunakan aplikasi dari folder proyek:

```
php artisan db:show
```

Hasilnya harus menunjukkan SQLite dengan lokasi `database/database.sqlite` pada folder yang sama dengan yang dibuka di DB Browser. Apabila tidak demikian:

1. Pada `.env`, pastikan `DB_CONNECTION=sqlite`, kemudian hapus atau beri tanda `#` pada `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
2. Jalankan `php artisan config:clear`, kemudian hentikan dan jalankan ulang `php artisan serve`.
3. Pada DB Browser, pilih tombol Refresh pada tab Browse Data (DB Browser tidak memperbarui tampilan secara otomatis).

Data yang telah tersimpan di berkas `arsip_kp_ta` atau di MySQL tidak berpindah secara otomatis ke `database/database.sqlite`. Untuk berkas `arsip_kp_ta`: hentikan `php artisan serve`, tutup DB Browser, kemudian salin berkas tersebut untuk menimpa `database/database.sqlite` (Windows: `copy /Y arsip_kp_ta database\database.sqlite`). Hapus `arsip_kp_ta` setelah dipastikan datanya telah berada di lokasi yang baru. Untuk MySQL: lakukan pendaftaran akun uji dan pengajuan ulang.

## Berkas SQL database (MySQL)

Seluruh struktur database didefinisikan pada migrasi (`database/migrations`, berkas PHP, yaitu cara Laravel membuat tabel). Bagi yang membutuhkan berkas `.sql` biasa, tersedia `database/sql/arsip_kp_ta.sql`: struktur 19 tabel beserta data awal (3 program studi, 10 persyaratan, 5 akun contoh dengan kata sandi `password`).

```
mysql -u root -p < database/sql/arsip_kp_ta.sql
```

Atau melalui phpMyAdmin: buka tab Import, kemudian pilih berkas tersebut. Perhatian: berkas ini menghapus lalu membuat ulang seluruh tabel aplikasi pada database `arsip_kp_ta`, sehingga data yang sudah ada pada tabel tersebut akan hilang. Tabel `migrations` turut terisi, sehingga `php artisan migrate` setelahnya melaporkan "Nothing to migrate".

Berkas ini dibuat dari migrasi dan seeder, serta dijaga oleh `tests/Feature/BerkasSqlTest.php`: pengujian gagal apabila terdapat tabel, kolom, atau migrasi yang belum ada pada berkas SQL. Setelah menambahkan migrasi, buat ulang berkas tersebut dari database Anda:

```
mysqldump -u root -p arsip_kp_ta > database/sql/arsip_kp_ta.sql
```

## Alur surat Tugas Akhir

Khusus TA. Alur KP tidak berubah.

1. Mahasiswa mengirim pengajuan TA beserta dokumennya, kemudian admin memverifikasinya (status Menunggu Dosen).
2. Admin menerbitkan **surat pengantar** (nomor, tanggal, dan berkas PDF yang telah ditandatangani) melalui halaman Dokumen Resmi. Mahasiswa mengunduhnya dari halaman detail pengajuan.
3. Mahasiswa mengunggah **surat balasan** (PDF, JPG, atau PNG, maksimal 5 MB). Surat balasan dapat diunggah ulang selama belum diterima; versi lama tetap tersimpan sebagai jejak.
4. Admin memeriksa surat balasan: **diterima** atau **revisi** (catatan wajib diisi dan ditampilkan di halaman mahasiswa). Keputusan masih dapat diubah selama SK belum terbit.
5. **SK Pembimbing Tugas Akhir** hanya dapat diterbitkan apabila: surat pengantar telah terbit, surat balasan versi terbaru telah diterima, dosen pembimbing telah sah, dan pengajuan telah disetujui dosen. Aturan ini diterapkan pada sisi server (`Pengajuan::hambatanSk()`), bukan sekadar dengan menyembunyikan tombol.
6. Mahasiswa mengunduh SK, kemudian admin mengarsipkan pengajuan. Pengarsipan TA mensyaratkan SK telah terbit.

Surat pengantar dan SK disimpan sebagai dokumen resmi (jenis `surat_pengantar_ta` dan `sk_ta`), surat balasan pada tabel `surat_balasan`. Kode jenis `sk_ta` tidak berubah; hanya labelnya yang menjadi "SK Pembimbing Tugas Akhir". Pengajuan TA yang telah berstatus Disetujui sebelum fitur ini tersedia harus melalui langkah surat pengantar dan surat balasan terlebih dahulu sebelum SK dapat diterbitkan.

## Dosen pembimbing

Pembimbing KP dan TA berasal dari sumber yang berbeda.

| | KP | TA |
|---|---|---|
| Siapa yang menentukan | Admin, dari daftar dosen pada database | Mahasiswa memilih saat membuat pengajuan |
| Persetujuan dosen | Tidak diperlukan; langsung berlaku | Dosen pilihan menerima atau menolak (alasan wajib bila menolak) |
| Apabila ditolak | - | Admin menetapkan dosen pengganti |

Alur TA: mahasiswa memilih dosen (status *Menunggu konfirmasi*), mengirim pengajuan, kemudian permintaan ditampilkan pada halaman **Mahasiswa Bimbingan** milik dosen tersebut serta pada kartu di dashboard dosen. Dosen menerima (*Diterima*) atau menolak (*Ditolak*) permintaan itu. Setelah ditolak, pengajuan masuk ke daftar **Perlu Penetapan Dosen Pembimbing** di dashboard admin dan dapat disaring pada menu Pengelolaan KP/TA (filter Pembimbing: Perlu penetapan).

Penetapan oleh admin dilakukan pada halaman Kelola di setiap pengajuan. Daftar dosen diurutkan berdasarkan **bimbingan aktif paling sedikit** (pengajuan yang telah diarsipkan tidak dihitung), dan dosen dengan bimbingan paling sedikit terpilih secara otomatis. Dosen yang menolak pengajuan tersebut tidak dapat dipilih kembali. Catatan wajib diisi apabila admin menggantikan dosen yang masih menunggu konfirmasi atau yang telah menerima.

Hal lain yang perlu diketahui:

- Dosen baru berwenang membuka dokumen dan memeriksa berkas setelah menjadi pembimbing yang sah (diterima atau ditetapkan). Dosen yang baru dipilih mahasiswa hanya dapat melihat ringkasan permintaan.
- Mahasiswa dapat mengganti dosen pilihan TA hanya selama pengajuan masih Draft.
- Seluruh pemilihan, penerimaan, penolakan, dan penetapan tersimpan di riwayat dosen pembimbing beserta pelaku dan catatannya, dan ditampilkan pada halaman detail mahasiswa dan admin.
- Dashboard mahasiswa menampilkan pembimbing KP dan TA beserta statusnya. Data diambil dari tabel yang sama dengan halaman lain.
- Pembimbing ditetapkan oleh admin. Apabila ketentuan di fakultas Anda berbeda, ubah ability `kelola` pada `app/Policies/PengajuanPolicy.php` dan route `admin.pengajuan.pembimbing`.

## Keamanan berkas

- Berkas disimpan di `storage/app/private`, tidak memiliki URL publik.
- Berkas hanya dapat dibuka melalui `/berkas/...` setelah diperiksa oleh Policy: pemilik pengajuan, dosen pembimbing, dan admin. Mahasiswa lain, dosen lain, dan tamu ditolak.
- Format dan ukuran berkas divalidasi berdasarkan pengaturan persyaratan (daftar izin: pdf, doc, docx, jpg, jpeg, png), nama berkas pada disk diacak, dan respons menyertakan header `X-Content-Type-Options: nosniff`.
- Pengguna yang dinonaktifkan oleh admin langsung dikeluarkan dari sesi yang sedang berjalan.

## Satu sumber data

Tidak ada data statis atau salinan. Halaman profil mahasiswa, daftar bimbingan dosen, antrean kerja admin, dan halaman pengelolaan membaca tabel yang sama melalui relasi Eloquent. Contoh: apabila admin mengubah nama, NIM, program studi, atau angkatan mahasiswa, atau mengganti nama program studi, halaman lain langsung menampilkan nilai baru setelah dimuat ulang. Persyaratan dokumen yang diubah oleh admin langsung digunakan pada formulir dan validasi unggahan mahasiswa.

Pembatasan integritas: pengguna, program studi, dan persyaratan yang telah digunakan oleh data lain tidak dapat dihapus (cukup dinonaktifkan). Admin tidak dapat menurunkan peran atau menonaktifkan akunnya sendiri.

## Struktur kode

```
app/Enums            Role, JenisPengajuan, StatusPengajuan, StatusDokumen
app/Models           Model dan relasi Eloquent
app/Services         PengajuanWorkflow: seluruh perpindahan status dilakukan di sini
app/Policies         Hak akses per pengajuan, dokumen, dan dokumen resmi
app/Http/Middleware  EnsureRole, EnsureUserActive
app/Http/Controllers Dipisah per peran: Mahasiswa, Dosen, Admin
app/Support          Menu per peran
database/migrations  Skema (migrasi tambahan, tidak mengubah migrasi bawaan Laravel)
database/seeders     Program studi, persyaratan, dan akun contoh
database/database.sqlite  Database SQLite yang dipakai aplikasi (struktur dan data awal)
database/sql         Dump SQL lengkap (struktur dan data awal): MySQL (arsip_kp_ta.sql) dan SQLite (arsip_kp_ta.sqlite.sql)
resources/views      Blade: layouts, partials, components, halaman per peran
lang/id              Terjemahan validasi dan teks antarmuka
tests/Feature        Uji otomatis
```

## Menjalankan uji otomatis

```bash
php artisan test
```

Pengujian menggunakan SQLite in-memory (memerlukan ekstensi `pdo_sqlite`), sehingga tidak mengubah `database/database.sqlite` Anda.

Jalankan perintah di atas untuk melihat hasil terkini. Cakupannya:

- Proses masuk (email atau nomor induk), keluar, akun nonaktif, pengalihan dashboard per peran, dan pencatatan log login.
- Matriks akses: setiap peran hanya dapat membuka halamannya sendiri (200) dan ditolak (403) di halaman peran lain, termasuk aksi tulis lintas peran dan ID yang tidak ada.
- Alur KP lengkap dari draft sampai arsip, termasuk validasi unggahan (format dan ukuran), revisi administrasi dan akademik, versi dokumen, urutan riwayat status, dan penguncian setelah diarsipkan.
- Akses berkas privat: pemilik, pembimbing, dan admin dapat membuka; mahasiswa lain, dosen lain, dan tamu ditolak; tidak ada URL publik.
- Sinkronisasi data setelah admin mengubah mahasiswa, dosen, program studi, dan persyaratan.
- CRUD admin (pengguna, program studi, persyaratan), validasi, perlindungan data yang dipakai, profil, dan ganti kata sandi.
- Alur dosen pembimbing: KP ditetapkan admin, TA dipilih mahasiswa lalu diterima atau ditolak dosen, penetapan pengganti dengan bimbingan paling sedikit, hak akses, dan dashboard mahasiswa.
- Alur surat TA: surat pengantar hanya setelah verifikasi, surat balasan hanya setelah surat pengantar, SK tertahan sampai balasan diterima dan dosen menyetujui, revisi dan unggah ulang, keputusan usang ditolak, arsip TA mewajibkan SK, hak akses tiap peran, privasi berkas surat balasan, dan KP tidak terpengaruh.
- Render semua halaman untuk setiap peran pada setiap status pengajuan dan status pembimbing, termasuk tujuh variasi keadaan surat TA.
- Migrasi (naik dan rollback) serta seeder, termasuk aman dijalankan ulang.

## Yang belum diuji

Hal berikut belum dapat diuji di lingkungan pengembangan dan sebaiknya Anda periksa sendiri saat pertama kali menjalankan aplikasi:

- **MySQL.** Seluruh pengujian memakai SQLite. Migrasi sudah ditinjau terhadap batasan MySQL (panjang indeks, urutan kunci asing, `down()`), tetapi belum dijalankan di MySQL.
- **Build Tailwind (`npm install && npm run build`).** Registry npm tidak terjangkau di lingkungan pengembangan, sehingga proses build dan tampilan visual di browser belum diperiksa secara langsung. Pengujian otomatis hanya memeriksa HTML yang dihasilkan, bukan hasil CSS.
- **Unggahan berkas nyata dari browser** (pengujian menggunakan berkas tiruan), serta tampilan pada perangkat seluler yang sebenarnya.

## Keterbatasan saat ini

- Dokumen resmi (surat pengantar, SK) diunggah oleh admin sebagai berkas. Aplikasi belum dapat membuat dokumen dari templat.
- Data Master saat ini baru berisi Program Studi.
- Belum tersedia notifikasi email, pencarian lintas arsip tingkat lanjut, maupun ekspor laporan.
- Setiap pengajuan hanya memiliki satu dosen pembimbing (belum tersedia pembimbing kedua), dan belum ada batas kuota bimbingan per dosen.

## Menempelkan ke proyek Laravel yang sudah ada

Kode ini ditulis untuk Laravel 13 dan Tailwind v4. Jika Anda sudah memiliki proyek Laravel 13 dengan skeleton standar, salin folder `app`, `database`, `lang`, `resources`, `routes`, `tests`, serta file `bootstrap/app.php`, `config/app.php`, `config/filesystems.php`, `vite.config.js`, `phpunit.xml`, dan `.env.example` (cocokkan isinya dengan berkas milik Anda; jangan menimpanya begitu saja apabila sudah terdapat perubahan). Migrasi bersifat tambahan: tabel `users` ditambahi kolom, bukan dibuat ulang. Setelah itu, jalankan `php artisan migrate --seed`.

## Deploy ke Vercel

Konfigurasinya sudah tersedia di repositori: `vercel.json` (runtime `vercel-php@0.8.0`, PHP 8.4), `api/index.php` (pintu masuk Laravel di Vercel), `.vercelignore`, dan skrip `vercel` di `composer.json` (membangun aset Vite ke dalam bundel fungsi).

1. Pada Vercel: Add New > Project, impor repositori ini. Framework Preset dan Build Command dibiarkan sesuai bawaan karena sudah diatur oleh `vercel.json`.
2. Pada Settings > Environment Variables, isikan `APP_KEY` (hasil `php artisan key:generate --show`) dan `APP_URL` (alamat situs di Vercel).
3. Jalankan Deploy.

Tanpa pengaturan tambahan, aplikasi berjalan dalam **mode demo**: `database/database.sqlite` disalin ke `/tmp`, sesi menggunakan cookie, dan berkas unggahan disimpan di `/tmp`. Isi `/tmp` hilang ketika instance fungsi berganti dan tidak dibagikan antarinstance, sehingga data baru (akun, pengajuan, dokumen) tidak bersifat permanen. Mode ini cukup untuk keperluan demo atau presentasi.

Untuk data permanen, isikan `DB_CONNECTION` (`mysql` atau `pgsql`) beserta `DB_URL` atau `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` pada Vercel. Mode demo otomatis tidak digunakan; sesi dan cache memakai database. Muat terlebih dahulu struktur dan data awal ke database tersebut (untuk MySQL: `database/sql/arsip_kp_ta.sql`, atau `php artisan migrate --seed` dari komputer Anda dengan `.env` yang menunjuk ke database tersebut).

Batasan di Vercel:

- Berkas unggahan masih disimpan di `/tmp` (disk `local`), sehingga tidak permanen meskipun database sudah permanen. Penyimpanan permanen memerlukan disk S3 dan perubahan kode, karena aplikasi memanggil `Storage::disk('local')` secara langsung.
- Batas ukuran request ke fungsi Vercel adalah 4,5 MB, lebih kecil daripada batas dokumen 5 MB pada Pengaturan Persyaratan.
- Perubahan skema harus diterapkan secara mandiri pada database yang digunakan; Vercel tidak menjalankan `migrate`. Pada mode demo, perbarui `database/database.sqlite` di repositori.
