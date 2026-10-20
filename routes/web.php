<?php

use App\Enums\Peran;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BimbinganController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DospemController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\SkController;
use App\Http\Controllers\SuratController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.process');

// Empat aktor saja: mahasiswa, dospem, operator_akademik (halaman web) dan admin (panel Filament di /admin).
Route::middleware(['auth', 'akun.aktif'])->group(function () {

    // Halaman bersama: otorisasi tiap berkas/pengajuan ditangani policy (bukan sekadar role).
    Route::get('/pengajuan/{pengajuan}', [PengajuanController::class, 'show'])->name('pengajuan.show');
    Route::get('/pengajuan/dokumen/{dokumen}', [PengajuanController::class, 'unduhDokumen'])->name('pengajuan.dokumen.unduh');
    Route::get('/pengajuan/{pengajuan}/sk', [SkController::class, 'unduhSk'])->name('pengajuan.sk.unduh');
    Route::get('/surat/{surat}/unduh', [SkController::class, 'unduhSurat'])->name('surat.unduh');
    Route::get('/surat-template/{jenis}', [SkController::class, 'unduhTemplateSurat'])->name('surat.template');
    Route::get('/bimbingan/{bimbingan}/bukti', [BimbinganController::class, 'bukti'])->name('bimbingan.bukti');

    // ------------------------------------------------------------
    // Mahasiswa
    // ------------------------------------------------------------
    Route::middleware('role:'.Peran::Mahasiswa->value)->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/', [DashboardController::class, 'mahasiswa'])->name('dashboard');

        Route::post('/pengajuan/{jenis}', [PengajuanController::class, 'store'])->name('pengajuan.store');
        Route::put('/pengajuan/{pengajuan}', [PengajuanController::class, 'update'])->name('pengajuan.update');
        Route::post('/pengajuan/{pengajuan}/kirim', [PengajuanController::class, 'kirim'])->name('pengajuan.kirim');
        Route::delete('/pengajuan/{pengajuan}', [PengajuanController::class, 'destroy'])->name('pengajuan.destroy');
        Route::post('/pengajuan/{pengajuan}/dokumen/{persyaratan}', [PengajuanController::class, 'unggah'])->name('pengajuan.dokumen.unggah');
        Route::delete('/dokumen/{dokumen}', [PengajuanController::class, 'hapusDokumen'])->name('pengajuan.dokumen.hapus');

        Route::post('/pengajuan/{pengajuan}/surat', [SuratController::class, 'store'])->name('surat.store');

        Route::post('/bimbingan/{jenis}', [BimbinganController::class, 'store'])->name('bimbingan.store');
        Route::delete('/bimbingan/{bimbingan}', [BimbinganController::class, 'destroy'])->name('bimbingan.destroy');
    });

    // ------------------------------------------------------------
    // Dosen Pembimbing
    // ------------------------------------------------------------
    Route::middleware('role:'.Peran::Dospem->value)->prefix('dospem')->name('dospem.')->group(function () {
        Route::get('/', [DashboardController::class, 'dospem'])->name('dashboard');
        Route::post('/pengajuan/{pengajuan}/setujui', [DospemController::class, 'setujui'])->name('pengajuan.setujui');
        Route::post('/pengajuan/{pengajuan}/revisi', [DospemController::class, 'revisi'])->name('pengajuan.revisi');
        Route::post('/bimbingan/{bimbingan}/validasi', [DospemController::class, 'validasiBimbingan'])->name('bimbingan.validasi');
    });

    // ------------------------------------------------------------
    // Operator Akademik
    // ------------------------------------------------------------
    Route::middleware('role:'.Peran::OperatorAkademik->value)->prefix('operator')->name('operator.')->group(function () {
        Route::get('/', [DashboardController::class, 'operator'])->name('dashboard');
        Route::post('/pengajuan/{pengajuan}/verifikasi', [OperatorController::class, 'verifikasi'])->name('pengajuan.verifikasi');
        Route::post('/pengajuan/{pengajuan}/kembalikan', [OperatorController::class, 'kembalikan'])->name('pengajuan.kembalikan');
        Route::post('/pengajuan/{pengajuan}/dospem', [OperatorController::class, 'tetapkanDospem'])->name('pengajuan.dospem');
        Route::post('/pengajuan/{pengajuan}/selesaikan-kp', [OperatorController::class, 'selesaikanKp'])->name('pengajuan.selesaikan-kp');
        Route::get('/pengajuan/{pengajuan}/sk/pratinjau', [OperatorController::class, 'pratinjauSk'])->name('sk.pratinjau');
        Route::post('/pengajuan/{pengajuan}/sk', [OperatorController::class, 'terbitkanSk'])->name('sk.terbitkan');
        Route::post('/surat/{surat}/proses', [OperatorController::class, 'prosesSurat'])->name('surat.proses');
    });
});
