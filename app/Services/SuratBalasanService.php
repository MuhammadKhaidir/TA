<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusSuratBalasan;
use App\Enums\StatusSuratPengantar;
use App\Enums\TugasAdministrasi;
use App\Exceptions\WorkflowException;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\SuratBalasan;
use App\Models\User;
use App\Support\HakAkses;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Surat balasan dari instansi tujuan: mahasiswa mengunggah setelah surat pengantar selesai,
 * Operator memvalidasi dengan checklist. Surat balasan yang Valid adalah gerbang untuk
 * mengirim pengajuan SK dan menerbitkan/mencatat SK.
 */
class SuratBalasanService
{
    public function __construct(private readonly NotifikasiService $notifikasi) {}

    public function unggah(Pengajuan $pengajuan, UploadedFile $file, User $actor): SuratBalasan
    {
        $path = $file->store("surat-balasan/{$pengajuan->id}", PengajuanService::DISK);
        $lama = null;

        try {
            $surat = DB::transaction(function () use ($pengajuan, $file, $path, $actor, &$lama) {
                $p = Pengajuan::query()->whereKey($pengajuan->id)->lockForUpdate()->with(['mahasiswa', 'prodi', 'suratPengantar', 'suratBalasan'])->firstOrFail();

                if ($p->jenis !== JenisPengajuan::Ta) {
                    throw new WorkflowException('Surat balasan hanya tersedia untuk pengajuan Tugas Akhir.');
                }
                if ($p->status->berhenti()) {
                    throw new WorkflowException("Pengajuan berstatus \"{$p->status->label()}\", surat balasan tidak dapat diunggah.");
                }
                if ($p->suratPengantar?->status !== StatusSuratPengantar::Selesai) {
                    throw new WorkflowException('Surat balasan dapat diunggah setelah surat pengantar selesai dan diambil.');
                }
                if ($p->suratBalasan?->status === StatusSuratBalasan::Valid) {
                    throw new WorkflowException('Surat balasan Anda sudah dinyatakan valid.');
                }

                $lama = $p->suratBalasan?->file_path;

                $surat = $p->suratBalasan ?? new SuratBalasan;
                $ulang = $surat->exists;
                $surat->forceFill([
                    'pengajuan_id' => $p->id,
                    'file_path' => $path,
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'status' => StatusSuratBalasan::Menunggu,
                    'checklist' => null,
                    'catatan_operator' => null,
                    'uploaded_by' => $actor->id,
                    'divalidasi_oleh' => null,
                    'divalidasi_pada' => null,
                ])->save();

                LogAktivitas::catat($actor, $ulang ? 'surat_balasan_diunggah_ulang' : 'surat_balasan_diunggah', 'Surat balasan diunggah.', $p);

                $this->notifikasi->kirim(
                    HakAkses::pelaku(TugasAdministrasi::ValidasiSuratBalasan, $p->prodi_id),
                    'Surat balasan menunggu validasi',
                    "{$p->mahasiswa->nama} ({$p->mahasiswa->nim}) mengunggah surat balasan.",
                    route('pengajuan.show', $p, false),
                    $p,
                    $actor,
                );

                return $surat;
            });
        } catch (\Throwable $e) {
            Storage::disk(PengajuanService::DISK)->delete($path);
            throw $e;
        }

        if ($lama && $lama !== $path) {
            Storage::disk(PengajuanService::DISK)->delete($lama);
        }

        return $surat;
    }

    /**
     * Validasi dengan checklist. Valid hanya bila SEMUA butir dicentang; selain itu status
     * Tidak valid dan catatan wajib supaya mahasiswa tahu apa yang harus diperbaiki.
     *
     * @param  array<string, mixed>  $checklist  kunci butir => dicentang atau tidak
     */
    public function validasi(SuratBalasan $surat, User $operator, array $checklist, ?string $catatan): SuratBalasan
    {
        return DB::transaction(function () use ($surat, $operator, $checklist, $catatan) {
            $s = SuratBalasan::query()->whereKey($surat->id)->lockForUpdate()->firstOrFail();
            $p = Pengajuan::query()->with(['mahasiswa.user', 'prodi'])->findOrFail($s->pengajuan_id);

            if ($s->status !== StatusSuratBalasan::Menunggu) {
                throw new WorkflowException("Surat balasan sudah berstatus \"{$s->status->label()}\".");
            }
            if ($p->status->berhenti()) {
                throw new WorkflowException("Pengajuan berstatus \"{$p->status->label()}\", surat balasan tidak dapat divalidasi.");
            }

            $hasil = [];
            foreach (array_keys(SuratBalasan::daftarChecklist()) as $kunci) {
                $hasil[$kunci] = filter_var($checklist[$kunci] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            $valid = $hasil !== [] && ! in_array(false, $hasil, true);
            if (! $valid && blank($catatan)) {
                throw new WorkflowException('Ada butir checklist yang belum terpenuhi: catatan perbaikan wajib diisi.');
            }

            $s->forceFill([
                'status' => $valid ? StatusSuratBalasan::Valid : StatusSuratBalasan::TidakValid,
                'checklist' => $hasil,
                'catatan_operator' => filled($catatan) ? trim($catatan) : null,
                'divalidasi_oleh' => $operator->id,
                'divalidasi_pada' => now(),
            ])->save();

            LogAktivitas::catat(
                $operator,
                $valid ? 'surat_balasan_valid' : 'surat_balasan_tidak_valid',
                $valid ? 'Surat balasan dinyatakan valid.' : 'Surat balasan tidak valid: '.trim((string) $catatan),
                $p,
            );

            $this->notifikasi->kirim(
                [$p->mahasiswa->user],
                $valid ? 'Surat balasan valid' : 'Surat balasan perlu diunggah ulang',
                $valid ? 'Anda sekarang dapat mengirim pengajuan SK.' : trim((string) $catatan),
                route('pengajuan.show', $p, false),
                $p,
                $operator,
            );

            return $s;
        });
    }
}
