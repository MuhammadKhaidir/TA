<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Models\Bimbingan;
use App\Services\BimbinganService;
use App\Services\PengajuanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BimbinganController extends Controller
{
    public function __construct(private readonly BimbinganService $service) {}

    public function store(Request $request, JenisPengajuan $jenis): RedirectResponse
    {
        $mahasiswa = Auth::user()->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun Anda belum tertaut ke data mahasiswa.');

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:1000'],
            'bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal bimbingan tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
        ]);

        return $this->jalankan(
            fn () => $this->service->catat($mahasiswa, $jenis, $data, $request->file('bukti'), Auth::user()),
            'Bimbingan dicatat dan menunggu validasi dosen pembimbing.',
            route('mahasiswa.dashboard').'#bimbingan-'.$jenis->value,
        );
    }

    public function destroy(Bimbingan $bimbingan): RedirectResponse
    {
        Gate::authorize('milik', $bimbingan);

        return $this->jalankan(
            fn () => $this->service->hapus($bimbingan, Auth::user()),
            'Catatan bimbingan dihapus.',
            route('mahasiswa.dashboard').'#bimbingan-'.$bimbingan->jenis->value,
        );
    }

    public function bukti(Bimbingan $bimbingan)
    {
        Gate::authorize('view', $bimbingan);
        abort_unless($bimbingan->bukti_path && Storage::disk(PengajuanService::DISK)->exists($bimbingan->bukti_path), 404);

        return Storage::disk(PengajuanService::DISK)->response($bimbingan->bukti_path);
    }
}
