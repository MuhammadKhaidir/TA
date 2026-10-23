<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Pengguna dapat masuk memakai email atau nomor induk (NIM/NIP).
        $kolom = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'nomor_induk';

        $berhasil = Auth::attempt([
            $kolom => $data['login'],
            'password' => $data['password'],
            'aktif' => true,
        ], $request->boolean('ingat'));

        if (! $berhasil) {
            throw ValidationException::withMessages([
                'login' => 'Identitas atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        $request->session()->regenerate();

        Aktivitas::catat('auth.login', 'Masuk ke sistem', null, Auth::user());

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Aktivitas::catat('auth.logout', 'Keluar dari sistem', null, $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
