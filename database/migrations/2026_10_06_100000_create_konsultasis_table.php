<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konsultasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('catatan')->nullable();
            $table->string('bukti_path')->nullable(); // foto bukti, opsional
            $table->timestamps();

            // Satu centang per hari per mahasiswa.
            $table->unique(['mahasiswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konsultasis');
    }
};
