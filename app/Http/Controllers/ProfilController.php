<?php

namespace App\Http\Controllers;

use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil dipakai semua role. Nama, email, nomor induk, role, program studi, dan angkatan
 * hanya DIBACA dari tabel users (diubah admin lewat Manajemen Pengguna). Pengguna sendiri
 * hanya dapat mengubah nomor HP dan kata sandi.
 */
class ProfilController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('programStudi');

        return view('profil.show', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
        ]);

        $request->user()->update(['no_hp' => $data['no_hp'] ?? null]);

        Aktivitas::catat('profil.ubah', 'Memperbarui nomor HP pada profil', $request->user());

        return back()->with('sukses', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        Aktivitas::catat('profil.password', 'Mengganti kata sandi', $request->user());

        return back()->with('sukses', 'Kata sandi berhasil diganti.');
    }
}
