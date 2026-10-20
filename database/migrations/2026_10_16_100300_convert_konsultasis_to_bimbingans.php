<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * `konsultasis` (centang konsultasi) berkembang menjadi `bimbingans`: dipakai untuk
 * TA dan KP, memiliki topik, tindak lanjut, dan validasi Dospem. Baris lama tetap
 * ada (jenis = 'ta', status validasi = 'menunggu').
 *
 * File foto bukti lama berada di disk `public` (bisa diakses lewat URL langsung);
 * dipindahkan ke disk privat agar hanya terbuka lewat route yang terotorisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('konsultasis', 'bimbingans');

        Schema::table('bimbingans', function (Blueprint $table) {
            $table->string('jenis')->default('ta')->after('mahasiswa_id'); // ta | kp
            $table->string('topik')->nullable()->after('tanggal');
            $table->text('tindak_lanjut')->nullable()->after('catatan');
            $table->string('status_validasi')->default('menunggu')->after('bukti_path'); // menunggu | divalidasi | perlu_revisi
            $table->text('catatan_dospem')->nullable()->after('status_validasi');
            $table->foreignId('divalidasi_oleh')->nullable()->after('catatan_dospem')->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable()->after('divalidasi_oleh');

            // Unik per mahasiswa + jenis + tanggal (dibuat sebelum yang lama dihapus,
            // karena MySQL membutuhkan indeks yang diawali mahasiswa_id untuk foreign key).
            $table->unique(['mahasiswa_id', 'jenis', 'tanggal'], 'bimbingans_mhs_jenis_tgl_unique');
        });

        Schema::table('bimbingans', function (Blueprint $table) {
            // Mengganti nama tabel tidak mengganti nama indeks, jadi nama lama dipakai apa adanya.
            $table->dropUnique('konsultasis_mahasiswa_id_tanggal_unique');
        });

        $publik = Storage::disk('public');
        $privat = Storage::disk('local');
        foreach (DB::table('bimbingans')->whereNotNull('bukti_path')->pluck('bukti_path') as $path) {
            if ($publik->exists($path)) {
                $privat->put($path, $publik->get($path));
                $publik->delete($path);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bimbingans', function (Blueprint $table) {
            $table->unique(['mahasiswa_id', 'tanggal'], 'konsultasis_mahasiswa_id_tanggal_unique');
        });

        Schema::table('bimbingans', function (Blueprint $table) {
            $table->dropUnique('bimbingans_mhs_jenis_tgl_unique');
            $table->dropConstrainedForeignId('divalidasi_oleh');
            $table->dropColumn(['jenis', 'topik', 'tindak_lanjut', 'status_validasi', 'catatan_dospem', 'divalidasi_pada']);
        });

        Schema::rename('bimbingans', 'konsultasis');
    }
};
