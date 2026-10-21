<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Models\Pengajuan;
use App\Models\SuratPengantar;
use App\Services\SuratPengantarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Surat pengantar TA. Mahasiswa mengisi dan mengirim; pelaku tugas (Operator memeriksa kelengkapan,
 * Admin memproses offline menurut pengaturan bawaan) mengerjakan sisanya. Siapa boleh apa diputuskan
 * SuratPengantarPolicy lewat HakAkses, bukan peran yang ditanam di sini.
 */
class SuratPengantarController extends Controller
{
    public function __construct(private readonly SuratPengantarService $service) {}

    public function simpan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        abort_unless($pengajuan->jenis === JenisPengajuan::Ta, 404);

        $data = $request->validate($this->aturan());

        return $this->jalankan(
            fn () => $this->service->simpanData($pengajuan, $data, Auth::user()),
            'Data surat pengantar disimpan.',
        );
    }

    public function kirim(Pengajuan $pengajuan): RedirectResponse
    {
        $surat = $pengajuan->suratPengantar;
        abort_unless($surat, 404, 'Simpan data surat pengantar terlebih dulu.');
        Gate::authorize('ubah', $surat);

        return $this->jalankan(
            fn () => $this->service->kirim($surat, Auth::user()),
            'Surat pengantar dikirim dan menunggu pemeriksaan kelengkapan.',
        );
    }

    public function periksa(SuratPengantar $surat): RedirectResponse
    {
        Gate::authorize('periksa', $surat);

        return $this->jalankan(
            fn () => $this->service->periksa($surat, Auth::user()),
            'Kelengkapan surat pengantar diverifikasi.',
        );
    }

    public function kembalikan(Request $request, SuratPengantar $surat): RedirectResponse
    {
        Gate::authorize('periksa', $surat);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan perbaikan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->kembalikan($surat, Auth::user(), $data['catatan']),
            'Surat pengantar dikembalikan ke mahasiswa beserta catatan.',
        );
    }

    public function proses(Request $request, SuratPengantar $surat): RedirectResponse
    {
        Gate::authorize('proses', $surat);
        $data = $request->validate(['nomor_surat' => ['nullable', 'string', 'max:255']]);

        return $this->jalankan(
            fn () => $this->service->proses($surat, Auth::user(), $data['nomor_surat'] ?? null),
            'Surat pengantar ditandai sedang diproses.',
        );
    }

    public function selesai(SuratPengantar $surat): RedirectResponse
    {
        Gate::authorize('proses', $surat);

        return $this->jalankan(
            fn () => $this->service->selesai($surat, Auth::user()),
            'Surat pengantar selesai dan diserahkan kepada mahasiswa.',
        );
    }

    /** Aturan isian diambil dari config/ta.php; simpan draft boleh belum lengkap, kelengkapan dicek saat dikirim. */
    private function aturan(): array
    {
        $aturan = [];
        foreach (SuratPengantar::daftarIsian() as $kunci => [, $jenis]) {
            $aturan[$kunci] = match ($jenis) {
                'textarea' => ['nullable', 'string', 'max:2000'],
                'date' => ['nullable', 'date'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $aturan;
    }
}
