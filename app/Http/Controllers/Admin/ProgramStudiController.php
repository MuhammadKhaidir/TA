<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Data Master: program studi. Dipakai pada data mahasiswa/dosen dan seluruh tampilannya. */
class ProgramStudiController extends Controller
{
    public const JENJANG = ['D3', 'D4', 'S1', 'S2', 'S3'];

    public function index(): View
    {
        $daftar = ProgramStudi::withCount('pengguna')->orderBy('nama')->get();

        return view('admin.program-studi.index', compact('daftar'));
    }

    public function create(): View
    {
        return view('admin.program-studi.form', ['prodi' => null, 'jenjang' => self::JENJANG]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prodi = ProgramStudi::create($this->validasi($request));

        Aktivitas::catat('prodi.buat', "Menambah program studi {$prodi->namaLengkap()}", $prodi);

        return redirect()->route('admin.program-studi.index')->with('sukses', 'Program studi ditambahkan.');
    }

    public function edit(ProgramStudi $programStudi): View
    {
        return view('admin.program-studi.form', ['prodi' => $programStudi, 'jenjang' => self::JENJANG]);
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        $programStudi->update($this->validasi($request, $programStudi));

        Aktivitas::catat('prodi.ubah', "Mengubah program studi {$programStudi->namaLengkap()}", $programStudi);

        return redirect()->route('admin.program-studi.index')->with('sukses', 'Program studi diperbarui.');
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        if ($programStudi->pengguna()->exists()) {
            return back()->with('galat', 'Program studi masih dipakai pengguna. Nonaktifkan atau pindahkan penggunanya terlebih dahulu.');
        }

        $nama = $programStudi->namaLengkap();
        $programStudi->delete();

        Aktivitas::catat('prodi.hapus', "Menghapus program studi {$nama}");

        return redirect()->route('admin.program-studi.index')->with('sukses', 'Program studi dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?ProgramStudi $prodi = null): array
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('program_studi', 'kode')->ignore($prodi?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'jenjang' => ['required', Rule::in(self::JENJANG)],
        ]);

        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
