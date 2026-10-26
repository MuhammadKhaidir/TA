<?php

namespace App\Http\Controllers\Operator;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\PembimbingService;
use App\Services\PengajuanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Menu "Pengelolaan KP/TA": melihat seluruh pengajuan dan menetapkan dosen pembimbing. */
class PengajuanController extends Controller
{
    public function __construct(
        private PengajuanWorkflow $workflow,
        private PembimbingService $pembimbing,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(JenisPengajuan::pilihan()))],
            'status' => ['nullable', Rule::in(array_keys(StatusPengajuan::pilihan()))],
            'pembimbing' => ['nullable', Rule::in(['perlu'])],
            'prodi' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $daftar = Pengajuan::with(['mahasiswa.programStudi', 'pembimbing', 'pembimbingPilihan'])
            ->when($filter['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
            ->when($filter['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(($filter['pembimbing'] ?? null) === 'perlu', fn ($q) => $q->perluPembimbing())
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

        return view('operator.pengajuan.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'prodi' => ProgramStudi::orderBy('nama')->get(),
        ]);
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load([
            'mahasiswa.programStudi', 'pembimbing', 'pembimbingPilihan', 'riwayat.user',
            'riwayatPembimbing.dosen', 'riwayatPembimbing.oleh', 'dokumenResmi', 'suratBalasan.pemeriksa', 'arsip',
        ]);

        return view('operator.pengajuan.show', [
            'pengajuan' => $pengajuan,
            // Diurutkan dari beban bimbingan paling sedikit.
            'dosen' => $this->pembimbing->daftarDosen($pengajuan),
        ] + $this->workflow->dataDokumen($pengajuan));
    }

    /** Menetapkan atau mengganti dosen pembimbing (KP maupun TA). */
    public function pembimbing(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('kelola', $pengajuan);

        $data = $request->validate([
            'pembimbing_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', Role::Dosen->value)->where('aktif', true)),
            ],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $dosen = User::findOrFail($data['pembimbing_id']);

        $this->pembimbing->tetapkan($pengajuan, $dosen, $request->user(), $data['catatan'] ?? null);

        return back()->with('sukses', "{$dosen->name} ditetapkan sebagai dosen pembimbing.");
    }
}
