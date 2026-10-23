<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $table->foreignId('persyaratan_id')->constrained('persyaratan')->restrictOnDelete();
            $table->foreignId('diunggah_oleh')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('versi')->default(1);
            // Hanya versi terbaru per persyaratan yang bernilai true; versi lama disimpan sebagai jejak.
            $table->boolean('terbaru')->default(true);
            $table->string('nama_asli');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->string('status', 20)->default('menunggu');
            $table->timestamps();

            $table->index(['pengajuan_id', 'persyaratan_id', 'terbaru'], 'dokumen_pengajuan_terbaru_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pengajuan');
    }
};
