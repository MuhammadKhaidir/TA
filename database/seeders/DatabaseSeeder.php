<?php

namespace Database\Seeders;

use App\Enums\JenisUjian;
use App\Enums\PerananPenguji;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\User;
use App\Services\SidangWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Peran yang terlibat dalam POS "Pendaftaran Ujian Akhir Program".
     *
     * @var array<int, string>
     */
    private const ROLES = [
        'admin',
        'mahasiswa',
        'dosen_penguji',
        'sekdep_koor_prodi',
        'penata',
        'pengelola_layanan',
        'pengadministrasi_perkantoran',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->buatUser('admin@ta.test', 'Administrator', 'admin');

        // --- Akun peran demo (satu login per peran, sesuai README) ---
        $sekdepUser = $this->buatUser('sekdep@ta.test', 'Dr. Hasan (SekDep/Koor. Prodi)', 'sekdep_koor_prodi');
        $penataUser = $this->buatUser('penata@ta.test', 'Yuni Astuti, S.E. (Penata)', 'penata');
        $pengelolaUser = $this->buatUser('pengelola@ta.test', 'Joko Prasetyo (Pengelola Layanan)', 'pengelola_layanan');
        $administrasiUser = $this->buatUser('administrasi@ta.test', 'Rina Wulandari (Pengadministrasi Perkantoran)', 'pengadministrasi_perkantoran');

        // --- Dosen Penguji ---
        $dosenUser = $this->buatUser('dosen@ta.test', 'Dr. Anwar Ibrahim, M.Kom.', 'dosen_penguji');
        $dosenAnwar = Dosen::firstOrCreate(
            ['nip' => '197508012005011001'],
            ['user_id' => $dosenUser->id, 'nama' => 'Dr. Anwar Ibrahim, M.Kom.', 'prodi' => 'Teknik Informatika']
        );
        $dosenSiti = Dosen::firstOrCreate(
            ['nip' => '198002142006042002'],
            ['nama' => 'Dr. Siti Nurhaliza, M.T.', 'prodi' => 'Teknik Informatika']
        );
        $dosenRahman = Dosen::firstOrCreate(
            ['nip' => '197911302005011003'],
            ['nama' => 'Rahman Hakim, S.Kom., M.Sc.', 'prodi' => 'Sistem Informasi']
        );
        $dosenMaya = Dosen::firstOrCreate(
            ['nip' => '198303102008122001'],
            ['nama' => 'Maya Sari, S.T., M.T.', 'prodi' => 'Teknik Informatika']
        );
        $dosenBambang = Dosen::firstOrCreate(
            ['nip' => '197601202003121001'],
            ['nama' => 'Prof. Bambang Sutrisno', 'prodi' => 'Sistem Informasi']
        );

        // --- Mahasiswa demo utama (dipakai untuk login mahasiswa@ta.test) ---
        $mahasiswaUser = $this->buatUser('mahasiswa@ta.test', 'Budi Santoso', 'mahasiswa');
        $mhsBudi = Mahasiswa::firstOrCreate(
            ['nim' => '09021182126001'],
            [
                'user_id' => $mahasiswaUser->id, 'nama' => 'Budi Santoso', 'prodi' => 'Teknik Informatika',
                'angkatan' => '2021', 'no_hp' => '081234567890', 'jumlah_konsultasi' => 12,
            ]
        );

        $svc = app(SidangWorkflowService::class);

        // Skenario 1 — baru diajukan, menunggu SekDep (Langkah 2).
        if ($mhsBudi->sidangs()->doesntExist()) {
            $svc->ajukanSidang($mhsBudi, [
                'jenis' => JenisUjian::Skripsi->value,
                'judul_ta' => 'Sistem Informasi Pendaftaran Ujian Akhir Program Berbasis Web',
            ], $mahasiswaUser);
        }

        // --- Mahasiswa tambahan untuk mengisi setiap tahapan alur ---
        $mhsData = [
            ['nim' => '09021182126002', 'nama' => 'Siti Aminah', 'angkatan' => '2021', 'konsultasi' => 8],
            ['nim' => '09021182126003', 'nama' => 'Rian Hidayat', 'angkatan' => '2021', 'konsultasi' => 12],
            ['nim' => '09021182126004', 'nama' => 'Dewi Lestari', 'angkatan' => '2021', 'konsultasi' => 13],
            ['nim' => '09021182126005', 'nama' => 'Andi Wijaya', 'angkatan' => '2020', 'konsultasi' => 15],
            ['nim' => '09021182126006', 'nama' => 'Rina Marlina', 'angkatan' => '2021', 'konsultasi' => 12],
            ['nim' => '09021182126007', 'nama' => 'Fajar Nugroho', 'angkatan' => '2020', 'konsultasi' => 14],
            ['nim' => '09021182126008', 'nama' => 'Wulan Sari', 'angkatan' => '2021', 'konsultasi' => 12],
            ['nim' => '09021182126009', 'nama' => 'Eka Putra', 'angkatan' => '2020', 'konsultasi' => 16],
        ];

        $mhs = [];
        foreach ($mhsData as $d) {
            $u = $this->buatUser(
                strtolower(str_replace(' ', '.', $d['nama'])).'@student.unsri.ac.id',
                $d['nama'],
                'mahasiswa'
            );
            $mhs[$d['nim']] = Mahasiswa::firstOrCreate(
                ['nim' => $d['nim']],
                [
                    'user_id' => $u->id, 'nama' => $d['nama'], 'prodi' => 'Teknik Informatika',
                    'angkatan' => $d['angkatan'], 'jumlah_konsultasi' => $d['konsultasi'],
                ]
            );
        }

        // Skenario 2 — Siti: konsultasi belum cukup -> Ditunda (Peringatan #1).
        $this->skenario($svc, $mhs['09021182126002'], 'komprehensif', 'Klasifikasi Sentimen Ulasan Produk Menggunakan Naive Bayes', function ($svc, $sidang) {
            // berhenti di status Ditunda hasil ajukanSidang
        });

        // Skenario 3 — Rian: sudah diusulkan jadwal, menunggu Penata (Langkah 3).
        $this->skenario($svc, $mhs['09021182126003'], 'komprehensif', 'Rancang Bangun Aplikasi Presensi Berbasis QR Code', function ($svc, $sidang) use ($sekdepUser, $dosenSiti, $dosenRahman) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->addDays(12)->toDateString(),
                'jam_sidang' => '09:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 1',
                'pengujis' => [
                    ['dosen_id' => $dosenSiti->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenRahman->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
        });

        // Skenario 4 — Dewi: sudah diverifikasi Penata, menunggu SK (Langkah 5).
        $this->skenario($svc, $mhs['09021182126004'], 'skripsi', 'Optimasi Rute Distribusi Menggunakan Algoritma Genetika', function ($svc, $sidang) use ($sekdepUser, $penataUser, $dosenAnwar, $dosenMaya) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->addDays(10)->toDateString(),
                'jam_sidang' => '10:00',
                'media' => 'daring',
                'tempat' => 'https://meet.unsri.ac.id/sidang-dewi',
                'pengujis' => [
                    ['dosen_id' => $dosenAnwar->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenMaya->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 82, 'dkn_terverifikasi' => true], $penataUser);
        });

        // Skenario 5 — Andi: SK terbit, sedang didistribusikan (Langkah 6).
        $this->skenario($svc, $mhs['09021182126005'], 'skripsi', 'Sistem Pendukung Keputusan Pemilihan Beasiswa dengan Metode SAW', function ($svc, $sidang) use ($sekdepUser, $penataUser, $pengelolaUser, $dosenSiti, $dosenBambang) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->addDays(9)->toDateString(),
                'jam_sidang' => '13:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 2',
                'pengujis' => [
                    ['dosen_id' => $dosenSiti->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenBambang->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 88, 'dkn_terverifikasi' => true], $penataUser);
            $svc->sahkanSkPenguji($sidang, ['nomor_sk' => '021/SK/FASILKOM/2026'], $pengelolaUser);
            $svc->teruskanSkKeSekdep($sidang, $penataUser);
        });

        // Skenario 6 — Rina: administrasi sedang menyiapkan info jadwal (Langkah 8).
        $this->skenario($svc, $mhs['09021182126006'], 'komprehensif', 'Analisis Performa Load Balancing pada Arsitektur Microservices', function ($svc, $sidang) use ($sekdepUser, $penataUser, $pengelolaUser, $administrasiUser, $dosenRahman, $dosenMaya) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->addDays(8)->toDateString(),
                'jam_sidang' => '09:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 1',
                'pengujis' => [
                    ['dosen_id' => $dosenRahman->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenMaya->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 79, 'dkn_terverifikasi' => true], $penataUser);
            $svc->sahkanSkPenguji($sidang, ['nomor_sk' => '022/SK/FASILKOM/2026'], $pengelolaUser);
            $svc->teruskanSkKeSekdep($sidang, $penataUser);
            $svc->distribusikanSkKeDosen($sidang, $sekdepUser);
            $svc->siapkanAdministrasi($sidang, [], $administrasiUser);
        });

        // Skenario 7 — Fajar: siap ujian, tinggal menunggu hari-H (Langkah 9 selesai).
        $this->skenario($svc, $mhs['09021182126007'], 'skripsi', 'Deteksi Objek Real-Time Menggunakan YOLOv8 untuk Pemantauan Lalu Lintas', function ($svc, $sidang) use ($sekdepUser, $penataUser, $pengelolaUser, $administrasiUser, $dosenAnwar, $dosenSiti) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->addDays(3)->toDateString(),
                'jam_sidang' => '10:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 3',
                'pengujis' => [
                    ['dosen_id' => $dosenAnwar->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenSiti->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 90, 'dkn_terverifikasi' => true], $penataUser);
            $svc->sahkanSkPenguji($sidang, ['nomor_sk' => '023/SK/FASILKOM/2026'], $pengelolaUser);
            $svc->teruskanSkKeSekdep($sidang, $penataUser);
            $svc->distribusikanSkKeDosen($sidang, $sekdepUser);
            $svc->siapkanAdministrasi($sidang, [], $administrasiUser);
            $svc->informasikanJadwal($sidang, $administrasiUser);
            $svc->cekKelengkapanBerkas($sidang, $administrasiUser);
        });

        // Skenario 8 — Wulan: ujian sudah berlangsung, 1 dari 2 dosen sudah input nilai
        // (menampilkan tugas aktif untuk dosen@ta.test serta antrean eskalasi Langkah 13-16).
        $this->skenario($svc, $mhs['09021182126008'], 'skripsi', 'Implementasi Chatbot Layanan Akademik Menggunakan NLP', function ($svc, $sidang) use ($sekdepUser, $penataUser, $pengelolaUser, $administrasiUser, $dosenUser, $dosenAnwar, $dosenMaya) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->subDay()->toDateString(),
                'jam_sidang' => '09:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 1',
                'pengujis' => [
                    ['dosen_id' => $dosenAnwar->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenMaya->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 85, 'dkn_terverifikasi' => true], $penataUser);
            $svc->sahkanSkPenguji($sidang, ['nomor_sk' => '024/SK/FASILKOM/2026'], $pengelolaUser);
            $svc->teruskanSkKeSekdep($sidang, $penataUser);
            $svc->distribusikanSkKeDosen($sidang, $sekdepUser);
            $svc->siapkanAdministrasi($sidang, [], $administrasiUser);
            $svc->informasikanJadwal($sidang, $administrasiUser);
            $svc->cekKelengkapanBerkas($sidang, $administrasiUser);
            $svc->serahkanBerkasUjian($sidang, $administrasiUser);
            $svc->inputNilai($sidang, $dosenAnwar, $dosenUser);
        });

        // Skenario 9 — Eka: seluruh proses tuntas (Selesai), contoh riwayat lengkap 16 langkah.
        $this->skenario($svc, $mhs['09021182126009'], 'komprehensif', 'Pengembangan Sistem Rekomendasi Skripsi Menggunakan Collaborative Filtering', function ($svc, $sidang) use ($sekdepUser, $penataUser, $pengelolaUser, $administrasiUser, $dosenBambang, $dosenRahman) {
            $svc->usulkanJadwal($sidang, [
                'tanggal_sidang' => now()->subDays(5)->toDateString(),
                'jam_sidang' => '13:00',
                'media' => 'luring',
                'tempat' => 'Ruang Sidang FASILKOM 2',
                'pengujis' => [
                    ['dosen_id' => $dosenBambang->id, 'peran' => PerananPenguji::Ketua->value],
                    ['dosen_id' => $dosenRahman->id, 'peran' => PerananPenguji::Anggota->value],
                ],
            ], $sekdepUser);
            $svc->verifikasiDanTeruskanPengelola($sidang, ['nilai_usep' => 91, 'dkn_terverifikasi' => true], $penataUser);
            $svc->sahkanSkPenguji($sidang, ['nomor_sk' => '018/SK/FASILKOM/2026'], $pengelolaUser);
            $svc->teruskanSkKeSekdep($sidang, $penataUser);
            $svc->distribusikanSkKeDosen($sidang, $sekdepUser);
            $svc->siapkanAdministrasi($sidang, [], $administrasiUser);
            $svc->informasikanJadwal($sidang, $administrasiUser);
            $svc->cekKelengkapanBerkas($sidang, $administrasiUser);
            $svc->serahkanBerkasUjian($sidang, $administrasiUser);
            $svc->inputNilai($sidang, $dosenBambang, $dosenBambang->user ?? $administrasiUser);
            $svc->inputNilai($sidang, $dosenRahman, $dosenRahman->user ?? $administrasiUser);
            $svc->cekNilaiSimak($sidang, $pengelolaUser);
        });
    }

    private function buatUser(string $email, string $nama, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nama, 'password' => Hash::make('password')]
        );
        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * Helper untuk membuat satu sidang demo lalu menjalankannya melalui
     * beberapa langkah workflow nyata (bukan insert manual), agar data
     * seeding sekaligus berfungsi sebagai uji integrasi ringan.
     */
    private function skenario(
        SidangWorkflowService $svc,
        Mahasiswa $mahasiswa,
        string $jenis,
        string $judul,
        callable $lanjutkan,
    ): void {
        if ($mahasiswa->sidangs()->exists()) {
            return;
        }

        $aktor = $mahasiswa->user;
        $sidang = $svc->ajukanSidang($mahasiswa, [
            'jenis' => $jenis,
            'judul_ta' => $judul,
        ], $aktor);

        $lanjutkan($svc, $sidang);
    }
}
