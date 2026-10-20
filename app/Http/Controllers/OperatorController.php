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

/** Aksi Operator Akademik. Setiap aksi dicek lewat policy `kelola` (cakupan prodi). */
class OperatorController extends Controller
{
    public function __construct(
        private readonly PengajuanService $service,
        private readonly SkService $sk,
        private readonly SuratService $surat,
    ) {}

    public function verifikasi(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->verifikasiAdministrasi($pengajuan, Auth::user()),
            'Administrasi diverifikasi.',
        );
    }

    public function kembalikan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan perbaikan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->kembalikanAdministrasi($pengajuan, Auth::user(), $data['catatan']),
            'Pengajuan dikembalikan ke mahasiswa beserta catatan perbaikan.',
        );
    }

    public function tetapkanDospem(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('kelola', $pengajuan);
        $data = $request->validate(['dosen_id' => ['required', 'integer', 'exists:dosens,id']], ['dosen_id.required' => 'Pilih dosen pembimbing.']);

        return $this->jalankan(
            fn () => $this->service->tetapkanDospem($pengajuan, Dosen::findOrFail($data['dosen_id']), Auth::user()),
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
