<?php

use App\Enums\Peran;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BimbinganController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DospemController;
use App\Http\Controllers\KartuKonsultasiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\SidangController;
use App\Http\Controllers\SkController;
use App\Http\Controllers\SuratBalasanController;
use App\Http\Controllers\SuratController;
use App\Http\Controllers\SuratPengantarController;
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

    // Revisi: kartu konsultasi, surat balasan, pembatalan, sidang, notifikasi. Policy yang menentukan akses.
    Route::get('/pengajuan/{pengajuan}/kartu-konsultasi', [KartuKonsultasiController::class, 'unduh'])->name('pengajuan.kartu.unduh');
    Route::get('/pengajuan/{pengajuan}/surat-balasan', [SuratBalasanController::class, 'unduh'])->name('balasan.unduh');
    Route::post('/pengajuan/{pengajuan}/batalkan', [PengajuanController::class, 'batalkanPengajuan'])->name('pengajuan.batalkan');

    Route::get('/sidang', [SidangController::class, 'index'])->name('sidang.index');
    Route::get('/sidang/{sidang}', [SidangController::class, 'show'])->name('sidang.show');
    Route::get('/sidang-dokumen/{dokumen}', [SidangController::class, 'unduhDokumen'])->name('sidang.dokumen.unduh');
    Route::post('/sidang/{sidang}/batalkan', [SidangController::class, 'batalkan'])->name('sidang.batalkan');

    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');

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

        // Surat pengantar TA, surat balasan, pengajuan ulang
        Route::post('/pengajuan/{pengajuan}/pengantar', [SuratPengantarController::class, 'simpan'])->name('pengantar.simpan');
        Route::post('/pengajuan/{pengajuan}/pengantar/kirim', [SuratPengantarController::class, 'kirim'])->name('pengantar.kirim');
        Route::post('/pengajuan/{pengajuan}/balasan', [SuratBalasanController::class, 'unggah'])->name('balasan.unggah');
        Route::post('/pengajuan/{pengajuan}/ajukan-ulang', [PengajuanController::class, 'ajukanUlang'])->name('pengajuan.ajukan-ulang');

        // Sidang TA
        Route::post('/pengajuan/{pengajuan}/sidang', [SidangController::class, 'store'])->name('sidang.store');
        Route::post('/sidang/{sidang}/dokumen/{persyaratan}', [SidangController::class, 'unggah'])->name('sidang.unggah');
        Route::delete('/sidang-dokumen/{dokumen}', [SidangController::class, 'hapusDokumen'])->name('sidang.dokumen.hapus');
        Route::post('/sidang/{sidang}/kirim', [SidangController::class, 'kirim'])->name('sidang.kirim');
        Route::post('/sidang/{sidang}/ajukan-ulang', [SidangController::class, 'ajukanUlang'])->name('sidang.ajukan-ulang');

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
        Route::post('/sidang/{sidang}/setujui', [SidangController::class, 'setujui'])->name('sidang.setujui');
        Route::post('/sidang/{sidang}/revisi', [SidangController::class, 'revisi'])->name('sidang.revisi');
    });

    // ------------------------------------------------------------
    // Operator Akademik
    // ------------------------------------------------------------
    Route::middleware('role:'.Peran::OperatorAkademik->value)->prefix('operator')->name('operator.')->group(function () {
        Route::get('/', [DashboardController::class, 'operator'])->name('dashboard');
        Route::post('/pengajuan/{pengajuan}/dospem', [OperatorController::class, 'tetapkanDospem'])->name('pengajuan.dospem');
        Route::post('/pengajuan/{pengajuan}/selesaikan-kp', [OperatorController::class, 'selesaikanKp'])->name('pengajuan.selesaikan-kp');
        Route::get('/pengajuan/{pengajuan}/sk/pratinjau', [OperatorController::class, 'pratinjauSk'])->name('sk.pratinjau');
        Route::post('/pengajuan/{pengajuan}/sk', [OperatorController::class, 'terbitkanSk'])->name('sk.terbitkan');
        Route::post('/surat/{surat}/proses', [OperatorController::class, 'prosesSurat'])->name('surat.proses');
    });

    // ------------------------------------------------------------
    // Tugas administrasi yang pelakunya dapat diatur lewat Pengaturan [K-01].
    // Operator Akademik atau Admin boleh melewati middleware; policy (HakAkses) yang memutuskan
    // apakah pengguna memegang tugasnya. Dengan pengaturan bawaan, Admin tidak memegang tugas apa pun di sini.
    // ------------------------------------------------------------
    Route::middleware('role:'.Peran::OperatorAkademik->value.'|'.Peran::Admin->value)->prefix('operator')->name('operator.')->group(function () {
        Route::post('/pengajuan/{pengajuan}/verifikasi', [OperatorController::class, 'verifikasi'])->name('pengajuan.verifikasi');
        Route::post('/pengajuan/{pengajuan}/kembalikan', [OperatorController::class, 'kembalikan'])->name('pengajuan.kembalikan');
        Route::post('/pengajuan/{pengajuan}/sk/catat', [OperatorController::class, 'catatSk'])->name('sk.catat');

        Route::post('/pengantar/{surat}/periksa', [SuratPengantarController::class, 'periksa'])->name('pengantar.periksa');
        Route::post('/pengantar/{surat}/kembalikan', [SuratPengantarController::class, 'kembalikan'])->name('pengantar.kembalikan');
        Route::post('/pengantar/{surat}/proses', [SuratPengantarController::class, 'proses'])->name('pengantar.proses');
        Route::post('/pengantar/{surat}/selesai', [SuratPengantarController::class, 'selesai'])->name('pengantar.selesai');
        Route::post('/balasan/{surat}/validasi', [SuratBalasanController::class, 'validasi'])->name('balasan.validasi');

        Route::post('/sidang/{sidang}/verifikasi', [SidangController::class, 'verifikasi'])->name('sidang.verifikasi');
        Route::post('/sidang/{sidang}/kembalikan', [SidangController::class, 'kembalikan'])->name('sidang.kembalikan');
        Route::post('/sidang/{sidang}/jadwalkan', [SidangController::class, 'jadwalkan'])->name('sidang.jadwalkan');
        Route::post('/sidang/{sidang}/selesai', [SidangController::class, 'selesai'])->name('sidang.selesai');
    });
});
