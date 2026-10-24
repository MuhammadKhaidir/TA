<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\LogAktivitas;
use App\Models\Pemeriksaan;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Mengarahkan pengguna ke dashboard sesuai role. */
    public function arahkan(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->value.'.dashboard');
    }

    public function mahasiswa(Request $request): View
    {
        $user = $request->user()->load('programStudi');
        $pengajuan = $user->pengajuan()->with(['pembimbing', 'pembimbingPilihan'])->orderByDesc('updated_at')->get();

        // Pembimbing per jenis: KP ditetapkan operator dari database dosen, TA berasal dari pilihan mahasiswa.
        $pengajuanKp = $pengajuan->first(fn (Pengajuan $p) => $p->jenis === JenisPengajuan::Kp);
        $pengajuanTa = $pengajuan->first(fn (Pengajuan $p) => $p->jenis === JenisPengajuan::Ta);

        $perluRevisi = $pengajuan->filter(fn (Pengajuan $p) => in_array(
            $p->status, [StatusPengajuan::RevisiAdministrasi, StatusPengajuan::RevisiAkademik], true,
        ));
        $dalamProses = $pengajuan->filter(fn (Pengajuan $p) => in_array(
            $p->status, [StatusPengajuan::Diajukan, StatusPengajuan::MenungguDosen, StatusPengajuan::Disetujui], true,
        ))->count();
        $selesai = $pengajuan->filter(fn (Pengajuan $p) => $p->status === StatusPengajuan::Diarsipkan)->count();

        return view('dashboard.mahasiswa', compact('user', 'pengajuan', 'pengajuanKp', 'pengajuanTa', 'perluRevisi', 'dalamProses', 'selesai'));
    }

    public function dosen(Request $request): View
    {
        $user = $request->user();

        $antrean = Pengajuan::with('mahasiswa.programStudi')
            ->where('pembimbing_id', $user->id)
            ->status(StatusPengajuan::MenungguDosen)
            ->orderBy('updated_at')
            ->get();

        $jumlahMahasiswa = Pengajuan::where('pembimbing_id', $user->id)->distinct()->count('mahasiswa_id');
        $selesai = Pengajuan::where('pembimbing_id', $user->id)->status(StatusPengajuan::Diarsipkan)->count();
        $totalDiperiksa = Pemeriksaan::where('pemeriksa_id', $user->id)->count();

        $permintaanPembimbing = Pengajuan::where('pembimbing_pilihan_id', $user->id)
            ->where('status_pembimbing', StatusPembimbing::Menunggu->value)
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->count();

        return view('dashboard.dosen', compact('antrean', 'jumlahMahasiswa', 'selesai', 'totalDiperiksa', 'permintaanPembimbing'));
    }

    public function operator(): View
    {
        $jumlah = Pengajuan::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $antreanVerifikasi = Pengajuan::with('mahasiswa.programStudi')
            ->status(StatusPengajuan::Diajukan)
            ->orderBy('diajukan_at')
            ->limit(5)
            ->get();

        $perluDokumenResmi = Pengajuan::with('mahasiswa.programStudi')
            ->status(StatusPengajuan::Disetujui)
            ->orderBy('updated_at')
            ->limit(5)
            ->get();

        $jumlahPerluPembimbing = Pengajuan::perluPembimbing()->count();

        $perluPembimbing = Pengajuan::with(['mahasiswa.programStudi', 'pembimbingPilihan'])
            ->perluPembimbing()
            ->orderBy('diajukan_at')
            ->limit(5)
            ->get();

        return view('dashboard.operator', compact(
            'jumlah', 'antreanVerifikasi', 'perluDokumenResmi', 'jumlahPerluPembimbing', 'perluPembimbing',
        ));
    }

    public function admin(): View
    {
        $perRole = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
        $jumlahProdi = ProgramStudi::count();
        $jumlahPersyaratan = Persyaratan::aktif()->count();
        $logTerbaru = LogAktivitas::with('user')->orderByDesc('id')->limit(8)->get();

        return view('dashboard.admin', compact('perRole', 'jumlahProdi', 'jumlahPersyaratan', 'logTerbaru'));
    }
}
