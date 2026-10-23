<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Persyaratan;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan Persyaratan. Form pengajuan mahasiswa, validasi unggahan, dan pembagian
 * pemeriksa (operator/dosen) semuanya dibaca dari tabel persyaratan.
 */
class PersyaratanController extends Controller
{
    public function index(Request $request): View
    {
        $jenis = $request->query('jenis');
        $jenis = in_array($jenis, ['kp', 'ta'], true) ? JenisPengajuan::from($jenis) : null;

        $daftar = Persyaratan::withCount('dokumen')
            ->when($jenis, fn ($q) => $q->untuk($jenis))
            ->orderBy('jenis_pengajuan')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        return view('admin.persyaratan.index', compact('daftar', 'jenis'));
    }

    public function create(): View
    {
        return view('admin.persyaratan.form', ['syarat' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $syarat = Persyaratan::create($this->validasi($request));

        Aktivitas::catat('persyaratan.buat', "Menambah persyaratan {$syarat->nama} ({$syarat->jenis_pengajuan->singkat()})", $syarat);

        return redirect()->route('admin.persyaratan.index')->with('sukses', 'Persyaratan ditambahkan.');
    }

    public function edit(Persyaratan $persyaratan): View
    {
        return view('admin.persyaratan.form', ['syarat' => $persyaratan]);
    }

    public function update(Request $request, Persyaratan $persyaratan): RedirectResponse
    {
        $persyaratan->update($this->validasi($request));

        Aktivitas::catat('persyaratan.ubah', "Mengubah persyaratan {$persyaratan->nama} ({$persyaratan->jenis_pengajuan->singkat()})", $persyaratan);

        return redirect()->route('admin.persyaratan.index')->with('sukses', 'Persyaratan diperbarui.');
    }

    public function destroy(Persyaratan $persyaratan): RedirectResponse
    {
        if ($persyaratan->dokumen()->exists()) {
            return back()->with('galat', 'Persyaratan ini sudah memiliki dokumen yang diunggah mahasiswa. Nonaktifkan saja agar riwayat tetap utuh.');
        }

        $nama = $persyaratan->nama;
        $persyaratan->delete();

        Aktivitas::catat('persyaratan.hapus', "Menghapus persyaratan {$nama}");

        return redirect()->route('admin.persyaratan.index')->with('sukses', 'Persyaratan dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'jenis_pengajuan' => ['required', Rule::in(array_keys(JenisPengajuan::pilihan()))],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'pemeriksa' => ['required', Rule::in([Role::Operator->value, Role::Dosen->value])],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'ukuran_maks_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'ekstensi' => ['required', 'array', 'min:1'],
            'ekstensi.*' => ['string', Rule::in(Persyaratan::EKSTENSI_DIIZINKAN)],
        ]);

        $data['ekstensi'] = implode(',', $data['ekstensi']);
        $data['wajib'] = $request->boolean('wajib');
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
