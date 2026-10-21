<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusBimbingan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSidangTa;
use App\Enums\StatusSuratBalasan;
use App\Enums\StatusSuratPengantar;
use App\Enums\StatusSurat;
use App\Models\Bimbingan;
use App\Models\JenisSurat;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\PeriodeAkademik;
use App\Models\Persyaratan;
use App\Models\Prodi;
use App\Models\SidangTa;
use App\Models\StrukturLaporan;
use App\Models\SuratBalasan;
use App\Models\SuratPengantar;
use App\Models\SuratPermohonan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function mahasiswa()
    {
        $mahasiswa = Auth::user()->mahasiswa?->load('prodi');

        if (! $mahasiswa) {
            return view('mahasiswa.dashboard', ['mahasiswa' => null]);
        }

        $terbaru = fn (JenisPengajuan $jenis) => $mahasiswa->pengajuans()
            ->where('jenis', $jenis->value)
            ->with(['dospem', 'dospem2', 'dokumens.persyaratan', 'suratKeputusan', 'suratPermohonans.jenisSurat', 'suratPengantar', 'suratBalasan'])
            ->latest('id')
            ->first();

        $ta = $terbaru(JenisPengajuan::Ta);
        $kp = $terbaru(JenisPengajuan::Kp);

        return view('mahasiswa.dashboard', [
            'mahasiswa' => $mahasiswa,
            'ta' => $ta,
            'kp' => $kp,
            'sidangTa' => $ta ? $ta->sidangs()->latest('id')->first() : null,
            'persyaratanTa' => Persyaratan::berlakuUntuk(JenisPersyaratan::TaPengajuan, $mahasiswa->prodi_id)->get(),
            'persyaratanKp' => Persyaratan::berlakuUntuk(JenisPersyaratan::KpPengajuan, $mahasiswa->prodi_id)->get(),
            'lampiranKp' => Persyaratan::berlakuUntuk(JenisPersyaratan::KpLampiran, $mahasiswa->prodi_id)->get(),
            'lampiranAktif' => Pengaturan::lampiranKpAktif(),
            'bimbingan' => [
                'ta' => $mahasiswa->bimbingans()->where('jenis', 'ta')->latest('tanggal')->get(),
                'kp' => $mahasiswa->bimbingans()->where('jenis', 'kp')->latest('tanggal')->get(),
            ],
            'ringkasan' => [
                'ta' => $mahasiswa->ringkasanBimbingan(JenisPengajuan::Ta),
                'kp' => $mahasiswa->ringkasanBimbingan(JenisPengajuan::Kp),
            ],
            'jenisSurats' => JenisSurat::query()->where('aktif', true)->orderBy('nama')->get(),
            'struktur' => StrukturLaporan::query()->where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
        ]);
    }

    public function dospem()
    {
        $user = Auth::user();
        $dosen = $user->dosen;

        $pengajuans = Pengajuan::query()->dapatDilihatOleh($user)
            ->with(['mahasiswa', 'prodi'])
            ->orderByRaw("case status when 'diajukan' then 0 when 'perlu_revisi' then 1 when 'disetujui' then 2 else 3 end")
            ->latest('updated_at')
            ->get();

        // Pembimbing 1 dan 2 menyetujui sendiri-sendiri: hanya yang masih ditunggu darinya yang tampil di sini.
        $perluDiperiksa = $pengajuans->filter(fn (Pengajuan $p) => $dosen && $p->menungguPersetujuan($dosen));

        $sidangMenunggu = SidangTa::query()->dapatDilihatOleh($user)
            ->where('status', StatusSidangTa::Terverifikasi->value)
            ->with(['pengajuan.mahasiswa'])
            ->get()
            ->filter(function (SidangTa $s) use ($dosen) {
                $posisi = $s->pengajuan->posisiPembimbing($dosen);

                return $posisi !== null && ($posisi === 1 ? $s->setuju_dospem1_pada : $s->setuju_dospem2_pada) === null;
            })
            ->values();

        $bimbinganMenunggu = $dosen
            ? Bimbingan::query()->with('mahasiswa')
                ->where('status_validasi', StatusBimbingan::Menunggu->value)
                ->whereIn('mahasiswa_id', $pengajuans->pluck('mahasiswa_id')->unique())
                ->orderBy('tanggal')
                ->get()
                ->filter(fn (Bimbingan $b) => Gate::forUser($user)->allows('validasi', $b))
                ->values()
            : collect();

        return view('dospem.dashboard', compact('dosen', 'pengajuans', 'perluDiperiksa', 'bimbinganMenunggu', 'sidangMenunggu'));
    }

    public function operator(Request $request)
    {
        $user = Auth::user();

        $filter = $request->validate([
            'jenis' => ['nullable', 'in:ta,kp'],
            'prodi_id' => ['nullable', 'integer'],
            'periode_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(StatusPengajuan::opsi()))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $dasar = Pengajuan::query()->dapatDilihatOleh($user);

        $daftar = (clone $dasar)->with(['mahasiswa', 'prodi', 'dospem', 'dospem2', 'periode', 'suratPengantar', 'suratBalasan'])
            ->when($filter['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
            ->when($filter['prodi_id'] ?? null, fn ($q, $v) => $q->where('prodi_id', $v))
            ->when($filter['periode_id'] ?? null, fn ($q, $v) => $q->where('periode_akademik_id', $v))
            // Tanpa filter status: tampilkan yang masih berjalan; arsip = status "selesai".
            ->when($filter['status'] ?? null,
                fn ($q, $v) => $q->where('status', $v),
                fn ($q) => $q->whereNotIn('status', [StatusPengajuan::Selesai->value, ...StatusPengajuan::nilaiBerhenti()]))
            ->when($filter['q'] ?? null, fn ($q, $v) => $q->whereHas('mahasiswa', fn ($m) => $m
                ->where('nama', 'like', "%{$v}%")->orWhere('nim', 'like', "%{$v}%")))
            ->orderByRaw("case status when 'diajukan' then 0 when 'disetujui' then 1 when 'perlu_revisi' then 2 else 3 end")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $cakupan = $user->cakupanProdi();
        $surats = SuratPermohonan::query()
            ->with(['pengajuan.mahasiswa', 'jenisSurat'])
            ->whereIn('status', [StatusSurat::Diajukan->value, StatusSurat::Diproses->value])
            ->when($cakupan !== null, fn ($q) => $q->whereHas('pengajuan', fn ($p) => $p->whereIn('prodi_id', $cakupan)))
            ->orderBy('created_at')
            ->get();

        $cakupanProdi = fn ($q) => $q->when($cakupan !== null, fn ($x) => $x->whereHas('pengajuan', fn ($p) => $p->whereIn('prodi_id', $cakupan)));
        $sidangMenunggu = $cakupanProdi(SidangTa::query()->where('status', StatusSidangTa::Diajukan->value))->count();

        $ringkasan = [
            'verifikasi' => (clone $dasar)->where('status', StatusPengajuan::Diajukan->value)
                ->where('status_administrasi', StatusAdministrasi::Menunggu->value)->count(),
            'siap_proses' => (clone $dasar)->where('status', StatusPengajuan::Disetujui->value)->count(),
            'perlu_revisi' => (clone $dasar)->where('status', StatusPengajuan::PerluRevisi->value)->count(),
            'surat' => $surats->count(),
            'pengantar' => $cakupanProdi(SuratPengantar::query()->where('status', StatusSuratPengantar::Diajukan->value))->count(),
            'balasan' => $cakupanProdi(SuratBalasan::query()->where('status', StatusSuratBalasan::Menunggu->value))->count(),
            'sidang' => $sidangMenunggu,
        ];

        return view('operator.dashboard', [
            'daftar' => $daftar,
            'surats' => $surats,
            'ringkasan' => $ringkasan,
            'filter' => $filter,
            'prodis' => $cakupan === null ? Prodi::query()->orderBy('nama')->get() : Prodi::query()->whereIn('id', $cakupan)->orderBy('nama')->get(),
            'periodes' => PeriodeAkademik::query()->orderByDesc('id')->get(),
            'statusOpsi' => StatusPengajuan::opsi(),
            'cakupanKosong' => $cakupan !== null && $cakupan === [],
        ]);
    }
}
