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

            // jenis_dokumen: App\Enums\JenisDokumen
            $table->string('jenis_dokumen');
            $table->string('nomor_sk')->nullable(); // khusus jenis dokumen SK
            $table->string('file_path');
            $table->string('nama_file_asli')->nullable();
            $table->text('keterangan')->nullable();
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
