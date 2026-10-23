<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Isi awal database. Semua seeder aman dijalankan berulang kali
     * (memakai firstOrCreate, sehingga data yang sudah diubah admin tidak ditimpa).
     */
    public function run(): void
    {
        $this->call([
            ProgramStudiSeeder::class,
            PersyaratanSeeder::class,
            UserSeeder::class,
        ]);
    }
}
