<?php

namespace App\Http\Controllers\Operator;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Services\PengajuanWorkflow;
use App\Services\SuratTaService;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DokumenResmiController extends Controller
{
    public function __construct(private PengajuanWorkflow $workflow, private SuratTaService $surat) {}

    public function index(): View
    {
        $perluDiproses = Pengajuan::with(['mahasiswa.programStudi', 'pembimbing', 'pembimbingPilihan', 'dokumenResmi', 'suratBalasan'])
            ->status(StatusPengajuan::Disetujui)
            ->orderBy('updated_at')
            ->get();

        // Pengajuan TA yang menunggu tindakan operator pada alur surat: pengantar belum terbit, atau balasan perlu diperiksa.
        $perluPengantar = Pengajuan::with(['mahasiswa'])->perluSuratPengantar()->orderBy('updated_at')->get();
        $perluPeriksaBalasan = Pengajuan::with(['mahasiswa'])->balasanMenunggu()->orderBy('updated_at')->get();

        $terbit = DokumenResmi::with(['pengajuan.mahasiswa', 'pengunggah'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('operator.dokumen-resmi.index', compact('perluDiproses', 'perluPengantar', 'perluPeriksaBalasan', 'terbit'));
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing', 'riwayat.user', 'dokumenResmi.pengunggah', 'suratBalasan.pemeriksa']);

        return view('operator.dokumen-resmi.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }

    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('prosesDokumenResmi', $pengajuan);

        // Surat pengantar TA punya jalur sendiri (suratPengantar), bukan lewat formulir umum ini.
        $jenisBoleh = array_values(array_diff(array_keys($pengajuan->jenis->jenisDokumenResmi()), [JenisPengajuan::SURAT_PENGANTAR_TA]));

        $data = $request->validate([
            'jenis' => ['required', Rule::in($jenisBoleh)],
            'nomor' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'berkas' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        // SK pembimbing TA baru boleh terbit setelah surat pengantar, surat balasan yang diterima, dan persetujuan dosen.
        if ($data['jenis'] === JenisPengajuan::SK_TA && ($hambatan = $pengajuan->hambatanSk()) !== null) {
            throw ValidationException::withMessages(['jenis' => "SK pembimbing belum dapat diterbitkan. {$hambatan}"]);
        }

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

    /** Operator menerbitkan surat pengantar TA (PDF yang sudah ditandatangani) untuk diunduh mahasiswa. */
    public function suratPengantar(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('terbitkanSuratPengantar', $pengajuan);

        $data = $request->validate([
            'nomor_pengantar' => ['required', 'string', 'max:100'],
            'tanggal_pengantar' => ['required', 'date'],
            'berkas_pengantar' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $this->surat->terbitkanPengantar(
            $pengajuan,
            $request->user(),
            $data['nomor_pengantar'],
            $data['tanggal_pengantar'],
            $request->file('berkas_pengantar'),
        );

        return redirect()->route('operator.dokumen-resmi.show', $pengajuan)
            ->with('sukses', 'Surat pengantar diterbitkan. Mahasiswa kini dapat mengunduhnya dan mengunggah surat balasan.');
    }

    public function destroy(Pengajuan $pengajuan, DokumenResmi $dokumenResmi): RedirectResponse
    {
        abort_unless((int) $dokumenResmi->pengajuan_id === (int) $pengajuan->id, 404);

        if ($dokumenResmi->jenis === JenisPengajuan::SURAT_PENGANTAR_TA) {
            $this->authorize('hapusSuratPengantar', $pengajuan);
            $this->surat->hapusPengantar($pengajuan, $dokumenResmi, request()->user());

            return back()->with('sukses', 'Surat pengantar ditarik.');
        }

        $this->authorize('prosesDokumenResmi', $pengajuan);

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
