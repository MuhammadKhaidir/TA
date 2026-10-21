<?php

namespace App\Http\Controllers;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Exceptions\WorkflowException;
use App\Enums\StatusSidangTa;
use App\Models\Dosen;
use App\Models\Pengajuan;
use App\Models\PengajuanDokumen;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\SuratBalasan;
use App\Models\SuratPengantar;
use App\Services\KartuKonsultasiService;
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
    public function show(Pengajuan $pengajuan, SkService $sk, KartuKonsultasiService $kartu)
    {
        Gate::authorize('view', $pengajuan);

        $pengajuan->load(['mahasiswa.prodi', 'prodi', 'periode', 'dospem', 'dospem2', 'dokumens.persyaratan', 'suratKeputusan', 'riwayats.user', 'digantikanOleh']);

        $ta = $pengajuan->jenis === JenisPengajuan::Ta;
        $bisaKelola = Gate::allows('kelola', $pengajuan);
        $surat = $ta ? $pengajuan->suratPengantar : null;
        $balasan = $ta ? $pengajuan->suratBalasan : null;
        $sidang = $ta ? $pengajuan->sidangs()->latest('id')->first() : null;
        $milik = Gate::allows('milik', $pengajuan);
        $hidup = ! $pengajuan->status->berhenti();

        return view('pengajuan.show', [
            'pengajuan' => $pengajuan,
            'persyaratan' => $pengajuan->persyaratanPengajuan(),
            'lampiran' => $pengajuan->jenis === JenisPengajuan::Kp
                ? Persyaratan::berlakuUntuk(JenisPersyaratan::KpLampiran, $pengajuan->prodi_id)->get()
                : collect(),
            'lampiranAktif' => Pengaturan::lampiranKpAktif(),
            'bisaUbah' => Gate::allows('ubah', $pengajuan),
            'milik' => $milik,
            'bisaKelola' => $bisaKelola,
            'bisaPeriksa' => Gate::allows('periksa', $pengajuan),
            'dosens' => $bisaKelola ? Dosen::query()->layakMembimbing()->with('prodi')->orderBy('nama')->get() : collect(),
            'skKurang' => $bisaKelola && $pengajuan->siapTerbitSk() && Pengaturan::skMode() === Pengaturan::SK_WEBSITE ? $sk->kekuranganKonfigurasi() : [],

            // Revisi: surat pengantar -> surat balasan -> gerbang SK
            'skMode' => Pengaturan::skMode(),
            'sp' => $surat,
            'sb' => $balasan,
            'persyaratanPengantar' => $ta ? $pengajuan->persyaratanPengantar() : collect(),
            'isianSp' => SuratPengantar::daftarIsian(),
            'checklistSb' => SuratBalasan::daftarChecklist(),
            'bisaMulaiSp' => $ta && $milik && $hidup && $surat === null,
            'bisaUbahSp' => $surat && Gate::allows('ubah', $surat),
            'bisaPeriksaSp' => $surat && Gate::allows('periksa', $surat),
            'bisaProsesSp' => $surat && Gate::allows('proses', $surat),
            'bisaValidasiSb' => $balasan && Gate::allows('validasiSuratBalasan', $pengajuan) && Gate::allows('validasi', $balasan),
            'bisaPeriksaKelengkapan' => Gate::allows('periksaKelengkapan', $pengajuan),
            'bisaCatatSk' => $ta && Gate::allows('catatSk', $pengajuan),
            'bisaBatalkan' => Gate::allows('batalkan', $pengajuan),
            'gerbangTerpenuhi' => ! $ta || ! $pengajuan->gerbangSuratBalasan() || $pengajuan->suratBalasanValid(),
            'kartuAlasanKunci' => $ta ? $kartu->alasanTerkunci($pengajuan) : null,
            'sidang' => $sidang,
            'bisaAjukanSidang' => $ta && $milik && $pengajuan->suratKeputusan !== null
                && ! $pengajuan->sidangs()->whereIn('status', StatusSidangTa::nilaiAktif())->exists(),
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

    /** Pembatalan pengajuan yang sudah dikirim: mahasiswa pemilik atau pemeriksa kelengkapan. Alasan wajib. */
    public function batalkanPengajuan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('batalkan', $pengajuan);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:2000']], ['alasan.required' => 'Alasan pembatalan wajib diisi.']);

        return $this->jalankan(
            fn () => $this->service->batalkanPengajuan($pengajuan, Auth::user(), $data['alasan']),
            'Pengajuan dibatalkan.',
        );
    }

    /** Pengajuan ulang setelah dibatalkan: dibuat draft baru, yang lama menjadi "Digantikan". */
    public function ajukanUlang(Pengajuan $pengajuan): RedirectResponse
    {
        Gate::authorize('milik', $pengajuan);

        try {
            $baru = $this->service->ajukanUlang($pengajuan, Auth::user());
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('pengajuan.show', $baru)->with('success', 'Draft pengajuan baru dibuat dari pengajuan sebelumnya. Periksa datanya lalu lanjutkan prosesnya.');
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

    /**
     * Unduh dokumen: pihak yang boleh melihat pengajuan (berkas disimpan di disk privat). Dokumen surat
     * pengantar juga boleh diunduh pelaku tugas pemeriksaan/pemrosesannya, yang belum tentu dapat membuka
     * seluruh pengajuan (mis. Admin yang memproses surat pengantar offline).
     */
    public function unduhDokumen(PengajuanDokumen $dokumen)
    {
        $pengajuan = $dokumen->pengajuan;
        $surat = $dokumen->persyaratan?->jenis === JenisPersyaratan::TaSuratPengantar ? $pengajuan->suratPengantar : null;

        abort_unless(
            Gate::allows('view', $pengajuan)
                || ($surat && (Gate::allows('proses', $surat) || Gate::allows('periksa', $surat))),
            403,
        );
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
