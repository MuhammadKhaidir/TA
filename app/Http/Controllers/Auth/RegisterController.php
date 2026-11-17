<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pendaftaran mandiri khusus mahasiswa. Akun dosen dan admin tetap dibuat admin lewat Manajemen Pengguna.
 *
 * Role dan status aktif ditetapkan di sini (bukan dari input), jadi pendaftar tidak bisa
 * membuat akun dengan role lain meskipun mengirim field "role" secara manual.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'prodi' => ProgramStudi::aktif()->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Email disimpan huruf kecil supaya pengecekan unik tidak bisa dilewati dengan beda huruf besar/kecil.
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'nomor_induk' => ['required', 'string', 'max:30', 'regex:/^[0-9]+$/', Rule::unique('users', 'nomor_induk')],
            'program_studi_id' => ['required', 'integer', Rule::exists('program_studi', 'id')->where('aktif', true)],
            'angkatan' => ['required', 'integer', 'min:1990', 'max:'.now()->year],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nomor_induk.regex' => 'NIM hanya boleh berisi angka.',
            'nomor_induk.unique' => 'NIM ini sudah terdaftar. Jika Anda belum pernah mendaftar, hubungi admin.',
        ], [
            'nomor_induk' => 'NIM',
        ]);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'nomor_induk' => $data['nomor_induk'],
                'program_studi_id' => $data['program_studi_id'],
                'angkatan' => $data['angkatan'],
                'password' => $data['password'],
                'role' => Role::Mahasiswa,
                'aktif' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua pendaftaran dengan data sama yang masuk bersamaan: pengecekan di atas lolos untuk keduanya.
            throw ValidationException::withMessages([
                'nomor_induk' => 'NIM atau email ini baru saja didaftarkan. Silakan masuk, atau hubungi admin.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Langsung ke dashboard mahasiswa (bukan lewat /dashboard yang meneruskan lagi), supaya pesan sukses tidak hilang.
        return redirect()->route('mahasiswa.dashboard')->with('sukses', 'Akun berhasil dibuat. Selamat datang, '.$user->name.'.');
    }
}
