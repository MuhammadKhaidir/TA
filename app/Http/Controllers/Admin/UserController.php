<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manajemen Pengguna. Data di tabel users adalah satu-satunya sumber nama, nomor induk,
 * program studi, dan angkatan; halaman lain hanya membacanya lewat relasi.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'role' => ['nullable', Rule::in(array_keys(Role::pilihan()))],
            'prodi' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $daftar = User::with('programStudi')
            ->when($filter['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
            ->when($filter['prodi'] ?? null, fn ($q, $v) => $q->where('program_studi_id', $v))
            ->when($filter['q'] ?? null, function ($q, $kata) {
                $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$kata}%")
                    ->orWhere('email', 'like', "%{$kata}%")
                    ->orWhere('nomor_induk', 'like', "%{$kata}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'prodi' => ProgramStudi::orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => null, 'prodi' => $this->daftarProdi()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $user = User::create($data);

        Aktivitas::catat('pengguna.buat', "Menambah pengguna {$user->name} ({$user->role->label()})", $user);

        return redirect()->route('admin.users.index')->with('sukses', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'prodi' => $this->daftarProdi($user)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validasi($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== $user->role->value || ! $data['aktif'])) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat mengubah role atau menonaktifkan akun Anda sendiri.',
            ]);
        }

        // Kata sandi hanya diganti bila diisi.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        Aktivitas::catat('pengguna.ubah', "Mengubah data pengguna {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('sukses', 'Data pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('galat', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $nama = $user->name;

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->with('galat', "{$nama} sudah memiliki data pengajuan atau pemeriksaan sehingga tidak dapat dihapus. Nonaktifkan akunnya melalui menu ubah.");
        }

        Aktivitas::catat('pengguna.hapus', "Menghapus pengguna {$nama}");

        return redirect()->route('admin.users.index')->with('sukses', 'Pengguna dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?User $user = null): array
    {
        $mahasiswa = $request->input('role') === Role::Mahasiswa->value;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'nomor_induk' => ['required', 'string', 'max:30', Rule::unique('users', 'nomor_induk')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(Role::pilihan()))],
            'program_studi_id' => [$mahasiswa ? 'required' : 'nullable', 'integer', 'exists:program_studi,id'],
            'angkatan' => [$mahasiswa ? 'required' : 'nullable', 'integer', 'min:1990', 'max:2100'],
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        $data['aktif'] = $request->boolean('aktif');

        // Angkatan hanya bermakna untuk mahasiswa.
        if (! $mahasiswa) {
            $data['angkatan'] = null;
        }

        return $data;
    }

    private function daftarProdi(?User $user = null)
    {
        return ProgramStudi::query()
            ->where(fn ($q) => $q->where('aktif', true)->orWhere('id', $user?->program_studi_id))
            ->orderBy('nama')
            ->get();
    }
}
