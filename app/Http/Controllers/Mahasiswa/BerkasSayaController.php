<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\DokumenPengajuan;
use App\Models\DokumenResmi;
use App\Models\SuratBalasan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BerkasSayaController extends Controller
{
    public function index(Request $request): View
    {
        $id = $request->user()->id;

        $dokumen = DokumenPengajuan::with(['pengajuan', 'persyaratan'])
            ->whereHas('pengajuan', fn ($q) => $q->where('mahasiswa_id', $id))
            ->orderByDesc('id')
            ->paginate(15);

        $resmi = DokumenResmi::with('pengajuan')
            ->whereHas('pengajuan', fn ($q) => $q->where('mahasiswa_id', $id))
            ->orderByDesc('id')
            ->get();

        $balasan = SuratBalasan::with('pengajuan')
            ->whereHas('pengajuan', fn ($q) => $q->where('mahasiswa_id', $id))
            ->orderByDesc('id')
            ->get();

        return view('mahasiswa.berkas.index', compact('dokumen', 'resmi', 'balasan'));
    }
}
