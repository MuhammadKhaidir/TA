<?php

namespace App\Http\Controllers\Operator;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Services\PengajuanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifikasiController extends Controller
{
    public function __construct(private PengajuanWorkflow $workflow) {}

    /** Antrean pengajuan yang menunggu verifikasi administrasi. */
    public function index(): View
    {
        $daftar = Pengajuan::with(['mahasiswa.programStudi', 'pembimbing'])
            ->status(StatusPengajuan::Diajukan)
            ->orderBy('diajukan_at')
            ->paginate(15);

        return view('operator.verifikasi.index', compact('daftar'));
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing', 'riwayat.user', 'dokumenResmi']);

        return view('operator.verifikasi.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }

    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('verifikasi', $pengajuan);

        $data = $request->validate([
            'keputusan' => ['nullable', 'array'],
            'keputusan.*' => ['nullable', 'string'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:2000'],
            'catatan_umum' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->periksa(
            $pengajuan,
            $request->user(),
            Role::Operator,
            $data['keputusan'] ?? [],
            $data['catatan'] ?? [],
            $data['catatan_umum'] ?? null,
        );

        return redirect()->route('operator.verifikasi.index')->with('sukses', 'Hasil verifikasi tersimpan.');
    }
}
