<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Jalankan aksi workflow; pelanggaran aturan status/data (WorkflowException)
     * ditampilkan sebagai pesan error, bukan halaman 500.
     */
    protected function jalankan(callable $aksi, string $pesanSukses, ?string $tujuan = null): RedirectResponse
    {
        try {
            $aksi();
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()])->withInput();
        }

        return ($tujuan ? redirect($tujuan) : back())->with('success', $pesanSukses);
    }
}
