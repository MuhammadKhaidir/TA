<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Akun contoh untuk pengembangan lokal. Semua berkata sandi "password".
     * Ubah atau hapus akun ini sebelum aplikasi dipakai sungguhan.
     */
    public function run(): void
    {
        $prodi = ProgramStudi::pluck('id', 'kode');

        $akun = [
            [
                'name' => 'Administrator Sistem', 'email' => 'admin@fasilkom.test',
                'role' => Role::Admin, 'nomor_induk' => 'ADM0001',
            ],
            [
                'name' => 'Operator Akademik', 'email' => 'operator@fasilkom.test',
                'role' => Role::Operator, 'nomor_induk' => 'OPR0001',
            ],
            [
                'name' => 'Dr. Budi Santoso, M.Kom.', 'email' => 'dosen1@fasilkom.test',
                'role' => Role::Dosen, 'nomor_induk' => '198001012005011001', 'program_studi_id' => $prodi['TI'] ?? null,
            ],
            [
                'name' => 'Dr. Siti Rahma, M.T.', 'email' => 'dosen2@fasilkom.test',
                'role' => Role::Dosen, 'nomor_induk' => '198203152006042001', 'program_studi_id' => $prodi['SI'] ?? null,
            ],
            [
                'name' => 'Andi Pratama', 'email' => 'mahasiswa1@fasilkom.test',
                'role' => Role::Mahasiswa, 'nomor_induk' => '09021282126001',
                'program_studi_id' => $prodi['TI'] ?? null, 'angkatan' => 2021,
            ],
            [
                'name' => 'Rina Lestari', 'email' => 'mahasiswa2@fasilkom.test',
                'role' => Role::Mahasiswa, 'nomor_induk' => '09021282227002',
                'program_studi_id' => $prodi['SI'] ?? null, 'angkatan' => 2022,
            ],
        ];

        foreach ($akun as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                $data + ['password' => 'password', 'aktif' => true],
            );
        }
    }
}
