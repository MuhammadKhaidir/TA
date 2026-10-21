<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Daftar notifikasi dalam sistem milik pengguna yang login. Tidak ada email/pesan luar. */
class NotifikasiController extends Controller
{
    public function __construct(private readonly NotifikasiService $service) {}

    public function index()
    {
        $user = Auth::user();

        return view('notifikasi.index', [
            'notifikasis' => Notifikasi::query()->where('user_id', $user->id)->latest('id')->paginate(20),
            'belumDibaca' => $this->service->jumlahBelumDibaca($user),
        ]);
    }

    /** Tandai dibaca lalu buka tujuannya (bila ada). Hanya milik sendiri; milik orang lain 404. */
    public function baca(Notifikasi $notifikasi): RedirectResponse
    {
        abort_unless($notifikasi->user_id === Auth::id(), 404);
        $this->service->tandaiDibaca($notifikasi);

        // Tautan internal saja: hanya jalur relatif yang diawali satu "/".
        $url = $notifikasi->url;
        if ($url && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return redirect($url);
        }

        return redirect()->route('notifikasi.index');
    }

    public function bacaSemua(): RedirectResponse
    {
        $this->service->tandaiSemuaDibaca(Auth::user());

        return redirect()->route('notifikasi.index')->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
