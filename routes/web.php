<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SidangController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    // Halaman detail sidang & unduh dokumen dapat diakses lintas peran,
    // dengan otorisasi granular ditangani di dalam SidangController.
    Route::get('/sidang/{sidang}', [SidangController::class, 'show'])->name('sidang.show');
    Route::get('/sidang/{sidang}/dokumen/{dokumen}/unduh', [SidangController::class, 'unduhDokumen'])->name('sidang.dokumen.unduh');

    // ------------------------------------------------------------
    // Mahasiswa — Langkah 1
    // ------------------------------------------------------------
    Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/', [DashboardController::class, 'mahasiswa'])->name('dashboard');
        Route::post('/sidang', [SidangController::class, 'ajukan'])->name('sidang.ajukan');
        Route::post('/sidang/{sidang}/dokumen', [SidangController::class, 'unggahDokumen'])->name('sidang.dokumen.unggah');
    });

    // ------------------------------------------------------------
    // SekDep / Koor. Prodi — Langkah 2, 6 (bagian kedua), 10 (bagian kedua), 16
    // ------------------------------------------------------------
    Route::middleware('role:sekdep_koor_prodi')->prefix('sekdep')->name('sekdep.')->group(function () {
        Route::get('/', [DashboardController::class, 'sekdep'])->name('dashboard');
        Route::post('/sidang/{sidang}/konsultasi', [SidangController::class, 'perbaruiKonsultasi'])->name('sidang.konsultasi');
        Route::post('/sidang/{sidang}/usulkan-jadwal', [SidangController::class, 'usulkanJadwal'])->name('sidang.usulkan-jadwal');
        Route::post('/sidang/{sidang}/distribusi-sk', [SidangController::class, 'distribusikanSkKeDosen'])->name('sidang.distribusi-sk');
        Route::post('/sidang/{sidang}/jadwal-ulang', [SidangController::class, 'jadwalkanUlang'])->name('sidang.jadwal-ulang');
        Route::post('/sidang/{sidang}/hubungi-dosen', [SidangController::class, 'hubungiDosen'])->name('sidang.hubungi-dosen');
    });

    // ------------------------------------------------------------
    // Penata — Langkah 3, 4, 6 (bagian pertama), 15
    // ------------------------------------------------------------
    Route::middleware('role:penata')->prefix('penata')->name('penata.')->group(function () {
        Route::get('/', [DashboardController::class, 'penata'])->name('dashboard');
        Route::post('/sidang/{sidang}/verifikasi', [SidangController::class, 'verifikasi'])->name('sidang.verifikasi');
        Route::post('/sidang/{sidang}/teruskan-sk', [SidangController::class, 'teruskanSkKeSekdep'])->name('sidang.teruskan-sk');
        Route::post('/sidang/{sidang}/lapor-sekdep', [SidangController::class, 'laporKeSekdep'])->name('sidang.lapor-sekdep');
    });

    // ------------------------------------------------------------
    // Pengelola Layanan — Langkah 5, 13, 14
    // ------------------------------------------------------------
    Route::middleware('role:pengelola_layanan')->prefix('pengelola')->name('pengelola.')->group(function () {
        Route::get('/', [DashboardController::class, 'pengelola'])->name('dashboard');
        Route::post('/sidang/{sidang}/sahkan-sk', [SidangController::class, 'sahkanSk'])->name('sidang.sahkan-sk');
        Route::post('/sidang/{sidang}/cek-nilai', [SidangController::class, 'cekNilai'])->name('sidang.cek-nilai');
        Route::post('/sidang/{sidang}/lapor-penata', [SidangController::class, 'laporKePenata'])->name('sidang.lapor-penata');
    });

    // ------------------------------------------------------------
    // Pengadministrasi Perkantoran — Langkah 7, 8, 9, 11
    // ------------------------------------------------------------
    Route::middleware('role:pengadministrasi_perkantoran')->prefix('administrasi')->name('administrasi.')->group(function () {
        Route::get('/', [DashboardController::class, 'administrasi'])->name('dashboard');
        Route::post('/sidang/{sidang}/siapkan', [SidangController::class, 'siapkanAdministrasi'])->name('sidang.siapkan');
        Route::post('/sidang/{sidang}/info-jadwal', [SidangController::class, 'informasikanJadwal'])->name('sidang.info-jadwal');
        Route::post('/sidang/{sidang}/cek-berkas', [SidangController::class, 'cekKelengkapanBerkas'])->name('sidang.cek-berkas');
        Route::post('/sidang/{sidang}/serahkan-berkas', [SidangController::class, 'serahkanBerkasUjian'])->name('sidang.serahkan-berkas');
    });

    // ------------------------------------------------------------
    // Dosen Penguji — Langkah 10 (bagian pertama), 12
    // ------------------------------------------------------------
    Route::middleware('role:dosen_penguji')->prefix('dosen')->name('dosen.')->group(function () {
        Route::get('/', [DashboardController::class, 'dosen'])->name('dashboard');
        Route::post('/sidang/{sidang}/konfirmasi-berhalangan', [SidangController::class, 'konfirmasiBerhalangan'])->name('sidang.konfirmasi-berhalangan');
        Route::post('/sidang/{sidang}/input-nilai', [SidangController::class, 'inputNilai'])->name('sidang.input-nilai');
    });
});
