<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Enums\StatusSidangTa;
use App\Models\Dosen;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\SidangTa;
use App\Models\SidangTaDokumen;
use App\Services\KartuKonsultasiService;
use App\Services\PengajuanService;
use App\Services\SidangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Modul sidang TA. Semua aksi dicek policy (SidangTaPolicy -> HakAkses); aturan status dan data
 * ada di SidangService. Pelaku tiap tahap mengikuti pengaturan hak akses, bukan peran yang ditanam.
 */
class SidangController extends Controller
{
    private const BERKAS = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'];

    public function __construct(private readonly SidangService $service) {}

    /** Antrean sidang untuk pengguna berwenang (Operator / pemegang tugas), terfilter oleh scope. */
    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->validate(['status' => ['nullable', 'in:'.implode(',', array_keys(StatusSidangTa::opsi()))]]);

        $daftar = SidangTa::query()->dapatDilihatOleh($user)
            ->with(['pengajuan.mahasiswa', 'pengajuan.prodi', 'penguji.dosen'])
            ->when($filter['status'] ?? null,
                fn ($q, $v) => $q->where('status', $v),
                fn ($q) => $q->whereNotIn('status', [StatusSidangTa::Selesai->value, StatusSidangTa::Dibatalkan->value, StatusSidangTa::Digantikan->value]))
            ->orderByRaw("case status when 'diajukan' then 0 when 'terverifikasi' then 1 when 'disetujui' then 2 when 'dijadwalkan' then 3 else 4 end")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('sidang.index', ['daftar' => $daftar, 'filter' => $filter, 'statusOpsi' => StatusSidangTa::opsi()]);
    }

    public function show(SidangTa $sidang, KartuKonsultasiService $kartu)
    {
        Gate::authorize('view', $sidang);

        $sidang->load(['pengajuan.mahasiswa.prodi', 'pengajuan.prodi', 'pengajuan.dospem', 'pengajuan.dospem2', 'pengajuan.suratKeputusan', 'dokumens.persyaratan', 'penguji.dosen', 'riwayats.user', 'digantikanOleh']);

        $persyaratan = $this->service->persyaratan($sidang);
        $bisaJadwalkan = Gate::allows('jadwalkan', $sidang) && in_array($sidang->status, [StatusSidangTa::Disetujui, StatusSidangTa::Dijadwalkan], true);

        return view('sidang.show', [
            'sidang' => $sidang,
            'pengajuan' => $sidang->pengajuan,
            'persyaratan' => $persyaratan,
            'tautanTersedia' => $persyaratan->mapWithKeys(fn (Persyaratan $r) => [$r->id => $r->tautan_otomatis ? $this->service->tautanTersedia($sidang, $r) : null]),
            'kartuTerkunci' => $kartu->terkunci($sidang->pengajuan),
            'bimbingan' => $this->service->ringkasanBimbingan($sidang),
            'bisaUbah' => Gate::allows('ubah', $sidang),
            'milik' => Gate::allows('milik', $sidang),
            'bisaPeriksa' => Gate::allows('periksa', $sidang),
            'bisaSetujui' => Gate::allows('setujui', $sidang),
            'bisaJadwalkan' => $bisaJadwalkan,
            'bisaSelesai' => Gate::allows('selesai', $sidang),
            'bisaBatalkan' => Gate::allows('batalkan', $sidang),
            'calonPenguji' => $bisaJadwalkan
                ? Dosen::query()->where('aktif', true)
                    ->whereNotIn('id', array_filter([$sidang->pengajuan->dospem_id, $sidang->pengajuan->dospem2_id]))
                    ->orderBy('nama')->get()
                : collect(),
        ]);
    }

    // ---- Mahasiswa -----------------------------------------------------

    public function store(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        abort_unless($pengajuan->jenis === JenisPengajuan::Ta, 404);

        try {
            $sidang = $this->service->buat($pengajuan, Auth::user());
        } catch (\App\Exceptions\WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('sidang.show', $sidang)->with('success', 'Draft pengajuan sidang dibuat. Lengkapi dokumen lalu kirim.');
    }

    public function unggah(Request $request, SidangTa $sidang, Persyaratan $persyaratan): RedirectResponse
    {
        Gate::authorize('milik', $sidang);
        $request->validate(['berkas' => self::BERKAS], ['berkas.required' => 'Pilih berkas yang akan diunggah.']);

        return $this->jalankan(
            fn () => $this->service->unggahDokumen($sidang, $persyaratan, $request->file('berkas'), Auth::user()),
            "Dokumen \"{$persyaratan->nama}\" berhasil diunggah.",
        );
    }

    public function hapusDokumen(SidangTaDokumen $dokumen): RedirectResponse
    {
        Gate::authorize('milik', $dokumen->sidang);

        return $this->jalankan(fn () => $this->service->hapusDokumen($dokumen, Auth::user()), 'Dokumen dihapus.');
    }

    public function kirim(SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('milik', $sidang);

        return $this->jalankan(
            fn () => $this->service->kirim($sidang, Auth::user()),
            'Pengajuan sidang dikirim dan menunggu verifikasi kelengkapan.',
        );
    }

    public function batalkan(Request $request, SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('batalkan', $sidang);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:2000']], ['alasan.required' => 'Alasan pembatalan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->batalkan($sidang, Auth::user(), $data['alasan']),
            'Pengajuan sidang dibatalkan.',
        );
    }

    public function ajukanUlang(SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('milik', $sidang);

        try {
            $baru = $this->service->ajukanUlang($sidang, Auth::user());
        } catch (\App\Exceptions\WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('sidang.show', $baru)->with('success', 'Draft pengajuan sidang baru dibuat dari pengajuan sebelumnya. Periksa dokumennya lalu kirim.');
    }

    /** Unduh dokumen sidang: siapa pun yang boleh melihat sidangnya (berkas di disk privat). */
    public function unduhDokumen(SidangTaDokumen $dokumen)
    {
        Gate::authorize('view', $dokumen->sidang);
        abort_unless(Storage::disk(PengajuanService::DISK)->exists($dokumen->file_path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk(PengajuanService::DISK)->download($dokumen->file_path, $dokumen->nama_file_asli ?? basename($dokumen->file_path));
    }

    // ---- Pemeriksa kelengkapan ----------------------------------------

    public function verifikasi(SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('periksa', $sidang);

        return $this->jalankan(fn () => $this->service->verifikasi($sidang, Auth::user()), 'Kelengkapan dokumen sidang diverifikasi.');
    }

    public function kembalikan(Request $request, SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('periksa', $sidang);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan perbaikan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->kembalikan($sidang, Auth::user(), $data['catatan']),
            'Pengajuan sidang dikembalikan ke mahasiswa beserta catatan.',
        );
    }

    // ---- Dosen Pembimbing 1 / 2 ---------------------------------------

    public function setujui(SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('setujui', $sidang);

        return $this->jalankan(fn () => $this->service->setujui($sidang, Auth::user()), 'Pengajuan sidang disetujui.');
    }

    public function revisi(Request $request, SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('setujui', $sidang);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan revisi wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->revisi($sidang, Auth::user(), $data['catatan']),
            'Permintaan revisi dikirim ke mahasiswa.',
        );
    }

    // ---- Penjadwalan dan penyelesaian ---------------------------------

    public function jadwalkan(Request $request, SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('jadwalkan', $sidang);
        $data = $request->validate([
            'jadwal_mulai' => ['required', 'date'],
            'durasi_menit' => ['nullable', 'integer', 'between:15,480'],
            'ruang' => ['nullable', 'string', 'max:100'],
            'penguji' => ['required', 'array', 'min:1'],
            'penguji.*' => ['integer', 'exists:dosens,id'],
        ], ['penguji.required' => 'Pilih minimal satu penguji.', 'jadwal_mulai.required' => 'Tanggal dan jam sidang wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->jadwalkan($sidang, Auth::user(), $data['jadwal_mulai'], $data['ruang'] ?? null, $data['penguji'], isset($data['durasi_menit']) ? (int) $data['durasi_menit'] : null),
            'Sidang dijadwalkan.',
        );
    }

    public function selesai(SidangTa $sidang): RedirectResponse
    {
        Gate::authorize('selesai', $sidang);

        return $this->jalankan(fn () => $this->service->selesai($sidang, Auth::user()), 'Sidang dikonfirmasi selesai.');
    }
}
