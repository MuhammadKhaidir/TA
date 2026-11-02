<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Support\Pdf\KartuKonsultasi;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Kartu konsultasi bimbingan KP/TA dalam bentuk PDF, dibuat saat diminta (tidak disimpan di server)
 * dari data pengajuan yang sama dengan yang dilihat dosen dan admin.
 */
class KartuKonsultasiController extends Controller
{
    public function unduh(Pengajuan $pengajuan): Response
    {
        // Hanya pemilik pengajuan, dan hanya setelah dosen pembimbing sah (lihat PengajuanPolicy).
        $this->authorize('unduhKartuKonsultasi', $pengajuan);

        $pengajuan->load(['mahasiswa.programStudi', 'pembimbing']);

        $pdf = KartuKonsultasi::buat($pengajuan->dataKartuKonsultasi(), public_path('images/UnsriLogo.png'));

        $nama = 'kartu-konsultasi-'.$pengajuan->jenis->value.'-'
            .Str::slug($pengajuan->mahasiswa->nomor_induk ?: $pengajuan->mahasiswa->name).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nama),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
