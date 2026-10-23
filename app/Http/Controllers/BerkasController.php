<?php

namespace App\Http\Controllers;

use App\Models\DokumenPengajuan;
use App\Models\DokumenResmi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya jalan keluar bagi berkas. Berkas disimpan di disk "local" (storage/app/private)
 * sehingga tidak punya URL publik; setiap permintaan diperiksa oleh policy.
 */
class BerkasController extends Controller
{
    public function dokumen(Request $request, DokumenPengajuan $dokumen): StreamedResponse
    {
        $this->authorize('view', $dokumen);

        return $this->kirim($dokumen->path, $dokumen->nama_asli, $dokumen->mime, $request->boolean('unduh'));
    }

    public function resmi(Request $request, DokumenResmi $dokumenResmi): StreamedResponse
    {
        $this->authorize('view', $dokumenResmi);

        return $this->kirim($dokumenResmi->path, $dokumenResmi->nama_asli, $dokumenResmi->mime, $request->boolean('unduh'));
    }

    private function kirim(string $path, string $nama, ?string $mime, bool $unduh): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        $header = ['X-Content-Type-Options' => 'nosniff'];
        if ($mime) {
            $header['Content-Type'] = $mime;
        }

        return $unduh
            ? $disk->download($path, $nama, $header)
            : $disk->response($path, $nama, $header);
    }
}
