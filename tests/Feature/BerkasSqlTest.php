<?php

namespace Tests\Feature;

use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Penjaga agar database/sql/arsip_kp_ta.sql tidak tertinggal dari migrasi dan seeder.
 * Bila tes ini gagal setelah Anda menambah migrasi, buat ulang berkas SQL dari database Anda:
 *   mysqldump -u root -p arsip_kp_ta > database/sql/arsip_kp_ta.sql
 */
class BerkasSqlTest extends TestCase
{
    use RefreshDatabase;

    private const PETUNJUK = 'Berkas database/sql/arsip_kp_ta.sql tertinggal dari migrasi. Buat ulang dengan: mysqldump -u root -p arsip_kp_ta > database/sql/arsip_kp_ta.sql';

    private function sql(): string
    {
        $path = base_path('database/sql/arsip_kp_ta.sql');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_berkas_sql_memuat_semua_tabel_dan_kolom_hasil_migrasi(): void
    {
        $sql = $this->sql();

        foreach (Schema::getTables() as $tabel) {
            $nama = $tabel['name'];

            $cocok = preg_match('/create table (?:if not exists )?`'.preg_quote($nama, '/').'` \((.*?)\n\)[ ;]/is', $sql, $m);
            $this->assertSame(1, $cocok, "Tabel {$nama} tidak ada di berkas SQL. ".self::PETUNJUK);

            foreach (Schema::getColumnListing($nama) as $kolom) {
                $ada = str_contains($m[1], "`{$kolom}` ") || str_contains($sql, "alter table `{$nama}` add `{$kolom}` ");
                $this->assertTrue($ada, "Kolom {$nama}.{$kolom} tidak ada di berkas SQL. ".self::PETUNJUK);
            }
        }
    }

    public function test_berkas_sql_mencatat_semua_migrasi_sebagai_sudah_dijalankan(): void
    {
        $sql = $this->sql();

        foreach (glob(database_path('migrations/*.php')) as $berkas) {
            $nama = basename($berkas, '.php');
            $this->assertStringContainsString("'{$nama}'", $sql, "Migrasi {$nama} belum tercatat di berkas SQL. ".self::PETUNJUK);
        }
    }

    public function test_berkas_sql_memuat_data_awal_dan_akun_contoh(): void
    {
        $this->seed();
        $sql = $this->sql();

        foreach (User::pluck('email') as $email) {
            $this->assertStringContainsString("'{$email}'", $sql, "Akun {$email} tidak ada di berkas SQL.");
        }
        foreach (ProgramStudi::pluck('kode') as $kode) {
            $this->assertStringContainsString("'{$kode}'", $sql, "Program studi {$kode} tidak ada di berkas SQL.");
        }
    }
}
