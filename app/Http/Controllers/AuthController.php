<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Peta peran -> nama route dashboard masing-masing.
     *
     * @var array<string, string>
     */
    private const DASHBOARD_ROUTES = [
        'mahasiswa' => 'mahasiswa.dashboard',
        'sekdep_koor_prodi' => 'sekdep.dashboard',
        'penata' => 'penata.dashboard',
        'pengelola_layanan' => 'pengelola.dashboard',
        'pengadministrasi_perkantoran' => 'administrasi.dashboard',
        'dosen_penguji' => 'dosen.dashboard',
    ];

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email atau password yang dimasukkan salah.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return $this->redirectByRole();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole()
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return redirect('/admin');
        }

        foreach (self::DASHBOARD_ROUTES as $role => $routeName) {
            if ($user->hasRole($role)) {
                return redirect()->route($routeName);
            }
        }

        Auth::logout();

        return redirect()->route('login')->withErrors([
            'email' => 'Akun belum memiliki peran yang valid. Hubungi Administrator.',
        ]);
    }
}
