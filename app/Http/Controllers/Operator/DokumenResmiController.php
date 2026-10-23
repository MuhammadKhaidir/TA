<?php

namespace App\Http\Controllers\Operator;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Services\PengajuanWorkflow;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DokumenResmiController extends Controller
{
    public function __construct(private PengajuanWorkflow $workflow) {}

    public function index(): View
    {
        $perluDiproses = Pengajuan::with(['mahasiswa.programStudi', 'pembimbing'])
            ->status(StatusPengajuan::Disetujui)
            ->orderBy('updated_at')
            ->get();

        $terbit = DokumenResmi::with(['pengajuan.mahasiswa', 'pengunggah'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('operator.dokumen-resmi.index', compact('perluDiproses', 'terbit'));
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing', 'riwayat.user', 'dokumenResmi.pengunggah']);

        return view('operator.dokumen-resmi.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }

    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('prosesDokumenResmi', $pengajuan);

        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys($pengajuan->jenis->jenisDokumenResmi()))],
            'nomor' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'berkas' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('berkas');
        $path = $file->store("dokumen-resmi/{$pengajuan->id}", 'local');

        $dokumen = DokumenResmi::create([
            'pengajuan_id' => $pengajuan->id,
            'diunggah_oleh' => $request->user()->id,
            'jenis' => $data['jenis'],
            'nomor' => $data['nomor'],
            'tanggal' => $data['tanggal'],
            'nama_asli' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'ukuran' => $file->getSize(),
        ]);

        Aktivitas::catat(
            'dokumen-resmi.unggah',
            "Mengunggah {$dokumen->jenisLabel()} nomor {$dokumen->nomor} untuk pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}",
            $dokumen,
        );

        return redirect()->route('operator.dokumen-resmi.show', $pengajuan)
            ->with('sukses', 'Dokumen resmi berhasil diunggah.');
    }

    public function destroy(Pengajuan $pengajuan, DokumenResmi $dokumenResmi): RedirectResponse
    {
        $this->authorize('prosesDokumenResmi', $pengajuan);

        abort_unless((int) $dokumenResmi->pengajuan_id === (int) $pengajuan->id, 404);

        Storage::disk('local')->delete($dokumenResmi->path);
        $dokumenResmi->delete();

        Aktivitas::catat(
            'dokumen-resmi.hapus',
            "Menghapus dokumen resmi nomor {$dokumenResmi->nomor} dari pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}",
            $pengajuan,
        );

        return back()->with('sukses', 'Dokumen resmi dihapus.');
    }

    public function arsipkan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('prosesDokumenResmi', $pengajuan);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->arsipkan($pengajuan, $request->user(), $data['catatan'] ?? null);

        return redirect()->route('operator.arsip.show', $pengajuan)
            ->with('sukses', 'Berkas final berhasil dipindahkan ke arsip digital.');
    }
}
