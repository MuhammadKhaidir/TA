<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role operator dihapus; seluruh fungsinya dipegang admin.
 *
 * Nilai 'operator' tersimpan di tiga kolom string (bukan enum database), jadi cukup dialihkan ke 'admin':
 * - users.role              : akun operator menjadi akun admin
 * - persyaratan.pemeriksa   : dokumen yang dulu diperiksa operator kini diperiksa admin
 * - pemeriksaan.peran       : riwayat pemeriksaan administrasi tetap tercatat, kini atas nama admin
 *
 * Tanpa pengalihan ini, cast ke enum Role akan gagal membaca baris lama yang masih bernilai 'operator'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'operator')->update(['role' => 'admin']);
        DB::table('persyaratan')->where('pemeriksa', 'operator')->update(['pemeriksa' => 'admin']);
        DB::table('pemeriksaan')->where('peran', 'operator')->update(['peran' => 'admin']);

        Schema::table('persyaratan', function (Blueprint $table) {
            $table->string('pemeriksa', 10)->default('admin')->change();
        });
    }

    public function down(): void
    {
        Schema::table('persyaratan', function (Blueprint $table) {
            $table->string('pemeriksa', 10)->default('operator')->change();
        });

        // Sebelum penggabungan, kedua kolom ini tidak pernah bernilai 'admin', jadi pemulihannya pasti.
        DB::table('persyaratan')->where('pemeriksa', 'admin')->update(['pemeriksa' => 'operator']);
        DB::table('pemeriksaan')->where('peran', 'admin')->update(['peran' => 'operator']);

        // users.role tidak dapat dipulihkan: akun operator lama tidak bisa dibedakan dari admin asli.
    }
};
