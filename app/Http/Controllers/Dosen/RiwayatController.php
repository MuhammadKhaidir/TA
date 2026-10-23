<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Pemeriksaan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    /** Riwayat pemeriksaan milik dosen yang sedang login. */
    public function index(Request $request): View
    {
        $daftar = Pemeriksaan::with(['pengajuan.mahasiswa', 'dokumen.persyaratan'])
            ->where('pemeriksa_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('dosen.riwayat.index', compact('daftar'));
    }
}
