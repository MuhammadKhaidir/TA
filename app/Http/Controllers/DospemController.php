<?php

namespace App\Http\Controllers;

use App\Models\Bimbingan;
use App\Models\Pengajuan;
use App\Services\BimbinganService;
use App\Services\PengajuanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Aksi Dosen Pembimbing. Hanya pada pengajuan/bimbingan mahasiswa yang ditugaskan kepadanya. */
class DospemController extends Controller
{
    public function __construct(
        private readonly PengajuanService $service,
        private readonly BimbinganService $bimbingan,
    ) {}

    public function setujui(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('periksa', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->setujuiAkademik($pengajuan, Auth::user()),
            'Pengajuan disetujui secara akademik.',
        );
    }

    public function revisi(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('periksa', $pengajuan);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan revisi wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->revisiAkademik($pengajuan, Auth::user(), $data['catatan']),
            'Permintaan revisi dikirim ke mahasiswa.',
        );
    }

    public function validasiBimbingan(Request $request, Bimbingan $bimbingan): RedirectResponse
    {
        Gate::authorize('validasi', $bimbingan);
        $data = $request->validate([
            'keputusan' => ['required', 'in:validasi,perbaiki'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->jalankan(
            fn () => $this->bimbingan->validasi($bimbingan, Auth::user(), $data['keputusan'] === 'validasi', $data['catatan'] ?? null),
            'Bimbingan diperbarui.',
        );
    }
}
