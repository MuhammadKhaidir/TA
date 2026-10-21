<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - `surat_keputusans.sumber`: SK diterbitkan dari website ('website') atau dicatat dari SK resmi
 *   yang terbit offline ('offline') [K-14]. Baris lama otomatis 'website'.
 * - `persyaratans.tautan_otomatis`: dokumen sidang yang sudah dimiliki sistem (mis. SK TA) ditautkan
 *   otomatis, tidak perlu diunggah ulang mahasiswa.
 * - Baris pengaturan baru. Nilai awalnya mengikuti PDF revisi; hal bertanda [K-xx] belum
 *   dikonfirmasi fakultas sehingga disimpan sebagai pengaturan, bukan ditanam di kode.
 */
return new class extends Migration
{
    private const PENGATURAN = [
        'sk.mode' => 'website', // website | offline [K-14]
        'ta.pembimbing_kedua_wajib' => '1', // 1 = pembimbing 2 wajib ditetapkan
        'kartu.butuh_sk' => '1', // 1 = kartu konsultasi terkunci sebelum SK terbit [K-04]
        'sidang.durasi_menit' => '90',
        'sidang.minimal_penguji' => '1',
        'akses.periksa_kelengkapan' => 'operator_akademik',
        'akses.validasi_surat_balasan' => 'operator_akademik',
        'akses.proses_surat_pengantar' => 'admin',
        'akses.catat_sk' => 'admin',
        'akses.jadwalkan_sidang' => 'admin',
        'akses.konfirmasi_sidang_selesai' => 'admin',
    ];

    public function up(): void
    {
        Schema::table('surat_keputusans', function (Blueprint $table) {
            $table->string('sumber')->default('website')->after('nomor');
        });

        Schema::table('persyaratans', function (Blueprint $table) {
            $table->string('tautan_otomatis')->nullable()->after('waktu_penyerahan'); // sk_ta | kartu_konsultasi
        });

        $now = now();
        foreach (self::PENGATURAN as $kunci => $nilai) {
            DB::table('pengaturans')->insertOrIgnore(['kunci' => $kunci, 'nilai' => $nilai, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('pengaturans')->whereIn('kunci', array_keys(self::PENGATURAN))->delete();

        Schema::table('persyaratans', fn (Blueprint $table) => $table->dropColumn('tautan_otomatis'));
        Schema::table('surat_keputusans', fn (Blueprint $table) => $table->dropColumn('sumber'));
    }
};
