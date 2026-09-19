<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sidangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained()->cascadeOnDelete();

            // jenis: App\Enums\JenisUjian, status: App\Enums\SidangStatus
            $table->string('jenis');
            $table->string('status')->default('diajukan');

            // Langkah 1 — kelengkapan syarat pengajuan.
            $table->date('tanggal_pengajuan');
            $table->unsignedInteger('jumlah_konsultasi_saat_ajukan')->default(0);
            $table->string('judul_ta')->nullable();

            // Langkah 2 & 3 — jadwal usulan dan verifikasi kelulusan.
            $table->date('tanggal_sidang')->nullable();
            $table->time('jam_sidang')->nullable();
            $table->string('media')->nullable(); // luring / daring
            $table->string('tempat')->nullable(); // ruangan atau tautan daring
            $table->unsignedTinyInteger('nilai_usep')->nullable();
            $table->boolean('dkn_terverifikasi')->default(false);

            // Langkah 5 & 6 — SK Penguji.
            $table->string('nomor_sk_penguji')->nullable();

            // Langkah 10 — riwayat penjadwalan ulang (jika terjadi).
            $table->unsignedTinyInteger('jumlah_penjadwalan_ulang')->default(0);
            $table->text('alasan_penjadwalan_ulang')->nullable();

            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidangs');
    }
};
