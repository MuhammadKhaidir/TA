<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Models\Pengajuan;
use App\Services\KartuKonsultasiService;
use Illuminate\Support\Facades\Gate;

/** Unduh kartu konsultasi TA (PDF). Terkunci sebelum SK terbit selama pengaturan kartu.butuh_sk aktif. */
class KartuKonsultasiController extends Controller
{
    public function unduh(Pengajuan $pengajuan, KartuKonsultasiService $kartu)
    {
        Gate::authorize('view', $pengajuan);

        try {
            $pdf = $kartu->render($pengajuan);
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Kartu-Konsultasi-'.$pengajuan->mahasiswa->nim.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
