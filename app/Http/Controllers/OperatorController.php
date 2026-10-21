<?php

namespace App\Http\Controllers;

use App\Enums\StatusSurat;
use App\Models\Dosen;
use App\Models\Pengajuan;
use App\Models\SuratPermohonan;
use App\Services\PengajuanService;
use App\Services\SkService;
use App\Services\SuratService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Aksi Operator Akademik. Penetapan pembimbing, penerbitan SK dari website, dan arsip KP dicek lewat
 * policy `kelola` (cakupan prodi). Pemeriksaan kelengkapan dan pencatatan SK offline mengikuti
 * pengaturan hak akses tugas (policy `periksaKelengkapan` / `catatSk`) [K-01].
 */
class OperatorController extends Controller
{
    public function __construct(
        private readonly PengajuanService $service,
        private readonly SkService $sk,
        private readonly SuratService $surat,
    ) {}

    public function verifikasi(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('periksaKelengkapan', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->verifikasiAdministrasi($pengajuan, Auth::user()),
            'Administrasi diverifikasi.',
        );
    }

    public function kembalikan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('periksaKelengkapan', $pengajuan);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan perbaikan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->kembalikanAdministrasi($pengajuan, Auth::user(), $data['catatan']),
            'Pengajuan dikembalikan ke mahasiswa beserta catatan perbaikan.',
        );
    }

    public function tetapkanDospem(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);
        $data = $request->validate([
            'dosen_id' => ['required', 'integer', 'exists:dosens,id'],
            'dosen2_id' => ['nullable', 'integer', 'exists:dosens,id', 'different:dosen_id'],
        ], [
            'dosen_id.required' => 'Pilih dosen pembimbing.',
            'dosen2_id.different' => 'Pembimbing 1 dan Pembimbing 2 harus dosen yang berbeda.',
        ]);

        return $this->jalankan(
            fn () => $this->service->tetapkanDospem(
                $pengajuan,
                Dosen::findOrFail($data['dosen_id']),
                Auth::user(),
                filled($data['dosen2_id'] ?? null) ? Dosen::findOrFail($data['dosen2_id']) : null,
            ),
            'Dosen pembimbing ditetapkan.',
        );
    }

    public function selesaikanKp(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->selesaikanKp($pengajuan, Auth::user()),
            'Administrasi KP diselesaikan dan diarsipkan.',
        );
    }

    // ---- SK TA ---------------------------------------------------------

    /** Pratinjau SK sebelum diterbitkan. Dikirim sebagai halaman terpisah tanpa skrip. */
    public function pratinjauSk(Pengajuan $pengajuan)
    {
        Gate::authorize('kelola', $pengajuan);
        abort_unless($pengajuan->jenis->value === 'ta', 404);

        $html = $this->sk->renderHtml($pengajuan);

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function terbitkanSk(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);

        return $this->jalankan(
            fn () => $this->sk->terbitkan($pengajuan, Auth::user()),
            'SK TA berhasil diterbitkan dan dapat diunduh mahasiswa.',
        );
    }

    /**
     * Jalur offline [K-14]: mencatat nomor, tanggal, dan salinan SK resmi yang terbit di luar sistem.
     * Pelakunya mengikuti pengaturan tugas "catat_sk" (bawaan: Admin, lewat panel /admin).
     */
    public function catatSk(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('catatSk', $pengajuan);
        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'salinan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'nomor.required' => 'Nomor SK wajib diisi.',
            'tanggal.required' => 'Tanggal SK wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal SK tidak boleh di masa depan.',
            'salinan.required' => 'Unggah salinan SK resmi (PDF atau gambar).',
        ]);

        return $this->jalankan(
            fn () => $this->sk->catatOffline($pengajuan, Auth::user(), $data['nomor'], $data['tanggal'], $request->file('salinan')),
            'SK resmi dicatat. Status pengajuan menjadi SK terbit.',
        );
    }

    // ---- Surat KP ------------------------------------------------------

    public function prosesSurat(Request $request, SuratPermohonan $surat): RedirectResponse
    {
        Gate::authorize('proses', $surat);

        $data = $request->validate([
            'tujuan' => ['required', 'in:diproses,selesai,ditolak'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'nomor_surat' => ['nullable', 'string', 'max:255'],
            'berkas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        return $this->jalankan(
            fn () => $this->surat->proses($surat, Auth::user(), StatusSurat::from($data['tujuan']), $data['catatan'] ?? null, $data['nomor_surat'] ?? null, $request->file('berkas')),
            'Permohonan surat diperbarui.',
        );
    }
}
