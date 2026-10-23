<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $table->foreignId('dokumen_id')->nullable()->constrained('dokumen_pengajuan')->cascadeOnDelete();
            $table->foreignId('pemeriksa_id')->constrained('users')->restrictOnDelete();
            $table->string('peran', 10);
            $table->string('keputusan', 10);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['pemeriksa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan');
    }
};
