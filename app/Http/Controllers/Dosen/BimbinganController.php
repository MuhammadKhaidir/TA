<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BimbinganController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $daftar = Pengajuan::with('mahasiswa.programStudi')
            ->where('pembimbing_id', $request->user()->id)
            ->when($filter['q'] ?? null, function ($query, $kata) {
                $query->whereHas('mahasiswa', fn ($m) => $m
                    ->where('name', 'like', "%{$kata}%")
                    ->orWhere('nomor_induk', 'like', "%{$kata}%"));
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('dosen.bimbingan.index', compact('daftar', 'filter'));
    }
}
