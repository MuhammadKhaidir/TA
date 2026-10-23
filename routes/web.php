<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\Mahasiswa;
use App\Http\Controllers\Operator;
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

    // Berkas privat: akses diperiksa policy (pemilik, dosen pembimbing, operator).
    Route::get('/berkas/dokumen/{dokumen}', [BerkasController::class, 'dokumen'])->name('berkas.dokumen');
    Route::get('/berkas/resmi/{dokumenResmi}', [BerkasController::class, 'resmi'])->name('berkas.resmi');

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
        Route::get('/riwayat', [Dosen\RiwayatController::class, 'index'])->name('riwayat.index');
    });

    /*
    | Operator Akademik
    */
    Route::middleware('role:operator')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'operator'])->name('dashboard');

        Route::get('/verifikasi', [Operator\VerifikasiController::class, 'index'])->name('verifikasi.index');
        Route::get('/verifikasi/{pengajuan}', [Operator\VerifikasiController::class, 'show'])->name('verifikasi.show');
        Route::post('/verifikasi/{pengajuan}', [Operator\VerifikasiController::class, 'store'])->name('verifikasi.store');

        Route::get('/pengajuan', [Operator\PengajuanController::class, 'index'])->name('pengajuan.index');
        Route::get('/pengajuan/{pengajuan}', [Operator\PengajuanController::class, 'show'])->name('pengajuan.show');
        Route::patch('/pengajuan/{pengajuan}/pembimbing', [Operator\PengajuanController::class, 'pembimbing'])->name('pengajuan.pembimbing');

        Route::get('/dokumen-resmi', [Operator\DokumenResmiController::class, 'index'])->name('dokumen-resmi.index');
        Route::get('/dokumen-resmi/{pengajuan}', [Operator\DokumenResmiController::class, 'show'])->name('dokumen-resmi.show');
        Route::post('/dokumen-resmi/{pengajuan}', [Operator\DokumenResmiController::class, 'store'])->name('dokumen-resmi.store');
        Route::post('/dokumen-resmi/{pengajuan}/arsipkan', [Operator\DokumenResmiController::class, 'arsipkan'])->name('dokumen-resmi.arsipkan');
        Route::delete('/dokumen-resmi/{pengajuan}/{dokumenResmi}', [Operator\DokumenResmiController::class, 'destroy'])->name('dokumen-resmi.destroy');

        Route::get('/arsip', [Operator\ArsipController::class, 'index'])->name('arsip.index');
        Route::get('/arsip/{pengajuan}', [Operator\ArsipController::class, 'show'])->name('arsip.show');
    });

    /*
    | Admin
    */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

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

        Route::get('/log-aktivitas', [Admin\LogAktivitasController::class, 'index'])->name('log.index');
    });
});
