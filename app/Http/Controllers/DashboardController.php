<?php

namespace App\Http\Controllers;

use App\Enums\SidangStatus;
use App\Models\Sidang;
use Illuminate\Support\Facades\Auth;

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
        ]);
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
