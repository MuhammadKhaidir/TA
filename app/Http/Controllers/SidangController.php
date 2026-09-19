<?php

namespace App\Http\Controllers;

use App\Enums\JenisDokumen;
use App\Exceptions\WorkflowException;
use App\Models\Dokumen;
use App\Models\Mahasiswa;
use App\Models\Sidang;
use App\Services\SidangWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SidangController extends Controller
{
    public function __construct(private readonly SidangWorkflowService $service)
    {
    }

    /**
     * Halaman detail sidang: menampilkan timeline 16 langkah, dokumen,
     * dewan penguji, serta panel aksi kontekstual sesuai peran yang login.
     */
    public function show(Sidang $sidang)
    {
        $this->pastikanBolehLihat($sidang);

        $sidang->load(['mahasiswa', 'pengujis', 'dokumens.uploader', 'riwayats.user']);

        return view('sidang.show', [
            'sidang' => $sidang,
            'langkahMaster' => \App\Enums\SidangStatus::judulLangkah(),
            'dosens' => \App\Models\Dosen::orderBy('nama')->get(),
        ]);
    }

    public function unduhDokumen(Sidang $sidang, Dokumen $dokumen)
    {
        $this->pastikanBolehLihat($sidang);

        abort_unless($dokumen->sidang_id === $sidang->id, 404);
        abort_unless(Storage::disk('public')->exists($dokumen->file_path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk('public')->download($dokumen->file_path, $dokumen->nama_file_asli ?? basename($dokumen->file_path));
    }

    private function pastikanBolehLihat(Sidang $sidang): void
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'sekdep_koor_prodi', 'penata', 'pengelola_layanan', 'pengadministrasi_perkantoran'])) {
            return;
        }

        if ($user->hasRole('mahasiswa') && $user->mahasiswa?->id === $sidang->mahasiswa_id) {
            return;
        }

        if ($user->hasRole('dosen_penguji') && $user->dosen && $sidang->sidangPengujis()->where('dosen_id', $user->dosen->id)->exists()) {
            return;
        }

        abort(403, 'Anda tidak berwenang melihat sidang ini.');
    }

    private function jalankan(callable $aksi, string $pesanSukses): RedirectResponse
    {
        try {
            $aksi();

            return back()->with('success', $pesanSukses);
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()])->withInput();
        }
    }

    // ------------------------------------------------------------------
    // Langkah 1 — Mahasiswa
    // ------------------------------------------------------------------

    public function ajukan(Request $request): RedirectResponse
    {
        $mahasiswa = Auth::user()->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun Anda belum tertaut ke data mahasiswa.');

        $data = $request->validate([
            'jenis' => ['required', 'in:komprehensif,skripsi'],
            'judul_ta' => ['nullable', 'string', 'max:255'],
            'dkn_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'usep_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'sk_pembimbing_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        return $this->jalankan(
            fn () => $this->service->ajukanSidang($mahasiswa, $data, Auth::user()),
            'Pengajuan sidang berhasil dikirim ke SekDep/Koor. Prodi.'
        );
    }

    public function unggahDokumen(Request $request, Sidang $sidang): RedirectResponse
    {
        $this->pastikanBolehLihat($sidang);

        $data = $request->validate([
            'jenis_dokumen' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $jenis = JenisDokumen::tryFrom($data['jenis_dokumen']);
        abort_unless($jenis, 422, 'Jenis dokumen tidak dikenali.');

        return $this->jalankan(
            fn () => $this->service->unggahDokumen($sidang, $jenis, $request->file('file'), Auth::user()),
            'Dokumen berhasil diunggah.'
        );
    }

    // ------------------------------------------------------------------
    // SekDep / Koor. Prodi
    // ------------------------------------------------------------------

    public function perbaruiKonsultasi(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'jumlah_konsultasi' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        return $this->jalankan(
            fn () => $this->service->perbaruiJumlahKonsultasi($sidang, $data['jumlah_konsultasi'], Auth::user()),
            'Jumlah konsultasi berhasil diperbarui.'
        );
    }

    public function usulkanJadwal(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_sidang' => ['required', 'date'],
            'jam_sidang' => ['required', 'date_format:H:i'],
            'media' => ['required', 'in:luring,daring'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'pengujis' => ['required', 'array', 'min:1'],
            'pengujis.*.dosen_id' => ['required', 'exists:dosens,id'],
            'pengujis.*.peran' => ['required', 'in:ketua,anggota,pembimbing'],
        ]);

        return $this->jalankan(
            fn () => $this->service->usulkanJadwal($sidang, $data, Auth::user()),
            'Jadwal ujian dan tim penguji berhasil diusulkan.'
        );
    }

    public function distribusikanSkKeDosen(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->distribusikanSkKeDosen($sidang, Auth::user()),
            'SK Penguji telah disampaikan ke seluruh Dosen Penguji.'
        );
    }

    public function jadwalkanUlang(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_sidang' => ['required', 'date'],
            'jam_sidang' => ['required', 'date_format:H:i'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'dosen_diganti_id' => ['nullable', 'exists:dosens,id'],
            'dosen_pengganti_id' => ['nullable', 'exists:dosens,id', 'required_with:dosen_diganti_id'],
        ]);

        return $this->jalankan(
            fn () => $this->service->jadwalkanUlang($sidang, $data, Auth::user()),
            'Sidang berhasil dijadwalkan ulang.'
        );
    }

    public function hubungiDosen(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->hubungiDosen($sidang, Auth::user()),
            'Dosen yang belum menginput nilai telah dihubungi.'
        );
    }

    // ------------------------------------------------------------------
    // Penata
    // ------------------------------------------------------------------

    public function verifikasi(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'nilai_usep' => ['required', 'integer', 'min:0', 'max:100'],
            'dkn_terverifikasi' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->service->verifikasiDanTeruskanPengelola($sidang, $data, Auth::user()),
            'Verifikasi selesai, jadwal diteruskan ke Pengelola Layanan.'
        );
    }

    public function teruskanSkKeSekdep(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->teruskanSkKeSekdep($sidang, Auth::user()),
            'SK Penguji diteruskan ke SekDep/Koor. Prodi.'
        );
    }

    public function laporKeSekdep(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->laporKeSekdep($sidang, Auth::user()),
            'Laporan disampaikan ke SekDep/Koor. Prodi.'
        );
    }

    // ------------------------------------------------------------------
    // Pengelola Layanan
    // ------------------------------------------------------------------

    public function sahkanSk(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'nomor_sk' => ['required', 'string', 'max:100'],
            'sk_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        return $this->jalankan(
            fn () => $this->service->sahkanSkPenguji($sidang, $data, Auth::user()),
            'SK Penguji berhasil disahkan dan diterbitkan.'
        );
    }

    public function cekNilai(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->cekNilaiSimak($sidang, Auth::user()),
            'Pemeriksaan nilai SIMAK selesai dilakukan.'
        );
    }

    public function laporKePenata(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->laporKePenata($sidang, Auth::user()),
            'Laporan disampaikan ke Penata.'
        );
    }

    // ------------------------------------------------------------------
    // Pengadministrasi Perkantoran
    // ------------------------------------------------------------------

    public function siapkanAdministrasi(Request $request, Sidang $sidang): RedirectResponse
    {
        $data = $request->validate([
            'dokumen_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        return $this->jalankan(
            fn () => $this->service->siapkanAdministrasi($sidang, $data, Auth::user()),
            'Kelengkapan administrasi sidang berhasil disiapkan.'
        );
    }

    public function informasikanJadwal(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->informasikanJadwal($sidang, Auth::user()),
            'Informasi jadwal ujian telah disampaikan ke mahasiswa.'
        );
    }

    public function cekKelengkapanBerkas(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->cekKelengkapanBerkas($sidang, Auth::user()),
            'Kelengkapan berkas sebelum ujian dinyatakan lengkap.'
        );
    }

    public function serahkanBerkasUjian(Sidang $sidang): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->service->serahkanBerkasUjian($sidang, Auth::user()),
            'Berkas ujian telah diserahkan ke dosen penguji.'
        );
    }

    // ------------------------------------------------------------------
    // Dosen Penguji
    // ------------------------------------------------------------------

    public function konfirmasiBerhalangan(Request $request, Sidang $sidang): RedirectResponse
    {
        $dosen = Auth::user()->dosen;
        abort_unless($dosen, 403, 'Akun Anda belum tertaut ke data dosen.');

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->service->konfirmasiBerhalangan($sidang, $dosen, $data['alasan'], Auth::user()),
            'Konfirmasi berhalangan hadir telah dicatat. Menunggu penjadwalan ulang.'
        );
    }

    public function inputNilai(Sidang $sidang): RedirectResponse
    {
        $dosen = Auth::user()->dosen;
        abort_unless($dosen, 403, 'Akun Anda belum tertaut ke data dosen.');

        return $this->jalankan(
            fn () => $this->service->inputNilai($sidang, $dosen, Auth::user()),
            'Nilai ujian TA berhasil disimpan.'
        );
    }
}
