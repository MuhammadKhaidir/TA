<?php

namespace App\Http\Controllers;

use App\Models\JenisSurat;
use App\Models\Pengajuan;
use App\Services\SuratService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SuratController extends Controller
{
    public function __construct(private readonly SuratService $service) {}

    /** Mahasiswa mengajukan permohonan surat untuk pengajuan KP miliknya. */
    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        $data = $request->validate([
            'jenis_surat_id' => ['required', 'integer', 'exists:jenis_surats,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], ['jenis_surat_id.required' => 'Pilih jenis surat.']);

        return $this->jalankan(
            fn () => $this->service->ajukan($pengajuan, JenisSurat::findOrFail($data['jenis_surat_id']), $data['catatan'] ?? null, Auth::user()),
            'Permohonan surat diajukan.',
            route('mahasiswa.dashboard').'#surat-kp',
        );
    }
}
