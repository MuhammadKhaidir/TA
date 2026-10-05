<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nim' => ['required', 'string', 'max:30', 'unique:mahasiswas,nim'],
            'prodi' => ['required', 'string', 'max:255'],
            'angkatan' => ['required', 'digits:4'],
        ]);

        // Peran selalu "mahasiswa" — sengaja tidak diambil dari input form.
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // di-hash otomatis oleh cast 'hashed' di model User
            ]);
            $user->assignRole('mahasiswa');

            Mahasiswa::create([
                'user_id' => $user->id,
                'nim' => $data['nim'],
                'nama' => $data['name'],
                'prodi' => $data['prodi'],
                'angkatan' => $data['angkatan'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('mahasiswa.dashboard');
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
