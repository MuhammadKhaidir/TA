<?php

namespace Database\Seeders;

use App\Enums\JenisPengajuan;
use App\Enums\Peran;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Pengaturan;
use App\Models\PeriodeAkademik;
use App\Models\Persyaratan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengajuanService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data demo + data awal konfigurasi. Semua akun demo berpassword `password`.
 * Isi persyaratan, jenis surat, struktur laporan, template SK, dan nilai
 * pengaturan di sini adalah CONTOH AWAL — pihak kampus perlu menyesuaikannya
 * lewat panel Admin sebelum dipakai sungguhan.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PengajuanService $service): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Peran::cases() as $peran) {
            Role::findOrCreate($peran->value, 'web');
        }

        $prodi = [];
        foreach (['TI' => 'Teknik Informatika', 'SI' => 'Sistem Informasi', 'SK' => 'Sistem Komputer'] as $kode => $nama) {
            $prodi[$kode] = Prodi::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'aktif' => true]);
        }

        PeriodeAkademik::firstOrCreate(['nama' => 'Ganjil 2026/2027'], ['aktif' => true]);

        $this->call(KonfigurasiAwalSeeder::class);
        $this->pengaturanDemo();

        // --- Akun ---
        $admin = $this->akun('admin@ta.test', 'Administrator Sistem', Peran::Admin);

        $operator = $this->akun('operator@ta.test', 'Operator Akademik', Peran::OperatorAkademik);
        $operator->update(['semua_prodi' => true]);

        $operatorSi = $this->akun('operator.si@ta.test', 'Operator Prodi SI', Peran::OperatorAkademik);
        $operatorSi->prodiOperator()->syncWithoutDetaching([$prodi['SI']->id]);

        $dospemUser = $this->akun('dospem@ta.test', 'Dr. Siti Rahmawati, M.Kom.', Peran::Dospem);
        $dospem = Dosen::firstOrCreate(['nip' => '198001012005012001'], ['user_id' => $dospemUser->id, 'nama' => $dospemUser->name, 'prodi_id' => $prodi['TI']->id, 'aktif' => true]);

        $dospem2User = $this->akun('dospem2@ta.test', 'Dr. Ahmad Anwar, M.T.', Peran::Dospem);
        $dospem2 = Dosen::firstOrCreate(['nip' => '197905052004011002'], ['user_id' => $dospem2User->id, 'nama' => $dospem2User->name, 'prodi_id' => $prodi['SI']->id, 'aktif' => true]);

        // Mahasiswa kosong, untuk mencoba alur dari awal.
        $this->mahasiswa('mahasiswa@ta.test', 'Muhammad Khaidir', '09021182126001', $prodi['TI']);

        // Mahasiswa demo lintas prodi dengan pengajuan pada tahap berbeda.
        $m2 = $this->mahasiswa('mahasiswa2@ta.test', 'Siti Aminah', '09021182126002', $prodi['TI']);
        $m3 = $this->mahasiswa('mahasiswa3@ta.test', 'Rian Hidayat', '09021182126003', $prodi['SI']);

        if (! $m2->pengajuans()->exists()) {
            $ta = $service->buat($m2, JenisPengajuan::Ta, ['judul' => 'Klasifikasi Sentimen Ulasan Produk Menggunakan Naive Bayes'], $m2->user);
            $this->unggahSemua($service, $ta, $m2->user);
            $service->kirim($ta, $m2->user);
        }

        if (! $m3->pengajuans()->exists()) {
            $ta = $service->buat($m3, JenisPengajuan::Ta, ['judul' => 'Rancang Bangun Aplikasi Presensi Berbasis QR Code'], $m3->user);
            $this->unggahSemua($service, $ta, $m3->user);
            $service->kirim($ta, $m3->user);
            $service->verifikasiAdministrasi($ta, $operator);
            $service->tetapkanDospem($ta, $dospem2, $operator);
            $service->setujuiAkademik($ta, $dospem2User);
            // Status: Disetujui — siap diterbitkan SK-nya oleh Operator Akademik.
        }
    }

    /** Nilai demo agar alur penerbitan SK dapat dicoba. TIDAK dipakai di data sungguhan. */
    private function pengaturanDemo(): void
    {
        // Nilai demo supaya alur penerbitan SK dapat dicoba. Ganti dengan data resmi kampus.
        foreach ([
            'sk.format_nomor' => '{urut}/SK/{kode_prodi}/{tahun}',
            'sk.kota' => 'Contoh Kota',
            'sk.penandatangan_nama' => 'Nama Pejabat Penandatangan (contoh)',
            'sk.penandatangan_jabatan' => 'Jabatan Penandatangan (contoh)',
        ] as $kunci => $nilai) {
            if (Pengaturan::ambil($kunci) === null) {
                Pengaturan::simpan($kunci, $nilai);
            }
        }
    }

    private function akun(string $email, string $nama, Peran $peran): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $nama, 'password' => Hash::make('password'), 'aktif' => true]);
        $user->syncRoles([$peran->value]);

        return $user;
    }

    private function mahasiswa(string $email, string $nama, string $nim, Prodi $prodi): Mahasiswa
    {
        $user = $this->akun($email, $nama, Peran::Mahasiswa);

        return Mahasiswa::firstOrCreate(
            ['nim' => $nim],
            ['user_id' => $user->id, 'nama' => $nama, 'prodi_id' => $prodi->id, 'angkatan' => '2021'],
        );
    }

    private function unggahSemua(PengajuanService $service, $pengajuan, User $actor): void
    {
        foreach ($pengajuan->persyaratanPengajuan() as $syarat) {
            $service->unggahDokumen($pengajuan, $syarat, UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n%demo\n"), $actor);
        }
    }
}
