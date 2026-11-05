<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur log aktivitas dihapus dari aplikasi, jadi tabelnya ikut dibuang.
 * Migrasi pembuatnya (2026_10_22_000010) sengaja tidak diubah supaya riwayat migrasi tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }

    public function down(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 50)->index();
            $table->text('deskripsi');
            $table->nullableMorphs('subjek');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }
};
