<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama, NIM, dan program studi mahasiswa TIDAK disalin ke sini; semuanya dibaca
        // dari tabel users melalui mahasiswa_id supaya selalu sinkron.
        Schema::create('pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pembimbing_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('jenis', 5);
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('instansi')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('diajukan_at')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan');
    }
};
