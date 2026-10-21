<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi sesuai PDF "Prompt Sistem Administrasi TA Terintegrasi (Revisi)":
 *  - Pembimbing 2 (pembimbing 1 tetap memakai `dospem_id`) beserta persetujuan masing-masing [K-07].
 *  - Pembatalan dan penggantian pengajuan (status Dibatalkan / Digantikan).
 *
 * Hanya MENAMBAH kolom; migrasi lama tidak disentuh dan data lama tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->foreignId('dospem2_id')->nullable()->after('dospem_id')->constrained('dosens')->nullOnDelete();
            // Persetujuan akademik dicatat per pembimbing; status_akademik = Disetujui bila semua yang berlaku sudah setuju.
            $table->timestamp('setuju_dospem1_pada')->nullable()->after('dospem2_id');
            $table->timestamp('setuju_dospem2_pada')->nullable()->after('setuju_dospem1_pada');

            $table->timestamp('dibatalkan_pada')->nullable()->after('selesai_pada');
            $table->text('alasan_batal')->nullable()->after('dibatalkan_pada');
            $table->foreignId('digantikan_oleh_id')->nullable()->after('alasan_batal')->constrained('pengajuans')->nullOnDelete();

            // Gerbang surat balasan hanya berlaku untuk pengajuan yang dibuat SETELAH revisi ini.
            // Pengajuan lama yang sudah berjalan tidak punya surat pengantar/balasan, jadi dikecualikan
            // (diisi false di bawah) agar tidak macet di tengah jalan.
            $table->boolean('wajib_surat_balasan')->default(true)->after('digantikan_oleh_id');
        });

        DB::table('pengajuans')->update(['wajib_surat_balasan' => false]);
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropColumn('wajib_surat_balasan');
            $table->dropConstrainedForeignId('digantikan_oleh_id');
            $table->dropColumn(['alasan_batal', 'dibatalkan_pada', 'setuju_dospem2_pada', 'setuju_dospem1_pada']);
            $table->dropConstrainedForeignId('dospem2_id');
        });
    }
};
