<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini adalah jejak audit (timeline) dari setiap langkah pada
     * Bagan Alir POS yang telah dieksekusi untuk sebuah sidang, sehingga
     * seluruh pihak dapat menelusuri riwayat proses secara transparan.
     */
    public function up(): void
    {
        Schema::create('sidang_riwayats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('langkah_ke'); // 1-16 sesuai Bagan Alir POS
            $table->string('judul');
            $table->text('keterangan')->nullable();
            $table->string('role')->nullable(); // peran pelaksana pada saat itu
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidang_riwayats');
    }
};
