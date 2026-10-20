<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Exceptions\WorkflowException;
use App\Models\Dosen;
use App\Models\Pengajuan;
use App\Models\PengajuanDokumen;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Services\PengajuanService;
use App\Services\SkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PengajuanController extends Controller
{
    private const BERKAS = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'];

    public function __construct(private readonly PengajuanService $service) {}

    /** Detail pengajuan, dipakai semua peran yang berwenang; panel aksi menyesuaikan peran. */
    public function show(Pengajuan $pengajuan, SkService $sk)
    {
        Gate::authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.prodi', 'prodi', 'periode', 'dospem', 'dokumens.persyaratan', 'suratKeputusan', 'riwayats.user']);

        $bisaKelola = Gate::allows('kelola', $pengajuan);

        return view('pengajuan.show', [
            'pengajuan' => $pengajuan,
            'persyaratan' => $pengajuan->persyaratanPengajuan(),
            'lampiran' => $pengajuan->jenis === JenisPengajuan::Kp
                ? Persyaratan::berlakuUntuk(JenisPersyaratan::KpLampiran, $pengajuan->prodi_id)->get()
                : collect(),
            'lampiranAktif' => Pengaturan::lampiranKpAktif(),
            'bisaUbah' => Gate::allows('ubah', $pengajuan),
            'milik' => Gate::allows('milik', $pengajuan),
            'bisaKelola' => $bisaKelola,
            'bisaPeriksa' => Gate::allows('periksa', $pengajuan),
            'dosens' => $bisaKelola ? Dosen::query()->layakMembimbing()->with('prodi')->orderBy('nama')->get() : collect(),
            'skKurang' => $bisaKelola && $pengajuan->siapTerbitSk() ? $sk->kekuranganKonfigurasi() : [],
        ]);
    }

    public function store(Request $request, JenisPengajuan $jenis): RedirectResponse
    {
        $mahasiswa = Auth::user()->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun Anda belum tertaut ke data mahasiswa.');

        $data = $request->validate($this->aturanIsi($jenis));

        try {
            $dibuat = $this->service->buat($mahasiswa, $jenis, $data, Auth::user());
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pengajuan.show', $dibuat)
            ->with('success', 'Draft pengajuan dibuat. Lengkapi dokumen lalu kirim.');
    }

    public function update(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        $data = $request->validate($this->aturanIsi($pengajuan->jenis));

        return $this->jalankan(
            fn () => $this->service->perbarui($pengajuan, $data, Auth::user()),
            'Data pengajuan diperbarui.',
        );
    }

    public function kirim(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->kirim($pengajuan, Auth::user()),
            'Pengajuan dikirim dan menunggu verifikasi Operator Akademik.',
        );
    }

    public function destroy(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);

        return $this->jalankan(
            fn () => $this->service->batalkan($pengajuan, Auth::user()),
            'Draft pengajuan dihapus.',
            route('mahasiswa.dashboard'),
        );
    }

    public function unggah(Request $request, Pengajuan $pengajuan, Persyaratan $persyaratan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);
        $request->validate(['berkas' => self::BERKAS], ['berkas.required' => 'Pilih berkas yang akan diunggah.']);

        return $this->jalankan(
            fn () => $this->service->unggahDokumen($pengajuan, $persyaratan, $request->file('berkas'), Auth::user()),
            "Dokumen \"{$persyaratan->nama}\" berhasil diunggah.",
        );
    }

    public function hapusDokumen(PengajuanDokumen $dokumen): RedirectResponse
    {
        Gate::authorize('milik', $dokumen->pengajuan);

        return $this->jalankan(fn () => $this->service->hapusDokumen($dokumen, Auth::user()), 'Dokumen dihapus.');
    }

    /** Unduh dokumen: hanya pihak yang boleh melihat pengajuan (berkas disimpan di disk privat). */
    public function unduhDokumen(PengajuanDokumen $dokumen)
    {
        Gate::authorize('view', $dokumen->pengajuan);
        abort_unless(Storage::disk(PengajuanService::DISK)->exists($dokumen->file_path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk(PengajuanService::DISK)->download($dokumen->file_path, $dokumen->nama_file_asli ?? basename($dokumen->file_path));
    }

    /** @return array<string, mixed> */
    private function aturanIsi(JenisPengajuan $jenis): array
    {
        if ($jenis === JenisPengajuan::Ta) {
            return ['judul' => ['required', 'string', 'max:255']];
        }

        return [
            'instansi_nama' => ['required', 'string', 'max:255'],
            'instansi_alamat' => ['required', 'string', 'max:1000'],
            'bidang_kegiatan' => ['nullable', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'pembimbing_lapangan_nama' => ['nullable', 'string', 'max:255'],
            'pembimbing_lapangan_kontak' => ['nullable', 'string', 'max:255'],
        ];
    }
}
