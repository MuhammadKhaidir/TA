<?php

namespace Database\Seeders;

use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;

class ProgramStudiSeeder extends Seeder
{
    /**
     * Contoh awal. Sesuaikan lewat menu Admin > Data Master > Program Studi.
     */
    public function run(): void
    {
        $data = [
            ['kode' => 'TI', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1'],
            ['kode' => 'SI', 'nama' => 'Sistem Informasi', 'jenjang' => 'S1'],
            ['kode' => 'SK', 'nama' => 'Sistem Komputer', 'jenjang' => 'S1'],
        ];

        foreach ($data as $baris) {
            ProgramStudi::firstOrCreate(['kode' => $baris['kode']], $baris + ['aktif' => true]);
        }
    }
}
