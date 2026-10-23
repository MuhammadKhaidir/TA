<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(Request $request): View
    {
        $daftar = Pengajuan::with(['pembimbing', 'riwayat.user'])
            ->where('mahasiswa_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return view('mahasiswa.status.index', compact('daftar'));
    }
}
