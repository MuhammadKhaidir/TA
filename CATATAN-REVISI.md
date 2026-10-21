# Catatan Revisi: Sistem Administrasi TA Terintegrasi

Revisi ini menambahkan fitur yang diminta pada PDF "Prompt Sistem Administrasi TA Terintegrasi (Revisi)" ke repo yang sudah berjalan. Modul KP, enum `Peran`, dan tabel sidang lama (`sidangs`, `sidang_pengujis`, `sidang_riwayats`, `dokumens`) tidak diubah.

## Cara memasang

1. Salin isi folder ini menimpa repo kamu (file yang sama akan tertimpa, file baru ditambahkan). `composer.json` dan `composer.lock` tidak berubah, jadi tidak perlu `composer update`.
2. Jalankan migrasi (hanya menambah, tidak menghapus data lama):
   ```
   php artisan migrate
   ```
3. Opsional, untuk mengisi contoh dokumen Kumpulan A (8) dan dokumen sidang (4) di database yang sudah berisi data:
   ```
   php artisan db:seed --class=KonfigurasiAwalSeeder
   ```
4. Jalankan tes:
   ```
   php artisan test
   ```

Pengajuan TA yang sudah berjalan sebelum revisi tidak dikenai gerbang surat balasan (kolom `wajib_surat_balasan` bernilai `false` untuk baris lama), dan SK lama otomatis bersumber `website`.

## Yang perlu kamu sesuaikan dengan PDF (isi di sini hanya pengisi tempat)

Isi PDF 18 halaman tidak bisa saya baca langsung; saya bekerja dari ringkasannya di memo. Nama dan jumlah berikut harus dicocokkan dengan PDF:

| Hal | Di mana | Keadaan sekarang |
|---|---|---|
| 6 data surat pengantar | `config/ta.php` → `surat_pengantar_data` | 6 isian contoh (instansi, alamat, ditujukan kepada, judul sementara, waktu pelaksanaan, kontak) |
| Butir checklist surat balasan | `config/ta.php` → `surat_balasan_checklist` | 5 butir contoh |
| 8 dokumen Kumpulan A | Panel Admin → Dokumen Surat Pengantar TA | 8 baris bernama "Dokumen Kumpulan A nomor N (ganti dengan nama dokumen...)" bila seeder dijalankan |
| 4 dokumen sidang | Panel Admin → Dokumen Sidang TA | SK TA dan Kartu konsultasi (tautan otomatis) + 2 baris pengisi tempat |
| Format kartu konsultasi PDF | `resources/views/pdf/kartu-konsultasi.blade.php` (atau ganti `ta.kartu_view` di `config/ta.php`) | Format sederhana sementara [K-04] |

## Pengaturan (Panel Admin → Pengaturan)

Hal yang bertanda [K-xx] di PDF belum dikonfirmasi fakultas, jadi disimpan sebagai pengaturan, bukan ditanam di kode:

| Kunci | Bawaan | Fungsi |
|---|---|---|
| `sk.mode` | `website` | `website` = SK dibuat dari template; `offline` = SK resmi terbit di luar sistem, Admin hanya mencatat nomor, tanggal, dan salinan [K-14] |
| `ta.pembimbing_kedua_wajib` | `1` | Pembimbing 2 wajib dan ikut menyetujui [K-07] |
| `kartu.butuh_sk` | `1` | Kartu konsultasi terkunci sampai SK terbit [K-04] |
| `bimbingan.minimal_ta` | `12` (bawaan lama) | Minimal bimbingan tervalidasi sebelum pembimbing menyetujui sidang [K-05]; kosongkan bila tidak ada batas |
| `sidang.durasi_menit` | `90` | Dipakai untuk cek jadwal bentrok |
| `sidang.minimal_penguji` | `1` | Jumlah penguji minimal |
| `akses.periksa_kelengkapan` | Operator | Siapa memeriksa kelengkapan (surat pengantar, SK, sidang) [K-01] |
| `akses.validasi_surat_balasan` | Operator | Siapa memvalidasi surat balasan |
| `akses.proses_surat_pengantar` | Admin | Siapa memproses surat pengantar offline |
| `akses.catat_sk` | Admin | Siapa mencatat SK offline |
| `akses.jadwalkan_sidang` | Admin | Siapa menjadwalkan sidang dan menetapkan penguji |
| `akses.konfirmasi_sidang_selesai` | Admin | Siapa menandai Sidang Selesai [K-09] |

Tugas yang dipegang Operator dikerjakan di halaman web Operator (detail pengajuan dan halaman Sidang); tugas yang dipegang Admin dikerjakan di panel `/admin`. Jika kamu mengalihkan tugas pemeriksaan kelengkapan ke Admin, Admin bisa mengerjakannya lewat halaman web yang sama, tetapi belum ada menu navigasinya (alamat halamannya harus dibuka langsung).

## Ringkasan fitur

1. **Surat pengantar TA**: mahasiswa mengisi data dan mengunggah dokumen Kumpulan A, Operator memeriksa kelengkapan (atau mengembalikan dengan catatan), Admin memproses offline lalu menandai selesai.
2. **Pembimbing 1 dan 2**: dipilih dari master dosen, harus berbeda; keduanya harus menyetujui. Mengganti pembimbing mengulang persetujuan.
3. **Surat balasan**: diunggah setelah surat pengantar selesai; Operator memvalidasi dengan checklist (semua butir harus terpenuhi, kalau tidak "Tidak valid" dengan catatan, mahasiswa mengunggah ulang).
4. **Gerbang SK**: pengajuan SK tidak bisa dikirim dan SK tidak bisa terbit/dicatat sebelum surat balasan valid.
5. **SK offline**: nomor, tanggal (tidak boleh di masa depan), dan salinan PDF/gambar; nomor tidak boleh kembar.
6. **Kartu konsultasi PDF**: dari catatan bimbingan, terkunci sebelum SK terbit.
7. **Sidang TA**: hanya setelah SK terbit; 4 dokumen (SK dan kartu ditautkan otomatis), verifikasi Operator, persetujuan kedua pembimbing dengan cek minimal bimbingan, penjadwalan Admin dengan penguji (bukan pembimbing, dosen aktif, tanpa duplikat) dan cek bentrok dosen/ruang, konfirmasi Sidang Selesai.
8. **Notifikasi dalam sistem**: lonceng di menu, daftar notifikasi, tandai dibaca; hanya milik sendiri.
9. **Pembatalan dan pengajuan ulang**: status Dibatalkan dan Digantikan, alasan wajib, tidak ada pengajuan ganda.

## Status pengujian

Suite lengkap: 97 tes lulus (44 tes lama, yang aturannya berubah sudah disesuaikan, ditambah 53 tes baru). Migrasi juga diuji naik dari database lama berisi data, rollback, dan naik lagi.

Pengujian dijalankan di PHP 8.3 (lingkungan saya) dengan komponen Symfony 7.4 sebagai pengganti sementara, karena `composer.lock` kamu membutuhkan PHP 8.4. Jalankan `php artisan test` di lingkungan PHP kamu sendiri untuk memastikan.
