<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\RiwayatPembimbing;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh perubahan dosen pembimbing lewat kelas ini.
 *
 * Aturan:
 *  - KP : mahasiswa tidak memilih. Operator menetapkan dosen dari daftar dosen di database.
 *  - TA : mahasiswa memilih dosen saat pengajuan (draft). Dosen tersebut menerima atau menolak
 *         (alasan wajib). Bila ditolak, operator menetapkan dosen pengganti, sebaiknya yang
 *         beban bimbingannya paling sedikit.
 *  - pembimbing_id hanya terisi bila pembimbing sudah sah (diterima atau ditetapkan).
 */
class PembimbingService
{
    /** Mahasiswa memilih (atau mengganti) dosen pembimbing TA selama pengajuan masih draft. */
    public function pilih(Pengajuan $pengajuan, User $dosen, User $oleh): void
    {
        if ($pengajuan->jenis !== JenisPengajuan::Ta) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen pembimbing KP ditetapkan oleh operator akademik.']);
        }

        if ($pengajuan->status !== StatusPengajuan::Draft) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen pilihan hanya dapat diubah selama pengajuan masih draft.']);
        }

        if ($dosen->role !== Role::Dosen || ! $dosen->aktif) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen yang dipilih tidak tersedia.']);
        }

        if ((int) $pengajuan->pembimbing_pilihan_id === (int) $dosen->id
            && $pengajuan->status_pembimbing === StatusPembimbing::Menunggu) {
            return;
        }

        DB::transaction(function () use ($pengajuan, $dosen, $oleh) {
            $pengajuan->forceFill([
                'pembimbing_pilihan_id' => $dosen->id,
                'pembimbing_id' => null,
                'status_pembimbing' => StatusPembimbing::Menunggu,
            ])->save();

            $this->catat($pengajuan, $dosen, $oleh, StatusPembimbing::Menunggu, null,
                "Memilih {$dosen->name} sebagai calon pembimbing TA #{$pengajuan->id}");
        });
    }

    /** Dosen pilihan mahasiswa menerima atau menolak permintaan sebagai pembimbing TA. */
    public function konfirmasi(Pengajuan $pengajuan, User $dosen, bool $terima, ?string $catatan = null): void
    {
        $catatan = filled($catatan) ? trim($catatan) : null;

        if ($pengajuan->jenis !== JenisPengajuan::Ta
            || $pengajuan->status_pembimbing !== StatusPembimbing::Menunggu
            || (int) $pengajuan->pembimbing_pilihan_id !== (int) $dosen->id
            || $pengajuan->status === StatusPengajuan::Draft) {
            throw ValidationException::withMessages(['keputusan' => 'Permintaan ini sudah tidak menunggu konfirmasi Anda.']);
        }

        if (! $terima && $catatan === null) {
            throw ValidationException::withMessages(['catatan' => 'Alasan penolakan wajib diisi agar mahasiswa dan operator mengetahuinya.']);
        }

        DB::transaction(function () use ($pengajuan, $dosen, $terima, $catatan) {
            $pengajuan->forceFill([
                'pembimbing_id' => $terima ? $dosen->id : null,
                'status_pembimbing' => $terima ? StatusPembimbing::Diterima : StatusPembimbing::Ditolak,
            ])->save();

            $this->catat(
                $pengajuan,
                $dosen,
                $dosen,
                $terima ? StatusPembimbing::Diterima : StatusPembimbing::Ditolak,
                $catatan,
                ($terima ? 'Menerima' : 'Menolak')." permintaan pembimbing TA #{$pengajuan->id}",
            );
        });
    }

    /**
     * Operator menetapkan (atau mengganti) dosen pembimbing, untuk KP maupun TA.
     * Catatan wajib bila operator menggantikan permintaan yang masih menunggu atau pembimbing yang sudah diterima.
     */
    public function tetapkan(Pengajuan $pengajuan, User $dosen, User $operator, ?string $catatan = null): void
    {
        $catatan = filled($catatan) ? trim($catatan) : null;

        if ($pengajuan->status === StatusPengajuan::Diarsipkan) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Pengajuan yang sudah diarsipkan tidak dapat diubah.']);
        }

        if ($dosen->role !== Role::Dosen || ! $dosen->aktif) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen yang dipilih tidak aktif.']);
        }

        if ($pengajuan->pembimbing_id !== null && (int) $pengajuan->pembimbing_id === (int) $dosen->id) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen tersebut sudah menjadi pembimbing pengajuan ini.']);
        }

        if ($this->idDosenYangMenolak($pengajuan)->contains($dosen->id)) {
            throw ValidationException::withMessages(['pembimbing_id' => 'Dosen tersebut sudah menolak pengajuan ini.']);
        }

        $menggantikan = in_array($pengajuan->status_pembimbing, [StatusPembimbing::Menunggu, StatusPembimbing::Diterima], true);

        if ($menggantikan && $catatan === null) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi karena Anda menggantikan pembimbing yang dipilih atau sudah diterima.']);
        }

        DB::transaction(function () use ($pengajuan, $dosen, $operator, $catatan) {
            $pengajuan->forceFill([
                'pembimbing_id' => $dosen->id,
                'status_pembimbing' => StatusPembimbing::Ditetapkan,
            ])->save();

            $this->catat($pengajuan, $dosen, $operator, StatusPembimbing::Ditetapkan, $catatan,
                "Menetapkan {$dosen->name} sebagai pembimbing {$pengajuan->jenis->singkat()} #{$pengajuan->id}");
        });
    }

    /**
     * Daftar dosen aktif untuk penetapan, diurutkan dari beban bimbingan paling sedikit.
     * Setiap dosen diberi atribut beban_aktif, beban_menunggu, dan menolak.
     *
     * @return Collection<int, User>
     */
    public function daftarDosen(Pengajuan $pengajuan): Collection
    {
        $menolak = $this->idDosenYangMenolak($pengajuan);

        return User::peran(Role::Dosen)
            ->aktif()
            ->denganBeban()
            ->with('programStudi')
            ->orderBy('beban_aktif')
            ->orderBy('beban_menunggu')
            ->orderBy('name')
            ->get()
            ->each(fn (User $d) => $d->setAttribute('menolak', $menolak->contains($d->id)));
    }

    /** @return Collection<int, int> */
    private function idDosenYangMenolak(Pengajuan $pengajuan): Collection
    {
        return RiwayatPembimbing::where('pengajuan_id', $pengajuan->id)
            ->where('status', StatusPembimbing::Ditolak->value)
            ->pluck('dosen_id')
            ->filter()
            ->map(fn ($id) => (int) $id);
    }

    private function catat(Pengajuan $pengajuan, ?User $dosen, ?User $oleh, StatusPembimbing $status, ?string $catatan, string $aktivitas): void
    {
        RiwayatPembimbing::create([
            'pengajuan_id' => $pengajuan->id,
            'dosen_id' => $dosen?->id,
            'user_id' => $oleh?->id,
            'status' => $status,
            'catatan' => $catatan,
        ]);

        Aktivitas::catat('pembimbing.'.$status->value, $aktivitas, $pengajuan, $oleh);
    }
}
