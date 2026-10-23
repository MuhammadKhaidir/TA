<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persyaratan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_pengajuan', 5);
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            // Siapa yang memeriksa dokumen ini: operator (administrasi) atau dosen (akademik).
            $table->string('pemeriksa', 10)->default('operator');
            $table->boolean('wajib')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedInteger('ukuran_maks_kb')->default(5120);
            $table->string('ekstensi', 100)->default('pdf');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['jenis_pengajuan', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persyaratan');
    }
};
