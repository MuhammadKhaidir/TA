<?php

namespace App\Http\Controllers;

use App\Enums\SidangStatus;
use App\Models\Konsultasi;
use App\Models\Sidang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Database\UniqueConstraintViolationException;

class DashboardController extends Controller
{
    public function mahasiswa()
    {
        $mahasiswa = Auth::user()->mahasiswa;

        $sidangs = $mahasiswa
            ? Sidang::where('mahasiswa_id', $mahasiswa->id)
                ->with(['pengujis', 'dokumens'])
                ->latest('tanggal_pengajuan')
                ->get()
            : collect();

        return view('mahasiswa.dashboard', [
            'mahasiswa' => $mahasiswa,
            'sidangs' => $sidangs,
            'konsultasis' => $mahasiswa?->konsultasis()->latest('tanggal')->get() ?? collect(),
        ]);
    }

    // ------------------------------------------------------------------
    // Checklist konsultasi pembimbingan (mahasiswa)
    // jumlah_konsultasi di-increment/decrement (bukan dihitung ulang) supaya
    // angka awal yang sudah diisi SekDep/Koor. Prodi tidak tertimpa.
    // ------------------------------------------------------------------

 public function catatKonsultasi(Request $request): RedirectResponse
{
    $mahasiswa = Auth::user()->mahasiswa;
    abort_unless($mahasiswa, 403, 'Akun Anda belum tertaut ke data mahasiswa.');

    $data = $request->validate([
        'tanggal' => [
            'bail', 'required', 'date', 'before_or_equal:today',
            function (string $attribute, mixed $value, \Closure $fail) use ($mahasiswa) {
                // whereDate: cocok untuk baris yang tersimpan sebagai "2026-10-06 00:00:00"
                if ($mahasiswa->konsultasis()->whereDate('tanggal', $value)->exists()) {
                    $fail('Konsultasi pada tanggal tersebut sudah dicentang.');
                }
            },
        ],
        'catatan' => ['nullable', 'string', 'max:255'],
        'bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
    ], [
        'tanggal.before_or_equal' => 'Tanggal konsultasi tidak boleh di masa depan.',
    ]);

    $buktiPath = null;

    try {
        DB::transaction(function () use ($mahasiswa, $data, $request, &$buktiPath) {
            $buktiPath = $request->file('bukti')?->store("konsultasi/{$mahasiswa->id}", 'public');

            $mahasiswa->konsultasis()->create([
                'tanggal' => $data['tanggal'],
                'catatan' => $data['catatan'] ?? null,
                'bukti_path' => $buktiPath,
            ]);
            $mahasiswa->increment('jumlah_konsultasi');
        });
    } catch (UniqueConstraintViolationException) {
        // Jaga-jaga kalau tombol diklik dua kali cepat: buang foto yang sudah terlanjur tersimpan.
        if ($buktiPath) {
            Storage::disk('public')->delete($buktiPath);
        }

        return back()
            ->withInput()
            ->withErrors(['tanggal' => 'Konsultasi pada tanggal tersebut sudah dicentang.']);
    }

    return redirect(route('mahasiswa.dashboard').'#konsultasi-pembimbingan')
        ->with('success', 'Konsultasi berhasil dicentang.');
}

    public function hapusKonsultasi(Konsultasi $konsultasi): RedirectResponse
    {
        $mahasiswa = Auth::user()->mahasiswa;
        abort_unless($mahasiswa && $konsultasi->mahasiswa_id === $mahasiswa->id, 403);

        DB::transaction(function () use ($mahasiswa, $konsultasi) {
            if ($konsultasi->bukti_path) {
                Storage::disk('public')->delete($konsultasi->bukti_path);
            }
            $konsultasi->delete();
            $mahasiswa->update(['jumlah_konsultasi' => max(0, $mahasiswa->jumlah_konsultasi - 1)]);
        });

        return redirect(route('mahasiswa.dashboard').'#konsultasi-pembimbingan')
            ->with('success', 'konsultasi dihapus.');
    }

