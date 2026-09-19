# Sistem Pendaftaran Ujian Akhir Program (Prototipe)

Prototipe Laravel yang mengimplementasikan **Prosedur Operasional Standar (POS)
"Pendaftaran Ujian Akhir Program"** — No. POS 020/POS/FASILKOM/2026, Fakultas
Ilmu Komputer, Universitas Sriwijaya — secara digital, mengikuti seluruh 16
langkah pada Bagan Alir POS beserta 5 catatan Peringatan di dalamnya.

Revisi ini menimpa total implementasi sebelumnya (yang baru berupa kerangka
login + 1 dashboard statis) agar benar-benar merepresentasikan alur pada
dokumen POS terbaru (TGL EFEKTIF 24 Februari 2026 / Nomor POS
020/POS/FASILKOM/2026, 6 Juli 2026).

## Menjalankan

Jalankan `SETUP.cmd` (Windows). Skrip ini akan: `composer install`,
`key:generate`, `migrate:fresh --seed`, `npm install && npm run build`, dan
`storage:link`. Setelah selesai, jalankan `php artisan serve`.

## Akun Demo

Kata sandi seluruh akun demo: `password`.

| Email | Peran | Bertanggung jawab atas langkah |
|---|---|---|
| `admin@ta.test` | Administrator | Panel oversight (`/admin`) — data induk mahasiswa & dosen |
| `mahasiswa@ta.test` | Mahasiswa | Langkah 1 |
| `sekdep@ta.test` | SekDep / Koor. Prodi | Langkah 2, 6 (bagian kedua), 10 (bagian kedua), 16 |
| `penata@ta.test` | Penata | Langkah 3, 4, 6 (bagian pertama), 15 |
| `pengelola@ta.test` | Pengelola Layanan | Langkah 5, 13, 14 |
| `administrasi@ta.test` | Pengadministrasi Perkantoran | Langkah 7, 8, 9, 11 |
| `dosen@ta.test` | Dosen Penguji | Langkah 10 (bagian pertama), 12 |

Data seeder (`database/seeders/DatabaseSeeder.php`) menjalankan 9 skenario
mahasiswa berbeda **melalui service alur kerja yang sesungguhnya** (bukan
insert manual), sehingga begitu `SETUP.cmd` selesai setiap dashboard peran
sudah memiliki data yang relevan untuk didemokan — mulai dari status
"Ditunda" (syarat konsultasi belum cukup) sampai "Selesai" (nilai lengkap di
SIMAK).

## Pemetaan 16 Langkah Bagan Alir POS ↔ Sistem

Setiap baris pada Bagan Alir POS diimplementasikan sebagai satu method pada
`App\Services\SidangWorkflowService`, dipicu melalui tombol aksi kontekstual
pada halaman `/sidang/{id}` (dapat diakses lintas peran dengan otorisasi
sesuai keterlibatan masing-masing), dan selalu tercatat sebagai jejak audit
pada tabel `sidang_riwayats`.

| # | Kegiatan (Bagan Alir POS) | Pelaksana | Method Service |
|---|---|---|---|
| 1 | Mahasiswa menyerahkan berkas persyaratan (DKN, bukti kelulusan USEP) | Mahasiswa | `ajukanSidang()` |
| 2 | Usulan jadwal ujian & penetapan tim penguji | SekDep/Koor. Prodi | `usulkanJadwal()` |
| 3 | Verifikasi kelulusan USEP, DKN, dan jadwal | Penata | `verifikasiDanTeruskanPengelola()` |
| 4 | Meneruskan jadwal ke Pengelola Layanan | Penata | (bagian dari method di atas) |
| 5 | Pengesahan & penerbitan SK Penguji | Pengelola Layanan | `sahkanSkPenguji()` |
| 6 | Distribusi SK: Penata → SekDep → Dosen Penguji | Penata, lalu SekDep | `teruskanSkKeSekdep()`, `distribusikanSkKeDosen()` |
| 7 | Penyiapan dokumen administrasi ujian | Pengadministrasi Perkantoran | `siapkanAdministrasi()` |
| 8 | Penyampaian info jadwal ke mahasiswa | Pengadministrasi Perkantoran | `informasikanJadwal()` |
| 9 | Pengecekan kelengkapan berkas sebelum ujian | Pengadministrasi Perkantoran | `cekKelengkapanBerkas()` |
| 10 | Konfirmasi berhalangan → penjadwalan ulang | Dosen Penguji, lalu SekDep | `konfirmasiBerhalangan()`, `jadwalkanUlang()` |
| 11 | Penyerahan berkas ujian ke dosen (hari-H) | Pengadministrasi Perkantoran | `serahkanBerkasUjian()` |
| 12 | Input nilai ujian TA di SIMAK | Dosen Penguji | `inputNilai()` |
| 13 | Pemeriksaan kelengkapan nilai di SIMAK | Pengelola Layanan | `cekNilaiSimak()` |
| 14 | Eskalasi ke Penata bila nilai belum lengkap | Pengelola Layanan | `laporKePenata()` |
| 15 | Eskalasi ke SekDep bila masih belum lengkap | Penata | `laporKeSekdep()` |
| 16 | SekDep menghubungi dosen agar segera input nilai | SekDep/Koor. Prodi | `hubungiDosen()` |

