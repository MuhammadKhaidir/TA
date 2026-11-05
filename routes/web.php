<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\Mahasiswa;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
| Tamu
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

/*
| Pengguna yang sudah login (semua role)
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'arahkan'])->name('dashboard');

    Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::put('/profil/password', [ProfilController::class, 'password'])->name('profil.password');

    // Berkas privat: akses diperiksa policy (pemilik, dosen pembimbing, admin).
    Route::get('/berkas/dokumen/{dokumen}', [BerkasController::class, 'dokumen'])->name('berkas.dokumen');
    Route::get('/berkas/resmi/{dokumenResmi}', [BerkasController::class, 'resmi'])->name('berkas.resmi');
    Route::get('/berkas/balasan/{suratBalasan}', [BerkasController::class, 'balasan'])->name('berkas.balasan');

    /*
    | Mahasiswa
    */
    Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'mahasiswa'])->name('dashboard');

        Route::controller(Mahasiswa\PengajuanController::class)->prefix('pengajuan')->name('pengajuan.')->group(function () {
            Route::get('/{jenis}', 'index')->whereIn('jenis', ['kp', 'ta'])->name('index');
            Route::get('/{jenis}/buat', 'create')->whereIn('jenis', ['kp', 'ta'])->name('create');
            Route::post('/{jenis}', 'store')->whereIn('jenis', ['kp', 'ta'])->name('store');

            Route::get('/detail/{pengajuan}', 'show')->name('show');
            Route::get('/detail/{pengajuan}/ubah', 'edit')->name('edit');
            Route::put('/detail/{pengajuan}', 'update')->name('update');
            Route::delete('/detail/{pengajuan}', 'destroy')->name('destroy');
            Route::post('/detail/{pengajuan}/ajukan', 'ajukan')->name('ajukan');
        });

        Route::post('/pengajuan/detail/{pengajuan}/dokumen', [Mahasiswa\DokumenController::class, 'store'])->name('dokumen.store');
        Route::post('/pengajuan/detail/{pengajuan}/surat-balasan', [Mahasiswa\SuratBalasanController::class, 'store'])->name('surat-balasan.store');
        Route::get('/pengajuan/detail/{pengajuan}/kartu-konsultasi', [Mahasiswa\KartuKonsultasiController::class, 'unduh'])->name('kartu-konsultasi.unduh');
        Route::get('/status', [Mahasiswa\StatusController::class, 'index'])->name('status.index');
        Route::get('/berkas', [Mahasiswa\BerkasSayaController::class, 'index'])->name('berkas.index');
    });

    /*
    | Dosen
    */
    Route::middleware('role:dosen')->prefix('dosen')->name('dosen.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dosen'])->name('dashboard');

        Route::get('/pemeriksaan', [Dosen\PemeriksaanController::class, 'index'])->name('pemeriksaan.index');
        Route::get('/pemeriksaan/{pengajuan}', [Dosen\PemeriksaanController::class, 'show'])->name('pemeriksaan.show');
        Route::post('/pemeriksaan/{pengajuan}', [Dosen\PemeriksaanController::class, 'store'])->name('pemeriksaan.store');

        Route::get('/bimbingan', [Dosen\BimbinganController::class, 'index'])->name('bimbingan.index');
        Route::post('/bimbingan/{pengajuan}/konfirmasi', [Dosen\BimbinganController::class, 'konfirmasi'])->name('bimbingan.konfirmasi');
        Route::get('/riwayat', [Dosen\RiwayatController::class, 'index'])->name('riwayat.index');
    });

    /*
    | Admin (menjalankan pula seluruh fungsi yang dulu dipegang operator akademik)
    */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        // Verifikasi administrasi, pengelolaan KP/TA, dokumen resmi, dan arsip berkas.
        Route::get('/verifikasi', [Admin\VerifikasiController::class, 'index'])->name('verifikasi.index');
        Route::get('/verifikasi/{pengajuan}', [Admin\VerifikasiController::class, 'show'])->name('verifikasi.show');
        Route::post('/verifikasi/{pengajuan}', [Admin\VerifikasiController::class, 'store'])->name('verifikasi.store');

        Route::get('/pengajuan', [Admin\PengajuanController::class, 'index'])->name('pengajuan.index');
        Route::get('/pengajuan/{pengajuan}', [Admin\PengajuanController::class, 'show'])->name('pengajuan.show');
        Route::patch('/pengajuan/{pengajuan}/pembimbing', [Admin\PengajuanController::class, 'pembimbing'])->name('pengajuan.pembimbing');

        Route::get('/dokumen-resmi', [Admin\DokumenResmiController::class, 'index'])->name('dokumen-resmi.index');
        Route::get('/dokumen-resmi/{pengajuan}', [Admin\DokumenResmiController::class, 'show'])->name('dokumen-resmi.show');
        Route::post('/dokumen-resmi/{pengajuan}', [Admin\DokumenResmiController::class, 'store'])->name('dokumen-resmi.store');
        Route::post('/dokumen-resmi/{pengajuan}/arsipkan', [Admin\DokumenResmiController::class, 'arsipkan'])->name('dokumen-resmi.arsipkan');
        Route::post('/dokumen-resmi/{pengajuan}/surat-pengantar', [Admin\DokumenResmiController::class, 'suratPengantar'])->name('dokumen-resmi.surat-pengantar');
        Route::post('/dokumen-resmi/{pengajuan}/surat-balasan', [Admin\SuratBalasanController::class, 'periksa'])->name('dokumen-resmi.surat-balasan');
        Route::delete('/dokumen-resmi/{pengajuan}/{dokumenResmi}', [Admin\DokumenResmiController::class, 'destroy'])->name('dokumen-resmi.destroy');

        Route::get('/arsip', [Admin\ArsipController::class, 'index'])->name('arsip.index');
        Route::get('/arsip/{pengajuan}', [Admin\ArsipController::class, 'show'])->name('arsip.show');

        // Manajemen pengguna, data master, dan persyaratan.
        Route::resource('pengguna', Admin\UserController::class)
            ->except('show')
            ->parameters(['pengguna' => 'user'])
            ->names('users');

        Route::prefix('data-master')->group(function () {
            Route::resource('program-studi', Admin\ProgramStudiController::class)
                ->except('show')
                ->parameters(['program-studi' => 'programStudi'])
                ->names('program-studi');
        });

        Route::resource('persyaratan', Admin\PersyaratanController::class)
            ->except('show')
            ->parameters(['persyaratan' => 'persyaratan'])
            ->names('persyaratan');
    });
});
