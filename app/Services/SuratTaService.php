<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusDokumen;
use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Models\SuratBalasan;
use App\Models\User;
use App\Support\Aktivitas;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alur surat Tugas Akhir. Hak melakukan tiap langkah diputuskan PengajuanPolicy sebelum
 * kelas ini dipanggil; kelas ini mengerjakan penyimpanan berkas, pencatatan, dan log.
 *
 *   surat pengantar (operator) -> surat balasan (mahasiswa) -> pemeriksaan operator -> SK pembimbing
 */
class SuratTaService
{
    /** Operator menerbitkan surat pengantar TA sebagai dokumen resmi. */
    public function terbitkanPengantar(Pengajuan $pengajuan, User $operator, string $nomor, string|CarbonInterface $tanggal, UploadedFile $berkas): DokumenResmi
    {
        $path = $berkas->store("dokumen-resmi/{$pengajuan->id}", 'local');

        $dokumen = DokumenResmi::create([
            'pengajuan_id' => $pengajuan->id,
            'diunggah_oleh' => $operator->id,
            'jenis' => JenisPengajuan::SURAT_PENGANTAR_TA,
            'nomor' => $nomor,
            'tanggal' => $tanggal,
            'nama_asli' => Str::limit(basename($berkas->getClientOriginalName()), 200, ''),
            'path' => $path,
            'mime' => $berkas->getMimeType(),
            'ukuran' => $berkas->getSize(),
        ]);

        $pengajuan->unsetRelation('dokumenResmi');

        Aktivitas::catat(
            'surat-pengantar.terbit',
            "Menerbitkan surat pengantar TA nomor {$nomor} untuk pengajuan TA #{$pengajuan->id}",
            $dokumen,
            $operator,
        );

        return $dokumen;
    }

    /** Menarik surat pengantar (hanya selama belum ada surat balasan; dijaga policy). */
    public function hapusPengantar(Pengajuan $pengajuan, DokumenResmi $pengantar, User $operator): void
    {
        Storage::disk('local')->delete($pengantar->path);
        $pengantar->delete();
        $pengajuan->unsetRelation('dokumenResmi');

        Aktivitas::catat(
            'surat-pengantar.hapus',
            "Menarik surat pengantar TA nomor {$pengantar->nomor} dari pengajuan TA #{$pengajuan->id}",
            $pengajuan,
            $operator,
        );
    }

    /**
     * Mahasiswa mengunggah surat balasan. Berkas lama tidak dihapus: versi baru menjadi versi terbaru
     * dan yang lama tersimpan sebagai jejak.
     */
    public function unggahBalasan(Pengajuan $pengajuan, User $mahasiswa, UploadedFile $berkas): SuratBalasan
    {
        $path = $berkas->store("surat-balasan/{$pengajuan->id}", 'local');

        $balasan = DB::transaction(function () use ($pengajuan, $mahasiswa, $berkas, $path) {
            $versi = (int) SuratBalasan::where('pengajuan_id', $pengajuan->id)->max('versi') + 1;

            SuratBalasan::where('pengajuan_id', $pengajuan->id)->update(['terbaru' => false]);

            return SuratBalasan::create([
                'pengajuan_id' => $pengajuan->id,
                'diunggah_oleh' => $mahasiswa->id,
                'versi' => $versi,
                'terbaru' => true,
                'nama_asli' => Str::limit(basename($berkas->getClientOriginalName()), 200, ''),
                'path' => $path,
                'mime' => $berkas->getMimeType(),
                'ukuran' => $berkas->getSize(),
                'status' => StatusDokumen::Menunggu,
            ]);
        });

        $pengajuan->unsetRelation('suratBalasan');

        Aktivitas::catat(
            'surat-balasan.unggah',
            "Mengunggah surat balasan TA (versi {$balasan->versi}) untuk pengajuan TA #{$pengajuan->id}",
            $balasan,
            $mahasiswa,
        );

        return $balasan;
    }

    /**
     * Operator menerima atau meminta revisi surat balasan.
     *
     * @param  int  $balasanId  id surat balasan yang sedang dilihat operator; ditolak bila mahasiswa sudah menggantinya
     */
    public function periksaBalasan(Pengajuan $pengajuan, User $operator, int $balasanId, StatusDokumen $keputusan, ?string $catatan): SuratBalasan
    {
        $balasan = $pengajuan->balasanTerbaru();

        if ($balasan === null || (int) $balasan->id !== $balasanId) {
            throw ValidationException::withMessages([
                'keputusan' => 'Surat balasan sudah berubah (mahasiswa mengunggah versi baru). Muat ulang halaman lalu periksa lagi.',
            ]);
        }

        if ($keputusan === StatusDokumen::Revisi && blank($catatan)) {
            throw ValidationException::withMessages([
                'catatan_balasan' => 'Catatan revisi wajib diisi.',
            ]);
        }

        $balasan->update([
            'status' => $keputusan,
            'diperiksa_oleh' => $operator->id,
            'diperiksa_at' => now(),
            'catatan' => $keputusan === StatusDokumen::Revisi ? $catatan : null,
        ]);

        $pengajuan->unsetRelation('suratBalasan');

        Aktivitas::catat(
            'surat-balasan.periksa',
            "Surat balasan TA (versi {$balasan->versi}) pengajuan TA #{$pengajuan->id}: {$keputusan->label()}",
            $balasan,
            $operator,
        );

        return $balasan;
    }
}
