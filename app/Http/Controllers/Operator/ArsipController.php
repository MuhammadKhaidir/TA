<?php

namespace App\Http\Controllers\Operator;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Models\ProgramStudi;
use App\Services\PengajuanWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArsipController extends Controller
{
    public function __construct(private PengajuanWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(JenisPengajuan::pilihan()))],
            'prodi' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $daftar = Pengajuan::with(['mahasiswa.programStudi', 'pembimbing', 'arsip', 'dokumenResmi'])
            ->status(StatusPengajuan::Diarsipkan)
            ->when($filter['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
            ->when($filter['prodi'] ?? null, fn ($q, $v) => $q->whereHas('mahasiswa', fn ($m) => $m->where('program_studi_id', $v)))
            ->when($filter['q'] ?? null, function ($q, $kata) {
                $q->where(function ($w) use ($kata) {
                    $w->where('judul', 'like', "%{$kata}%")
                        ->orWhereHas('mahasiswa', fn ($m) => $m
                            ->where('name', 'like', "%{$kata}%")
                            ->orWhere('nomor_induk', 'like', "%{$kata}%"));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('operator.arsip.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'prodi' => ProgramStudi::orderBy('nama')->get(),
        ]);
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        abort_unless($pengajuan->status === StatusPengajuan::Diarsipkan, 404, 'Pengajuan ini belum diarsipkan.');

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing', 'riwayat.user', 'dokumenResmi.pengunggah', 'arsip.petugas']);

        return view('operator.arsip.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }
}