Status sebuah sidang (`App\Enums\SidangStatus`) merepresentasikan "langkah
mana yang baru saja tuntas" sekaligus "peran mana yang harus bertindak
berikutnya", sehingga setiap dashboard peran otomatis menampilkan antrean
sidang yang menjadi tanggung jawabnya.

## Implementasi 5 Peringatan POS

| # | Bunyi Peringatan | Implementasi |
|---|---|---|
| 1 | Sidang ditunda jika konsultasi < 12 kali | `Mahasiswa::memenuhiSyaratKonsultasi()` — pengajuan otomatis berstatus **Ditunda**; SekDep memperbarui jumlah konsultasi untuk melanjutkan |
| 2 | SK Penguji harus terbit maks. H-7 sebelum sidang | `Sidang::risikoSkBelumTerbit()` — banner peringatan tampil di halaman detail |
| 3 | Dosen berhalangan → Kadep/Kaprodi ambil langkah | Alur Langkah 10: `konfirmasiBerhalangan()` → `jadwalkanUlang()` (dengan opsi pergantian dosen) |
| 4 | File TA wajib dikirim H-1 sebelum sidang | `Sidang::terlambatUnggahDokumenTa()` — banner peringatan bila lewat batas & belum diunggah |
| 5 | Sidang skripsi maks. 1 jam | `JenisUjian::batasDurasiMenit()` + `Sidang::batasSelesaiSidang()` — perkiraan jam selesai ditampilkan pada halaman detail |

Mutu Baku "Waktu: 1 Hari" pada setiap langkah turut diimplementasikan sebagai
SLA (`Sidang::batasWaktuAksi()` / `aksiTerlambat()`), ditandai pada dashboard
tiap peran bila sebuah sidang sudah melewati 1 hari sejak langkah
sebelumnya tanpa diproses.

## Arsitektur Singkat

- **`app/Enums`** — `SidangStatus` (mesin status 16-langkah), `JenisUjian`,
  `JenisDokumen`, `PerananPenguji`, `KonfirmasiKehadiran`.
- **`app/Services/SidangWorkflowService.php`** — satu-satunya pintu masuk
  untuk mengubah status sidang; setiap perubahan divalidasi (status saat ini
  harus tepat) dan selalu menulis ke `sidang_riwayats`.
- **`app/Http/Controllers/SidangController.php`** — satu endpoint per aksi
  POS, memanggil service lalu redirect dengan pesan flash.
- **`app/Http/Controllers/DashboardController.php`** — satu method per
  peran, menampilkan antrean sidang yang relevan.
- **`resources/views/sidang/show.blade.php`** — halaman detail lintas peran:
  timeline 16 langkah, dokumen, dewan penguji, dan panel aksi kontekstual
  (hanya menampilkan aksi yang berhak dilakukan oleh peran yang sedang
  login, sesuai status sidang saat ini).
- **Panel Admin (Filament, `/admin`)** — bersifat *oversight* (baca-saja
  untuk data sidang) agar mesin status tidak dapat dilewati/rusak lewat
  CRUD manual; data induk Mahasiswa & Dosen tetap dapat dikelola penuh.

## Simplifikasi & Keterbatasan (perlu dikonfirmasi sebelum produksi)

- Integrasi SIMAK V3 disimulasikan sebagai penanda "nilai_diinput" pada
  sistem ini (bukan pemanggilan API SIMAK yang sesungguhnya).
  Nomor SK, penomoran surat, dan dokumen (DKN, Berita Acara, Form Revisi,
  dsb.) berupa unggahan PDF bebas, belum memakai templat resmi/otomatis.
- Otorisasi peran bersifat umum (per-peran), belum dibatasi per
  departemen/program studi tertentu.
- Notifikasi (WhatsApp/email) pada Langkah 8 dan 16 masih berupa pencatatan
  di sistem, belum terintegrasi ke saluran komunikasi sesungguhnya.
- Penjadwalan ulang (Langkah 10) memperbarui tanggal/jam & (opsional) dosen
  pengganti secara langsung, belum mengulang proses penerbitan SK secara
  otomatis — perlu dikonfirmasi apakah SK perlu diterbitkan ulang formalnya.
