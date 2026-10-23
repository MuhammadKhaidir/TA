<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogAktivitasController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'aksi' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $daftar = LogAktivitas::with('user')
            ->when($filter['aksi'] ?? null, fn ($q, $v) => $q->where('aksi', 'like', $v.'%'))
            ->when($filter['q'] ?? null, function ($q, $kata) {
                $q->where(function ($w) use ($kata) {
                    $w->where('deskripsi', 'like', "%{$kata}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$kata}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $kelompok = [
            'auth' => 'Login dan logout',
            'pengguna' => 'Pengguna',
            'prodi' => 'Program studi',
            'persyaratan' => 'Persyaratan',
            'pengajuan' => 'Pengajuan dan status',
            'dokumen' => 'Dokumen persyaratan',
            'dokumen-resmi' => 'Dokumen resmi',
            'profil' => 'Profil',
        ];

        return view('admin.log.index', compact('daftar', 'filter', 'kelompok'));
    }
}
