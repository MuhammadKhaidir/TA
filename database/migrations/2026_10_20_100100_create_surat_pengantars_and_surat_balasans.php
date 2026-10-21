<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat pengantar TA dan surat balasan. Keduanya menempel pada pengajuan TA.
 * Dokumen Kumpulan A pada surat pengantar memakai tabel `pengajuan_dokumens` yang sudah ada
 * (persyaratan berjenis `ta_surat_pengantar`), bukan tabel baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_pengantars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable(); // pasangan kunci-nilai; daftar isian ada di config/ta.php
            $table->string('status')->default('draft');
            $table->text('catatan_operator')->nullable();
            $table->timestamp('diajukan_pada')->nullable();
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->string('nomor_surat')->nullable(); // dicatat bila surat resmi sudah bernomor
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamp('diambil_pada')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
        });

        Schema::create('surat_balasans', function (Blueprint $table) {
            $table->id();
            // Satu surat balasan per pengajuan; unggah ulang menggantikan berkas yang belum valid.
            $table->foreignId('pengajuan_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('nama_file_asli')->nullable();
            $table->string('status')->default('menunggu');
            $table->json('checklist')->nullable(); // kunci butir => true/false; butir ada di config/ta.php
            $table->text('catatan_operator')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_balasans');
        Schema::dropIfExists('surat_pengantars');
    }
};
