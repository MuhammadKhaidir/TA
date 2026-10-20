<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusSurat;
use App\Exceptions\WorkflowException;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\SuratPermohonan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permohonan surat administrasi KP. Website tidak mengarang format surat resmi:
 * Operator mengunggah surat hasil yang sudah sesuai standar kampus.
 */
class SuratService
{
    public function ajukan(Pengajuan $pengajuan, JenisSurat $jenis, ?string $catatan, User $actor): SuratPermohonan
    {
        if ($pengajuan->jenis !== JenisPengajuan::Kp) {
            throw new WorkflowException('Permohonan surat hanya tersedia untuk pengajuan KP.');
        }
        if (! $jenis->aktif) {
            throw new WorkflowException('Jenis surat tersebut sedang tidak tersedia.');
        }

        return DB::transaction(function () use ($pengajuan, $jenis, $catatan, $actor) {
            $berjalan = SuratPermohonan::query()
                ->where('pengajuan_id', $pengajuan->id)
                ->where('jenis_surat_id', $jenis->id)
                ->whereIn('status', [StatusSurat::Diajukan->value, StatusSurat::Diproses->value])
                ->lockForUpdate()
                ->exists();

            if ($berjalan) {
                throw new WorkflowException("Permohonan \"{$jenis->nama}\" Anda masih diproses.");
            }

            $surat = SuratPermohonan::create([
                'pengajuan_id' => $pengajuan->id,
                'jenis_surat_id' => $jenis->id,
                'status' => StatusSurat::Diajukan,
                'catatan_mahasiswa' => $catatan,
            ]);

            LogAktivitas::catat($actor, 'surat_diajukan', "Permohonan surat \"{$jenis->nama}\" diajukan.", $pengajuan);

            return $surat;
        });
    }

    /** Operator memproses: tandai diproses, selesai (wajib unggah surat hasil), atau tolak (wajib catatan). */
    public function proses(SuratPermohonan $surat, User $operator, StatusSurat $tujuan, ?string $catatan, ?string $nomor, ?UploadedFile $file): SuratPermohonan
    {
        if (! in_array($surat->status, [StatusSurat::Diajukan, StatusSurat::Diproses], true)) {
            throw new WorkflowException("Permohonan sudah berstatus \"{$surat->status->label()}\".");
        }
        if (! in_array($tujuan, [StatusSurat::Diproses, StatusSurat::Selesai, StatusSurat::Ditolak], true)) {
            throw new WorkflowException('Status tujuan tidak valid.');
        }
        if ($tujuan === StatusSurat::Selesai && ! $file) {
            throw new WorkflowException('Unggah surat hasil sebelum menandai permohonan selesai.');
        }
        if ($tujuan === StatusSurat::Ditolak && blank($catatan)) {
            throw new WorkflowException('Catatan wajib diisi saat menolak permohonan.');
        }

        $path = $file?->store("surat/{$surat->pengajuan_id}", PengajuanService::DISK);

        try {
            DB::transaction(function () use ($surat, $operator, $tujuan, $catatan, $nomor, $file, $path) {
                $surat->forceFill([
                    'status' => $tujuan,
                    'catatan_operator' => $catatan,
                    'nomor_surat' => $nomor ?: $surat->nomor_surat,
                    'diproses_oleh' => $operator->id,
                    'diproses_pada' => now(),
                ]);
                if ($path) {
                    $surat->file_path = $path;
                    $surat->nama_file_asli = $file->getClientOriginalName();
                }
                $surat->save();

                LogAktivitas::catat($operator, 'surat_'.$tujuan->value, "Surat \"{$surat->jenisSurat->nama}\": {$tujuan->label()}".($catatan ? " — {$catatan}" : ''), $surat->pengajuan);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(PengajuanService::DISK)->delete($path);
            }
            throw $e;
        }

        return $surat;
    }
}
