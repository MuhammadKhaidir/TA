<?php

namespace App\Http\Controllers\Operator;

use App\Enums\StatusDokumen;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Services\SuratTaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuratBalasanController extends Controller
{
    public function __construct(private SuratTaService $surat) {}

    /** Operator menerima atau meminta revisi surat balasan TA. */
    public function periksa(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('periksaSuratBalasan', $pengajuan);

        $data = $request->validate([
            'balasan_id' => ['required', 'integer'],
            'keputusan' => ['required', Rule::in([StatusDokumen::Diterima->value, StatusDokumen::Revisi->value])],
            'catatan_balasan' => ['nullable', 'string', 'max:2000'],
        ]);

        $balasan = $this->surat->periksaBalasan(
            $pengajuan,
            $request->user(),
            (int) $data['balasan_id'],
            StatusDokumen::from($data['keputusan']),
            $data['catatan_balasan'] ?? null,
        );

        if ($balasan->status === StatusDokumen::Revisi) {
            $pesan = 'Permintaan revisi surat balasan dikirim ke mahasiswa.';
        } else {
            $hambatan = $pengajuan->hambatanSk();
            $pesan = $hambatan === null
                ? 'Surat balasan diterima. SK pembimbing kini dapat diterbitkan.'
                : "Surat balasan diterima. SK pembimbing belum dapat diterbitkan: {$hambatan}";
        }

        return redirect()->route('operator.dokumen-resmi.show', $pengajuan)->with('sukses', $pesan);
    }
}
