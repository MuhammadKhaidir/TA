<?php

namespace Tests\Feature;

use App\Models\Persyaratan;
use App\Models\TemplateSk;
use App\Services\SkService;
use Database\Seeders\KonfigurasiAwalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class SeederKonfigurasiTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    public function test_seeder_konfigurasi_aman_dijalankan_berulang_dan_tidak_mengisi_data_resmi_kampus(): void
    {
        $this->seed(KonfigurasiAwalSeeder::class);
        $this->seed(KonfigurasiAwalSeeder::class);

        $this->assertSame(1, Persyaratan::where('jenis', 'ta_pengajuan')->count());
        $this->assertSame(6, Persyaratan::where('jenis', 'kp_lampiran')->count());
        $this->assertSame(1, TemplateSk::count());

        // Revisi: 8 dokumen Kumpulan A (surat pengantar) dan 4 dokumen sidang, dua di antaranya tautan otomatis.
        $this->assertSame(8, Persyaratan::where('jenis', 'ta_surat_pengantar')->count());
        $this->assertSame(4, Persyaratan::where('jenis', 'ta_sidang')->count());
        $this->assertSame(['kartu_konsultasi', 'sk_ta'], Persyaratan::where('jenis', 'ta_sidang')->whereNotNull('tautan_otomatis')->pluck('tautan_otomatis')->sort()->values()->all());

        // Nomor, kota, dan penandatangan tetap harus diisi Admin; SK belum boleh terbit.
        $kurang = app(SkService::class)->kekuranganKonfigurasi();
        $this->assertContains('Format nomor SK', $kurang);
        $this->assertContains('Nama penandatangan SK', $kurang);
        $this->assertNotContains('Template SK aktif', $kurang);
    }

    public function test_seeder_tidak_menimpa_perubahan_admin(): void
    {
        $this->seed(KonfigurasiAwalSeeder::class);
        Persyaratan::where('jenis', 'ta_pengajuan')->update(['nama' => 'Proposal TA (versi kampus)']);
        $this->seed(KonfigurasiAwalSeeder::class);

        $this->assertDatabaseHas('persyaratans', ['nama' => 'Proposal TA (versi kampus)']);
    }
}
