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
        Schema::create('sidang_pengujis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained()->cascadeOnDelete();

            // peran: App\Enums\PerananPenguji
            $table->string('peran')->default('anggota');

            // Langkah 10 — konfirmasi kehadiran dosen penguji.
            // konfirmasi: App\Enums\KonfirmasiKehadiran
            $table->string('konfirmasi')->default('menunggu');
            $table->text('alasan_berhalangan')->nullable();

            // Langkah 12 — input nilai ujian TA di SIMAK.
            $table->boolean('nilai_diinput')->default(false);
            $table->timestamp('waktu_input_nilai')->nullable();

            $table->timestamps();

            $table->unique(['sidang_id', 'dosen_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidang_pengujis');
    }
};
