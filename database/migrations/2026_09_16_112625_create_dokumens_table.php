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
    Schema::create('dokumens', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sidang_id')->constrained()->cascadeOnDelete();
        $table->enum('jenis_dokumen', [
            'formulir_pendaftaran',
            'sk_pembimbing_ta',
            'sk_penguji',
            'jadwal_sidang',
            'daftar_hadir',
            'berita_acara',
            'form_penilaian',
            'form_revisi',
            'dokumen_ta',
        ]);
        $table->string('nomor_sk')->nullable(); // isi cuma buat jenis SK
        $table->string('file_path');
        $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumens');
    }
};
