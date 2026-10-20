<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusBimbingan;
use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Bimbingan;
use App\Models\LogAktivitas;
use App\Models\Mahasiswa;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Pencatatan bimbingan TA/KP oleh mahasiswa dan validasi oleh Dospem.
 * Jumlah bimbingan selalu dihitung dari baris yang ada (bukan counter),
 * dan jumlah minimum bersifat konfigurasi (Pengaturan), tidak ditanam di kode.
 */
class BimbinganService
{
    /** Pengajuan (TA/KP terbaru yang sudah dikirim) yang menentukan siapa Dospem untuk bimbingan ini. */
    public function pengajuanTerkait(Mahasiswa|int $mahasiswa, JenisPengajuan $jenis): ?Pengajuan
    {
        $id = $mahasiswa instanceof Mahasiswa ? $mahasiswa->id : $mahasiswa;

        return Pengajuan::query()
            ->where('mahasiswa_id', $id)
            ->where('jenis', $jenis->value)
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->whereNotNull('dospem_id')
            ->latest('id')
            ->first();
    }

    public function catat(Mahasiswa $mahasiswa, JenisPengajuan $jenis, array $data, ?UploadedFile $bukti, User $actor): Bimbingan
    {
        $pengajuan = $this->pengajuanTerkait($mahasiswa, $jenis);
        if (! $pengajuan) {
            throw new WorkflowException("Bimbingan {$jenis->singkat()} dapat dicatat setelah pengajuan dikirim dan dosen pembimbing ditetapkan.");
        }

        $path = $bukti?->store("bimbingan/{$mahasiswa->id}", PengajuanService::DISK);

        try {
            $bimbingan = Bimbingan::create([
                'mahasiswa_id' => $mahasiswa->id,
                'jenis' => $jenis,
                'tanggal' => $data['tanggal'],
                'topik' => $data['topik'],
                'catatan' => $data['catatan'] ?? null,
                'tindak_lanjut' => $data['tindak_lanjut'] ?? null,
                'bukti_path' => $path,
            ]);
        } catch (UniqueConstraintViolationException) {
            if ($path) {
                Storage::disk(PengajuanService::DISK)->delete($path);
            }
            throw new WorkflowException('Bimbingan pada tanggal tersebut sudah dicatat.');
        }

        LogAktivitas::catat($actor, 'bimbingan_dicatat', "Bimbingan {$jenis->singkat()} tanggal {$bimbingan->tanggal->format('d/m/Y')} dicatat.", $pengajuan);

        return $bimbingan;
    }

    public function hapus(Bimbingan $bimbingan, User $actor): void
    {
        if ($bimbingan->status_validasi === StatusBimbingan::Divalidasi) {
            throw new WorkflowException('Bimbingan yang sudah divalidasi dosen tidak dapat dihapus.');
        }

        $path = $bimbingan->bukti_path;
        $bimbingan->delete();
        if ($path) {
            Storage::disk(PengajuanService::DISK)->delete($path);
        }
        LogAktivitas::catat($actor, 'bimbingan_dihapus', "Bimbingan tanggal {$bimbingan->tanggal->format('d/m/Y')} dihapus.");
    }

    /** Dospem memvalidasi, atau meminta perbaikan (catatan wajib) atas bimbingan mahasiswa bimbingannya. */
    public function validasi(Bimbingan $bimbingan, User $dospem, bool $setuju, ?string $catatan): Bimbingan
    {
        if (! $setuju && blank($catatan)) {
            throw new WorkflowException('Catatan wajib diisi saat meminta perbaikan bimbingan.');
        }

        $bimbingan->forceFill([
            'status_validasi' => $setuju ? StatusBimbingan::Divalidasi : StatusBimbingan::PerluRevisi,
            'catatan_dospem' => $catatan,
            'divalidasi_oleh' => $dospem->id,
            'divalidasi_pada' => now(),
        ])->save();

        LogAktivitas::catat(
            $dospem,
            $setuju ? 'bimbingan_divalidasi' : 'bimbingan_perlu_diperbaiki',
            "Bimbingan tanggal {$bimbingan->tanggal->format('d/m/Y')}".($catatan ? ": {$catatan}" : ''),
            $this->pengajuanTerkait($bimbingan->mahasiswa_id, $bimbingan->jenis),
        );

        return $bimbingan;
    }
}
