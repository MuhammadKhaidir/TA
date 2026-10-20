<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multi-program-studi: prodi menjadi data dinamis (tabel `prodis`), mahasiswa dan
 * dosen merujuk ke prodi lewat foreign key. Kolom teks lama `prodi` di-backfill
 * ke tabel baru terlebih dulu, baru dihapus (data tidak hilang).
 *
 * Juga menambah: periode akademik, cakupan prodi untuk Operator Akademik, dan
 * flag `aktif` pada pengguna/dosen supaya akun bisa dinonaktifkan tanpa dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodis', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama')->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('periode_akademiks', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique(); // contoh: "Ganjil 2026/2027"
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        // Cakupan Operator Akademik. Operator tanpa baris di sini dan tanpa
        // flag users.semua_prodi tidak melihat data apa pun (tidak ada akses implisit).
        Schema::create('operator_prodi', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prodi_id')->constrained('prodis')->cascadeOnDelete();
            $table->primary(['user_id', 'prodi_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('password');
            $table->boolean('semua_prodi')->default(false)->after('aktif');
        });

        // Data awal nomenklatur; dapat diubah/ditambah Admin lewat panel.
        $now = now();
        foreach (['TI' => 'Teknik Informatika', 'SI' => 'Sistem Informasi', 'SK' => 'Sistem Komputer'] as $kode => $nama) {
            DB::table('prodis')->insert(['kode' => $kode, 'nama' => $nama, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->foreignId('prodi_id')->nullable()->after('nama')->constrained('prodis')->restrictOnDelete();
        });

        Schema::table('dosens', function (Blueprint $table) {
            $table->foreignId('prodi_id')->nullable()->after('nama')->constrained('prodis')->restrictOnDelete();
            $table->boolean('aktif')->default(true)->after('prodi_id');
        });

        foreach (['mahasiswas', 'dosens'] as $tabel) {
            $daftar = DB::table($tabel)->whereNotNull('prodi')->where('prodi', '!=', '')->distinct()->pluck('prodi');
            foreach ($daftar as $namaProdi) {
                DB::table($tabel)->where('prodi', $namaProdi)->update(['prodi_id' => $this->idProdi($namaProdi)]);
            }
        }

        Schema::table('mahasiswas', fn (Blueprint $table) => $table->dropColumn('prodi'));
        Schema::table('dosens', fn (Blueprint $table) => $table->dropColumn('prodi'));
    }

    public function down(): void
    {
        Schema::table('mahasiswas', fn (Blueprint $table) => $table->string('prodi')->nullable()->after('nama'));
        Schema::table('dosens', fn (Blueprint $table) => $table->string('prodi')->nullable()->after('nama'));

        foreach (['mahasiswas', 'dosens'] as $tabel) {
            foreach (DB::table('prodis')->get() as $prodi) {
                DB::table($tabel)->where('prodi_id', $prodi->id)->update(['prodi' => $prodi->nama]);
            }
        }

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prodi_id');
        });
        Schema::table('dosens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prodi_id');
            $table->dropColumn('aktif');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['aktif', 'semua_prodi']));

        Schema::dropIfExists('operator_prodi');
        Schema::dropIfExists('periode_akademiks');
        Schema::dropIfExists('prodis');
    }

    /** Cari prodi berdasarkan nama (tanpa peduli huruf besar/kecil); buat baru bila belum ada. */
    private function idProdi(string $nama): int
    {
        $nama = trim($nama);

        $id = DB::table('prodis')->whereRaw('lower(nama) = ?', [mb_strtolower($nama)])->value('id');
        if ($id) {
            return (int) $id;
        }

        $dasar = Str::of($nama)->explode(' ')->filter()
            ->map(fn ($kata) => Str::upper(Str::substr($kata, 0, 1)))->implode('');
        $dasar = Str::substr($dasar !== '' ? $dasar : 'PRD', 0, 8);

        $kode = $dasar;
        for ($i = 2; DB::table('prodis')->where('kode', $kode)->exists(); $i++) {
            $kode = $dasar.$i;
        }

        $now = now();

        return (int) DB::table('prodis')->insertGetId([
            'kode' => $kode, 'nama' => $nama, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
};
