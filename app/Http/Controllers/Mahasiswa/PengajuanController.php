<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\PembimbingService;
use App\Services\PengajuanWorkflow;
use App\Support\Aktivitas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengajuanController extends Controller
{
    public function __construct(
        private PengajuanWorkflow $workflow,
        private PembimbingService $pembimbing,
    ) {}

    public function index(Request $request, string $jenis): View
    {
        $jenis = JenisPengajuan::from($jenis);
        $user = $request->user();

        $daftar = Pengajuan::with(['pembimbing', 'pembimbingPilihan'])
            ->where('mahasiswa_id', $user->id)
            ->jenis($jenis)
            ->orderByDesc('updated_at')
            ->get();

        return view('mahasiswa.pengajuan.index', [
            'jenis' => $jenis,
            'daftar' => $daftar,
            'bisaBuat' => ! $this->punyaBerjalan($user, $jenis),
        ]);
    }

    public function create(Request $request, string $jenis): View|RedirectResponse
    {
        $jenis = JenisPengajuan::from($jenis);

        if ($this->punyaBerjalan($request->user(), $jenis)) {
            return $this->tolakDuplikat($jenis);
        }

        return view('mahasiswa.pengajuan.form', [
            'jenis' => $jenis,
            'pengajuan' => null,
            'bisaPilihPembimbing' => $jenis === JenisPengajuan::Ta,
            'dosen' => $this->daftarDosen(),
            'persyaratan' => $this->persyaratanAktif($jenis),
        ]);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $jenis = JenisPengajuan::from($jenis);
        $user = $request->user();

        if ($this->punyaBerjalan($user, $jenis)) {
            return $this->tolakDuplikat($jenis);
        }

        // KP: pembimbing ditetapkan operator, tidak dipilih mahasiswa. TA: mahasiswa memilih dosen.
        $data = $this->validasi($request, $jenis, $jenis === JenisPengajuan::Ta);
        $pilihan = Arr::pull($data, 'pembimbing_id');

        $pengajuan = DB::transaction(function () use ($data, $jenis, $user, $pilihan) {
            $pengajuan = Pengajuan::create($data + [
                'mahasiswa_id' => $user->id,
                'jenis' => $jenis,
                'status' => StatusPengajuan::Draft,
            ]);

            $this->workflow->catatAwal($pengajuan, $user);

            if ($pilihan !== null) {
                $this->pembimbing->pilih($pengajuan, User::findOrFail($pilihan), $user);
            }

            return $pengajuan;
        });

        return redirect()->route('mahasiswa.pengajuan.show', $pengajuan)
            ->with('sukses', 'Pengajuan dibuat sebagai draft. Unggah dokumen persyaratan, lalu kirim pengajuan.');
    }

    public function show(Pengajuan $pengajuan): View
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load([
            'mahasiswa.programStudi', 'pembimbing', 'pembimbingPilihan', 'riwayat.user',
            'riwayatPembimbing.dosen', 'riwayatPembimbing.oleh', 'dokumenResmi',
        ]);

        return view('mahasiswa.pengajuan.show', ['pengajuan' => $pengajuan] + $this->workflow->dataDokumen($pengajuan));
    }

    public function edit(Pengajuan $pengajuan): View
    {
        $this->authorize('update', $pengajuan);

        return view('mahasiswa.pengajuan.form', [
            'jenis' => $pengajuan->jenis,
            'pengajuan' => $pengajuan,
            'bisaPilihPembimbing' => $this->bisaPilihPembimbing($pengajuan),
            'dosen' => $this->daftarDosen(),
            'persyaratan' => $this->persyaratanAktif($pengajuan->jenis),
        ]);
    }

    public function update(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('update', $pengajuan);

        // Dosen pilihan TA hanya dapat diganti selama draft. Setelah dikirim, penggantian lewat operator.
        $bisaPilih = $this->bisaPilihPembimbing($pengajuan);
        $data = $this->validasi($request, $pengajuan->jenis, $bisaPilih);
        $pilihan = Arr::pull($data, 'pembimbing_id');

        DB::transaction(function () use ($pengajuan, $data, $pilihan, $request, $bisaPilih) {
            $pengajuan->update($data);

            if ($bisaPilih && $pilihan !== null) {
                $this->pembimbing->pilih($pengajuan, User::findOrFail($pilihan), $request->user());
            }
        });

        Aktivitas::catat('pengajuan.ubah', "Mengubah data pengajuan {$pengajuan->jenis->singkat()} #{$pengajuan->id}", $pengajuan);

        return redirect()->route('mahasiswa.pengajuan.show', $pengajuan)->with('sukses', 'Data pengajuan diperbarui.');
    }

    public function ajukan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('update', $pengajuan);

        $this->workflow->ajukan($pengajuan, $request->user());

        return redirect()->route('mahasiswa.pengajuan.show', $pengajuan)
            ->with('sukses', 'Pengajuan berhasil dikirim.');
    }

    public function destroy(Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorize('delete', $pengajuan);

        $jenis = $pengajuan->jenis;
        $id = $pengajuan->id;

        Storage::disk('local')->deleteDirectory("pengajuan/{$id}");
        $pengajuan->delete();

        Aktivitas::catat('pengajuan.hapus', "Menghapus draft pengajuan {$jenis->singkat()} #{$id}");

        return redirect()->route('mahasiswa.pengajuan.index', $jenis->value)->with('sukses', 'Draft pengajuan dihapus.');
    }

    /** Mahasiswa hanya memilih pembimbing untuk TA yang masih draft. */
    private function bisaPilihPembimbing(Pengajuan $pengajuan): bool
    {
        return $pengajuan->jenis === JenisPengajuan::Ta && $pengajuan->status === StatusPengajuan::Draft;
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, JenisPengajuan $jenis, bool $denganPilihanPembimbing): array
    {
        $aturan = [
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'instansi' => [$jenis === JenisPengajuan::Kp ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        if ($denganPilihanPembimbing) {
            $aturan['pembimbing_id'] = [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', Role::Dosen->value)->where('aktif', true)),
            ];
        }

        return $request->validate($aturan);
    }

    /** Satu mahasiswa hanya boleh memiliki satu pengajuan per jenis yang belum diarsipkan. */
    private function punyaBerjalan(User $user, JenisPengajuan $jenis): bool
    {
        return Pengajuan::where('mahasiswa_id', $user->id)
            ->jenis($jenis)
            ->where('status', '!=', StatusPengajuan::Diarsipkan->value)
            ->exists();
    }

    private function tolakDuplikat(JenisPengajuan $jenis): RedirectResponse
    {
        return redirect()->route('mahasiswa.pengajuan.index', $jenis->value)
            ->with('galat', "Anda sudah memiliki pengajuan {$jenis->singkat()} yang sedang berjalan.");
    }

    /** @return Collection<int, User> */
    private function daftarDosen(): Collection
    {
        return User::peran(Role::Dosen)->aktif()->orderBy('name')->get();
    }

    /** @return Collection<int, Persyaratan> */
    private function persyaratanAktif(JenisPengajuan $jenis): Collection
    {
        return Persyaratan::aktif()->untuk($jenis)->orderBy('urutan')->orderBy('id')->get();
    }
}
