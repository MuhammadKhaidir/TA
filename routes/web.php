<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/mahasiswa', function () {
        abort_unless(auth()->user()->hasRole('mahasiswa'), 403);

        return view('mahasiswa.dashboard', [
            'mahasiswa' => auth()->user()->mahasiswa,
        ]);
    })->name('mahasiswa.dashboard');
});
