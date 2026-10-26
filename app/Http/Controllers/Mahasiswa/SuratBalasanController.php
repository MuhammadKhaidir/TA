<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Services\SuratTaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SuratBalasanController extends Controller
{
    public function __construct(private SuratTaService $surat) {}

    /** Mahasiswa mengunggah surat balasan atas surat pengantar TA. */
    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('unggahSuratBalasan', $pengajuan);

        $request->validate([
            'berkas_balasan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $balasan = $this->surat->unggahBalasan($pengajuan, $request->user(), $request->file('berkas_balasan'));

        return redirect()->route('mahasiswa.pengajuan.show', $pengajuan)
            ->with('sukses', "Surat balasan (versi {$balasan->versi}) berhasil diunggah dan menunggu pemeriksaan operator.");
    }
}
