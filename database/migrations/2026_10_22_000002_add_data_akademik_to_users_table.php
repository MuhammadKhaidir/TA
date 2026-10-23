<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom role dan data akademik ke tabel users bawaan Laravel.
 * Dibuat sebagai migrasi tambahan (bukan mengubah migrasi bawaan) supaya aman
 * dijalankan di database yang sudah pernah dimigrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nomor_induk', 30)->nullable()->unique();
            $table->string('role', 20)->default('mahasiswa')->index();
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studi')->restrictOnDelete();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->boolean('aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_studi_id');
            $table->dropUnique(['nomor_induk']);
            $table->dropIndex(['role']);
            $table->dropColumn(['nomor_induk', 'role', 'angkatan', 'no_hp', 'aktif']);
        });
    }
};
