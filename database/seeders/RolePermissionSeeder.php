<?php

namespace Database\Seeders;

use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $mahasiswaRole = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => Hash::make('password')]
        );
        $admin->assignRole($adminRole);

        $student = User::firstOrCreate(
            ['email' => 'mahasiswa@example.com'],
            ['name' => 'Mahasiswa Demo', 'password' => Hash::make('password')]
        );
        $student->assignRole($mahasiswaRole);

        Mahasiswa::firstOrCreate(
            ['user_id' => $student->id],
            [
                'nim' => '09010000000001',
                'nama' => 'Mahasiswa Demo',
                'prodi' => 'Manajemen Informatika',
                'angkatan' => '2024',
            ]
        );
    }
}
