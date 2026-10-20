<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti administrasi TA dan KP. TA dan KP memakai SATU tabel `pengajuans`
 * (kolom `jenis`) supaya alur, status, dokumen, dan hak aksesnya tidak terduplikasi.
 *
 * Tabel lama modul sidang (sidangs, sidang_pengujis, dokumens, sidang_riwayats)
 * sengaja TIDAK disentuh: datanya tetap aman sampai pihak kampus memutuskan
 * untuk mengarsipkan atau menghapusnya lewat migrasi tersendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Daftar persyaratan yang dikelola Admin: dokumen pengajuan TA/KP dan lampiran laporan KP.
        Schema::create('persyaratans', function (Blueprint $table) {
            $table->id();
            $table->string('jenis'); // ta_pengajuan | kp_pengajuan | kp_lampiran
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('wajib')->default(true);
            $table->string('waktu_penyerahan')->nullable(); // kapan harus diserahkan (teks bebas)
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->cascadeOnDelete(); // null = semua prodi
            $table->timestamps();
        });

        Schema::create('pengajuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prodi_id')->constrained('prodis')->restrictOnDelete();
            $table->foreignId('periode_akademik_id')->nullable()->constrained('periode_akademiks')->nullOnDelete();
            $table->string('jenis'); // ta | kp
            $table->string('status')->default('draft');
            // Dua dimensi persetujuan yang sengaja dipisah.
            $table->string('status_administrasi')->default('menunggu');
            $table->string('status_akademik')->default('menunggu');
            $table->foreignId('dospem_id')->nullable()->constrained('dosens')->nullOnDelete();

            // TA
            $table->string('judul')->nullable();

            // KP (kolom di bawah null untuk pengajuan TA)
            $table->string('instansi_nama')->nullable();
            $table->text('instansi_alamat')->nullable();
            $table->string('bidang_kegiatan')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            // Pembimbing lapangan hanya data kontak, bukan akun login.
            $table->string('pembimbing_lapangan_nama')->nullable();
            $table->string('pembimbing_lapangan_kontak')->nullable();

            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_id', 'jenis']);
            $table->index(['prodi_id', 'status']);
        });

        Schema::create('pengajuan_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persyaratan_id')->nullable()->constrained('persyaratans')->nullOnDelete();
            $table->string('file_path');
            $table->string('nama_file_asli')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pengajuan_id', 'persyaratan_id']);
        });

        Schema::create('template_sks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->longText('isi'); // HTML dengan penanda {{nama_mahasiswa}}, dst.
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('penomoran_sk', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->unsignedInteger('terakhir')->default(0);
        });

        Schema::create('surat_keputusans', function (Blueprint $table) {
            $table->id();
            // unique + restrict: satu pengajuan hanya bisa punya satu SK, dan SK tidak ikut terhapus diam-diam.
            $table->foreignId('pengajuan_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('template_sk_id')->nullable()->constrained('template_sks')->nullOnDelete();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->string('file_path');
            $table->longText('isi_html'); // salinan isi saat terbit, tahan terhadap perubahan template
            $table->foreignId('diterbitkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('jenis_surats', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->text('persyaratan')->nullable();
            $table->string('template_path')->nullable(); // file template kosong untuk diunduh mahasiswa (opsional)
            $table->string('template_nama')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('surat_permohonans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jenis_surat_id')->constrained('jenis_surats')->restrictOnDelete();
            $table->string('status')->default('diajukan'); // diajukan | diproses | selesai | ditolak
            $table->text('catatan_mahasiswa')->nullable();
            $table->text('catatan_operator')->nullable();
            $table->string('nomor_surat')->nullable();
            $table->string('file_path')->nullable();
            $table->string('nama_file_asli')->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('struktur_laporans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pengaturans', function (Blueprint $table) {
            $table->id();
            $table->string('kunci')->unique();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });

        // Satu tabel untuk jejak audit pengajuan (riwayat + catatan revisi) sekaligus log aktivitas Admin.
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('peran')->nullable();
            $table->string('aksi');
            $table->text('keterangan')->nullable();
            $table->nullableMorphs('subjek');
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        foreach ([
            'sk.format_nomor' => null,
            'sk.kota' => null,
            'sk.penandatangan_nama' => null,
            'sk.penandatangan_jabatan' => null,
            'bimbingan.minimal_ta' => '12', // dipertahankan dari aturan POS pada implementasi lama
            'bimbingan.minimal_kp' => null, // belum ditetapkan kampus
            'kp.pengumpulan_lampiran_aktif' => '0',
        ] as $kunci => $nilai) {
            DB::table('pengaturans')->insert(['kunci' => $kunci, 'nilai' => $nilai, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach ([
            'log_aktivitas', 'pengaturans', 'struktur_laporans', 'surat_permohonans', 'jenis_surats',
            'surat_keputusans', 'penomoran_sk', 'template_sks', 'pengajuan_dokumens', 'pengajuans', 'persyaratans',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
