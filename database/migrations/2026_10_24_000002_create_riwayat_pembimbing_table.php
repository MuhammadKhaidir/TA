<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pembimbing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $table->foreignId('dosen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pembimbing');
    }
};
