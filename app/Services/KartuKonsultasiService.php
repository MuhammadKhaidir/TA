<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Bimbingan;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use Illuminate\Support\Collection;

/**
 * Kartu konsultasi TA dalam bentuk PDF, disusun dari catatan bimbingan mahasiswa.
 *
 * Bentuk kartu (kop, kolom, tanda tangan) belum dipastikan fakultas [K-04], jadi tampilannya
 * dipisah dari logika: seluruh isi kartu ada di satu view Blade (`config('ta.kartu_view')`,
 * bawaan `pdf.kartu-konsultasi`). Mengganti format cukup mengganti view itu; service ini
 * hanya menyediakan datanya dan tidak perlu diubah.
 */
class KartuKonsultasiService
{
    public function __construct(private readonly SkPdfRenderer $renderer) {}

    /**
     * Alasan kartu belum boleh diunduh, atau null bila boleh. Terkunci sebelum SK TA terbit
     * selama pengaturan `kartu.butuh_sk` aktif [K-04].
     */
    public function alasanTerkunci(Pengajuan $p): ?string
    {
        if ($p->jenis !== JenisPengajuan::Ta) {
            return 'Kartu konsultasi hanya tersedia untuk Tugas Akhir.';
        }
        if (Pengaturan::kartuButuhSk() && ! $p->suratKeputusan()->exists()) {
            return 'Kartu konsultasi dapat diunduh setelah SK TA terbit.';
        }

        return null;
    }

    public function terkunci(Pengajuan $p): bool
    {
        return $this->alasanTerkunci($p) !== null;
    }

    /** @return Collection<int, Bimbingan> urut tanggal naik */
    public function bimbingan(Pengajuan $p): Collection
    {
        return Bimbingan::query()
            ->where('mahasiswa_id', $p->mahasiswa_id)
            ->where('jenis', JenisPengajuan::Ta->value)
            ->orderBy('tanggal')->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> data yang diterima view kartu */
    public function data(Pengajuan $p): array
    {
        $p->loadMissing(['mahasiswa', 'prodi', 'dospem', 'dospem2', 'suratKeputusan']);

        return [
            'pengajuan' => $p,
            'mahasiswa' => $p->mahasiswa,
            'prodi' => $p->prodi,
            'pembimbing1' => $p->dospem,
            'pembimbing2' => $p->dospem2,
            'sk' => $p->suratKeputusan,
            'bimbingans' => $this->bimbingan($p),
            'dicetak' => now(),
        ];
    }

    public function renderHtml(Pengajuan $p): string
    {
        return view(config('ta.kartu_view', 'pdf.kartu-konsultasi'), $this->data($p))->render();
    }

    /** PDF kartu konsultasi. @throws WorkflowException bila terkunci atau PDF gagal dibuat */
    public function render(Pengajuan $p): string
    {
        if ($alasan = $this->alasanTerkunci($p)) {
            throw new WorkflowException($alasan);
        }

        try {
            return $this->renderer->render($this->renderHtml($p));
        } catch (WorkflowException) {
            throw new WorkflowException('Kartu konsultasi gagal dibuat. Silakan coba lagi.');
        }
    }
}
