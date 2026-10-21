<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul sidang TA yang baru. Tabel sidang lama (`sidangs`, `sidang_pengujis`, `sidang_riwayats`,
 * `dokumens`) sengaja TIDAK disentuh dan tidak dipakai modul ini: datanya tetap aman sampai pihak
 * kampus memutuskan nasibnya lewat migrasi tersendiri. Itu sebabnya nama tabel di sini berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidang_tas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained()->cascadeOnDelete(); // pengajuan TA yang SK-nya sudah terbit
            $table->foreignId('mahasiswa_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('diajukan_pada')->nullable();

            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();

            // Persetujuan akademik per pembimbing (pembimbing 1 dan 2 sama-sama setuju) [K-07].
            $table->timestamp('setuju_dospem1_pada')->nullable();
            $table->timestamp('setuju_dospem2_pada')->nullable();

            $table->dateTime('jadwal_mulai')->nullable();
            $table->unsignedSmallInteger('durasi_menit')->nullable();
            $table->string('ruang')->nullable();
            $table->foreignId('dijadwalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dijadwalkan_pada')->nullable();

            $table->foreignId('selesai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();

            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('alasan_batal')->nullable();
            $table->foreignId('digantikan_oleh_id')->nullable()->constrained('sidang_tas')->nullOnDelete();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
            $table->index('jadwal_mulai');
        });

        Schema::create('sidang_ta_penguji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_ta_id')->constrained('sidang_tas')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosens')->restrictOnDelete();
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();

            $table->unique(['sidang_ta_id', 'dosen_id']);
        });

        Schema::create('sidang_ta_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_ta_id')->constrained('sidang_tas')->cascadeOnDelete();
            $table->foreignId('persyaratan_id')->nullable()->constrained('persyaratans')->nullOnDelete();
            $table->string('file_path');
            $table->string('nama_file_asli')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sidang_ta_id', 'persyaratan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_dokumens');
        Schema::dropIfExists('sidang_ta_penguji');
        Schema::dropIfExists('sidang_tas');
    }
};
