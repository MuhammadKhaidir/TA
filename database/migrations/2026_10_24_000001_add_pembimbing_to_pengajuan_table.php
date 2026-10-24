<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur dosen pembimbing.
 *
 * - pembimbing_id          : dosen pembimbing yang SAH (sudah menerima atau ditetapkan operator); null bila belum ada.
 * - pembimbing_pilihan_id  : khusus TA, dosen yang dipilih mahasiswa saat pengajuan (tetap tersimpan sebagai catatan).
 * - status_pembimbing      : belum | menunggu | diterima | ditolak | ditetapkan.
 *
 * Migrasi tambahan: pengajuan lama yang sudah punya pembimbing dianggap sah
 * (KP: ditetapkan, TA: diterima) agar akses dosen tidak tiba-tiba hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan', function (Blueprint $table) {
            $table->foreignId('pembimbing_pilihan_id')->nullable()->after('pembimbing_id')
                ->constrained('users')->restrictOnDelete();
            $table->string('status_pembimbing', 20)->default('belum')->after('pembimbing_pilihan_id')->index();
        });

        DB::table('pengajuan')
            ->whereNotNull('pembimbing_id')
            ->where('jenis', 'kp')
            ->update(['status_pembimbing' => 'ditetapkan']);

        DB::table('pengajuan')
            ->whereNotNull('pembimbing_id')
            ->where('jenis', 'ta')
            ->update(['status_pembimbing' => 'diterima', 'pembimbing_pilihan_id' => DB::raw('pembimbing_id')]);
    }

    public function down(): void
    {
        Schema::table('pengajuan', function (Blueprint $table) {
            $table->dropIndex(['status_pembimbing']);
            $table->dropConstrainedForeignId('pembimbing_pilihan_id');
            $table->dropColumn('status_pembimbing');
        });
    }
};