    public function buktiKonsultasi(Konsultasi $konsultasi)
    {
        $user = Auth::user();
        abort_unless(
            $user->hasAnyRole(['admin', 'sekdep_koor_prodi']) || $user->mahasiswa?->id === $konsultasi->mahasiswa_id,
            403
        );
        abort_unless($konsultasi->bukti_path && Storage::disk('public')->exists($konsultasi->bukti_path), 404);

        return Storage::disk('public')->response($konsultasi->bukti_path);
    }

    public function sekdep()
    {
        $antrean = Sidang::with('mahasiswa')
            ->whereIn('status', [
                SidangStatus::Diajukan->value,
                SidangStatus::SkDiteruskanSekdep->value,
                SidangStatus::MenungguPenjadwalanUlang->value,
            ])
            ->orderBy('updated_at')
            ->get();

        $ditunda = Sidang::with('mahasiswa')
            ->where('status', SidangStatus::Ditunda->value)
            ->orderBy('tanggal_pengajuan')
            ->get();

        $eskalasiNilai = Sidang::with(['mahasiswa', 'pengujis'])
            ->where('status', SidangStatus::PelaksanaanUjian->value)
            ->get()
            ->reject(fn (Sidang $s) => $s->semuaNilaiSudahDiinput());

        return view('sekdep.dashboard', compact('antrean', 'ditunda', 'eskalasiNilai'));
    }

    public function penata()
    {
        $antrean = Sidang::with('mahasiswa')
            ->whereIn('status', [
                SidangStatus::MenungguVerifikasiPenata->value,
                SidangStatus::SkTerbit->value,
            ])
            ->orderBy('updated_at')
            ->get();

        $eskalasiNilai = Sidang::with(['mahasiswa', 'pengujis'])
            ->where('status', SidangStatus::PelaksanaanUjian->value)
            ->get()
            ->reject(fn (Sidang $s) => $s->semuaNilaiSudahDiinput());

        return view('penata.dashboard', compact('antrean', 'eskalasiNilai'));
    }

    public function pengelola()
    {
        $antrean = Sidang::with('mahasiswa')
            ->where('status', SidangStatus::MenungguProsesSk->value)
            ->orderBy('updated_at')
            ->get();

        $pemeriksaanNilai = Sidang::with(['mahasiswa', 'pengujis'])
            ->where('status', SidangStatus::PelaksanaanUjian->value)
            ->orderBy('tanggal_sidang')
            ->get();

        return view('pengelola.dashboard', compact('antrean', 'pemeriksaanNilai'));
    }

    public function administrasi()
    {
        $antrean = Sidang::with('mahasiswa')
            ->whereIn('status', [
                SidangStatus::MenyiapkanAdministrasi->value,
                SidangStatus::MenungguInfoJadwal->value,
                SidangStatus::MenungguPengecekanBerkas->value,
            ])
            ->orderBy('updated_at')
            ->get();

        $siapUjian = Sidang::with('mahasiswa')
            ->where('status', SidangStatus::SiapUjian->value)
            ->orderBy('tanggal_sidang')
            ->get();

        return view('administrasi.dashboard', compact('antrean', 'siapUjian'));
    }

    public function dosen()
    {
        $dosen = Auth::user()->dosen;

        $sidangs = $dosen
            ? Sidang::whereHas('pengujis', fn ($q) => $q->where('dosen_id', $dosen->id))
                ->with(['mahasiswa', 'pengujis'])
                ->orderBy('tanggal_sidang')
                ->get()
            : collect();

        $perluTindakan = $sidangs->whereIn('status', [SidangStatus::SiapUjian, SidangStatus::PelaksanaanUjian]);

        return view('dosen.dashboard', [
            'dosen' => $dosen,
            'sidangs' => $sidangs,
            'perluTindakan' => $perluTindakan,
        ]);
    }
}
