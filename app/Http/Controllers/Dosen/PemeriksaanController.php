<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Services\PengajuanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PemeriksaanController extends Controller
{
    public function __construct(private PengajuanWorkflow $workflow) {}

    /** Antrean berkas mahasiswa bimbingan yang menunggu pemeriksaan dosen. */
    public function index(Request $request): View
    {
        $daftar = Pengajuan::with('mahasiswa.programStudi')
            ->where('pembimbing_id', $request->user()->id)
            ->status(StatusPengajuan::MenungguDosen)
            ->orderBy('updated_at')
            ->paginate(15);

        return view('dosen.pemeriksaan.index', compact('daftar'));
    }

    /** Detail pengajuan. Dosen hanya dapat membuka pengajuan yang dibimbingnya. */
    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing', 'riwayat.user', 'dokumenResmi']);

        return view('dosen.pemeriksaan.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }

    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('periksa', $pengajuan);

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
            Role::Dosen,
            $data['keputusan'] ?? [],
            $data['catatan'] ?? [],
            $data['catatan_umum'] ?? null,
        );

        return redirect()->route('dosen.pemeriksaan.index')->with('sukses', 'Hasil pemeriksaan tersimpan.');
    }
}
