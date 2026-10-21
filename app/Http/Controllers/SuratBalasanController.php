<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Models\Pengajuan;
use App\Models\SuratBalasan;
use App\Services\PengajuanService;
use App\Services\SuratBalasanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Surat balasan instansi: mahasiswa mengunggah, pemegang tugas validasi memeriksa dengan checklist. */
class SuratBalasanController extends Controller
{
    public function __construct(private readonly SuratBalasanService $service) {}

    public function unggah(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        abort_unless($pengajuan->jenis === JenisPengajuan::Ta, 404);
        $request->validate(
            ['berkas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ['berkas.required' => 'Pilih berkas surat balasan yang akan diunggah.'],
        );

        return $this->jalankan(
            fn () => $this->service->unggah($pengajuan, $request->file('berkas'), Auth::user()),
            'Surat balasan diunggah dan menunggu validasi Operator.',
        );
    }

    public function unduh(Pengajuan $pengajuan)
    {
        $surat = $pengajuan->suratBalasan;
        abort_unless($surat, 404, 'Surat balasan belum diunggah.');
        Gate::authorize('view', $surat);
        abort_unless(Storage::disk(PengajuanService::DISK)->exists($surat->file_path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk(PengajuanService::DISK)->download($surat->file_path, $surat->nama_file_asli ?? basename($surat->file_path));
    }

    public function validasi(Request $request, SuratBalasan $surat): RedirectResponse
    {
        Gate::authorize('validasi', $surat);
        $data = $request->validate([
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->jalankan(
            fn () => $this->service->validasi($surat, Auth::user(), $data['checklist'] ?? [], $data['catatan'] ?? null),
            'Hasil validasi surat balasan disimpan.',
        );
    }
}
