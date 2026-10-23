<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\StatusDokumen;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\DokumenPengajuan;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DokumenController extends Controller
{
    /**
     * Mengunggah dokumen persyaratan. Pada status draft semua dokumen boleh diunggah/diganti;
     * pada status revisi hanya dokumen yang ditandai revisi yang boleh diunggah ulang.
     * Berkas lama tidak dihapus; versi baru menjadi versi terbaru dan yang lama menjadi jejak.
     */
    public function store(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('update', $pengajuan);

        $request->validate(['persyaratan_id' => ['required', 'integer']]);

        $syarat = Persyaratan::aktif()->untuk($pengajuan->jenis)->find($request->input('persyaratan_id'));
        if ($syarat === null) {
            throw ValidationException::withMessages([
                'persyaratan_id' => 'Persyaratan tidak valid untuk pengajuan ini.',
            ]);
        }

        $lama = $pengajuan->dokumenTerbaru()->where('persyaratan_id', $syarat->id)->first();

        if ($pengajuan->status !== StatusPengajuan::Draft && ($lama === null || $lama->status !== StatusDokumen::Revisi)) {
            throw ValidationException::withMessages([
                'berkas' => 'Hanya dokumen yang ditandai revisi yang dapat diunggah ulang.',
            ]);
        }

        // Aturan format dan ukuran dibaca dari pengaturan persyaratan (diatur admin).
        $request->validate(['berkas' => $syarat->aturanBerkas()]);

        $file = $request->file('berkas');
        $path = $file->store("pengajuan/{$pengajuan->id}", 'local');

        $dokumen = DB::transaction(function () use ($pengajuan, $syarat, $file, $path, $request) {
            $versi = (int) $pengajuan->dokumen()->where('persyaratan_id', $syarat->id)->max('versi') + 1;

            $pengajuan->dokumen()->where('persyaratan_id', $syarat->id)->update(['terbaru' => false]);

            return DokumenPengajuan::create([
                'pengajuan_id' => $pengajuan->id,
                'persyaratan_id' => $syarat->id,
                'diunggah_oleh' => $request->user()->id,
                'versi' => $versi,
                'terbaru' => true,
                'nama_asli' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
                'status' => StatusDokumen::Menunggu,
            ]);
        });

        Aktivitas::catat(
            'dokumen.unggah',
            "Mengunggah {$syarat->nama} (versi {$dokumen->versi}) untuk pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}",
            $dokumen,
        );

        return redirect()->route('mahasiswa.pengajuan.show', $pengajuan)
            ->with('sukses', "{$syarat->nama} berhasil diunggah.");
    }
}
