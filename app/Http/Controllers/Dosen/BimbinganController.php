<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Services\PembimbingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BimbinganController extends Controller
{
    public function __construct(private PembimbingService $pembimbing) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        // Permintaan pembimbing TA yang dipilih mahasiswa dan menunggu jawaban dosen ini.
        $permintaan = Pengajuan::with('mahasiswa.programStudi')
            ->where('pembimbing_pilihan_id', $user->id)
            ->where('status_pembimbing', StatusPembimbing::Menunggu->value)
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->orderBy('diajukan_at')
            ->get();

        $daftar = Pengajuan::with('mahasiswa.programStudi')
            ->where('pembimbing_id', $user->id)
            ->when($filter['q'] ?? null, function ($query, $kata) {
                $query->whereHas('mahasiswa', fn ($m) => $m
                    ->where('name', 'like', "%{$kata}%")
                    ->orWhere('nomor_induk', 'like', "%{$kata}%"));
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('dosen.bimbingan.index', compact('daftar', 'filter', 'permintaan'));
    }

    /** Dosen menerima atau menolak permintaan menjadi pembimbing TA. */
    public function konfirmasi(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('konfirmasiPembimbing', $pengajuan);

        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['terima', 'tolak'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $terima = $data['keputusan'] === 'terima';

        $this->pembimbing->konfirmasi($pengajuan, $request->user(), $terima, $data['catatan'] ?? null);

        return redirect()->route('dosen.bimbingan.index')->with(
            'sukses',
            $terima
                ? 'Anda menjadi pembimbing TA mahasiswa tersebut.'
                : 'Permintaan ditolak. Operator akademik akan menetapkan dosen pengganti.',
        );
    }
}
