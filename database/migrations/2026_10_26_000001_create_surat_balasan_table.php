<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Surat balasan Tugas Akhir: diunggah mahasiswa setelah surat pengantar terbit, diperiksa operator.
        Schema::create('surat_balasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $table->foreignId('diunggah_oleh')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('versi')->default(1);
            // Hanya versi terbaru yang bernilai true; versi lama disimpan sebagai jejak.
            $table->boolean('terbaru')->default(true);
            $table->string('nama_asli');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diperiksa_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'terbaru'], 'surat_balasan_terbaru_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_balasan');
    }
};
