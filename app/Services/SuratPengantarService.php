<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusSuratPengantar;
use App\Enums\TugasAdministrasi;
use App\Exceptions\WorkflowException;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\SuratPengantar;
use App\Models\User;
use App\Support\HakAkses;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Surat pengantar TA: mahasiswa mengisi data + mengunggah dokumen Kumpulan A -> Operator memeriksa
 * kelengkapan -> surat diproses offline -> selesai/diambil. Surat resmi dicetak di luar sistem;
 * service ini hanya mengatur tahapnya. Pemeriksaan "siapa boleh" ada di SuratPengantarPolicy.
 */
class SuratPengantarService
{
    public function __construct(private readonly NotifikasiService $notifikasi) {}

    /** Simpan isian (membuat draft pada penyimpanan pertama). */
    public function simpanData(Pengajuan $pengajuan, array $data, User $actor): SuratPengantar
    {
        return DB::transaction(function () use ($pengajuan, $data, $actor) {
            $p = $this->kunciPengajuan($pengajuan);

            $surat = $p->suratPengantar ?? new SuratPengantar(['data' => []]);
            if ($surat->exists && ! $surat->status->bolehDiubahMahasiswa()) {
                throw new WorkflowException("Data surat pengantar tidak dapat diubah karena berstatus \"{$surat->status->label()}\".");
            }

            $isian = Arr::only($data, array_keys(SuratPengantar::daftarIsian()));
            $surat->data = array_map(fn ($nilai) => is_string($nilai) ? trim($nilai) : $nilai, $isian);

            if (! $surat->exists) {
                $surat->forceFill(['pengajuan_id' => $p->id, 'status' => StatusSuratPengantar::Draft]);
            }
            $surat->save();

            LogAktivitas::catat($actor, 'surat_pengantar_disimpan', 'Data surat pengantar TA disimpan.', $p);

            return $surat;
        });
    }

    public function kirim(SuratPengantar $surat, User $actor): SuratPengantar
    {
        return $this->dalamTransaksi($surat, function (SuratPengantar $s, Pengajuan $p) use ($actor) {
            if (! $s->status->bolehDiubahMahasiswa()) {
                throw new WorkflowException("Surat pengantar tidak dapat dikirim karena berstatus \"{$s->status->label()}\".");
            }
            $this->pastikanLengkap($s, $p);

            $ulang = $s->diajukan_pada !== null;
            $s->forceFill(['status' => StatusSuratPengantar::Diajukan, 'diajukan_pada' => now()])->save();

            LogAktivitas::catat($actor, $ulang ? 'surat_pengantar_diajukan_ulang' : 'surat_pengantar_diajukan',
                $ulang ? 'Surat pengantar diperbaiki dan diajukan ulang.' : 'Surat pengantar dikirim untuk diperiksa kelengkapannya.', $p);

            $this->notifikasi->kirim(
                HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $p->prodi_id),
                'Surat pengantar TA menunggu pemeriksaan',
                "{$p->mahasiswa->nama} ({$p->mahasiswa->nim}) mengirim surat pengantar TA.",
                route('pengajuan.show', $p, false),
                $p,
                $actor,
            );
        });
    }

    /** Operator: data dan dokumen dinyatakan lengkap. */
    public function periksa(SuratPengantar $surat, User $pemeriksa): SuratPengantar
    {
        return $this->dalamTransaksi($surat, function (SuratPengantar $s, Pengajuan $p) use ($pemeriksa) {
            if ($s->status !== StatusSuratPengantar::Diajukan) {
                throw new WorkflowException("Pemeriksaan tidak dapat dilakukan pada status \"{$s->status->label()}\".");
            }
            $this->pastikanLengkap($s, $p);

            $s->forceFill([
                'status' => StatusSuratPengantar::Lengkap,
                'diperiksa_oleh' => $pemeriksa->id,
                'diperiksa_pada' => now(),
                'catatan_operator' => null,
            ])->save();

            LogAktivitas::catat($pemeriksa, 'surat_pengantar_lengkap', 'Kelengkapan surat pengantar diverifikasi.', $p);

            $this->notifikasi->kirim(
                HakAkses::pelaku(TugasAdministrasi::ProsesSuratPengantar, $p->prodi_id),
                'Surat pengantar TA siap diproses',
                "Surat pengantar {$p->mahasiswa->nama} ({$p->mahasiswa->nim}) sudah lengkap dan menunggu diproses.",
                null,
                $p,
                $pemeriksa,
            );
        });
    }

    public function kembalikan(SuratPengantar $surat, User $pemeriksa, string $catatan): SuratPengantar
    {
        return $this->dalamTransaksi($surat, function (SuratPengantar $s, Pengajuan $p) use ($pemeriksa, $catatan) {
            if (! in_array($s->status, [StatusSuratPengantar::Diajukan, StatusSuratPengantar::Lengkap], true)) {
                throw new WorkflowException("Surat pengantar tidak dapat dikembalikan pada status \"{$s->status->label()}\".");
            }
            if (blank($catatan)) {
                throw new WorkflowException('Catatan perbaikan wajib diisi.');
            }

            $s->forceFill([
                'status' => StatusSuratPengantar::PerluRevisi,
                'diperiksa_oleh' => $pemeriksa->id,
                'diperiksa_pada' => now(),
                'catatan_operator' => $catatan,
            ])->save();

            LogAktivitas::catat($pemeriksa, 'surat_pengantar_revisi', $catatan, $p);

            $this->notifikasi->kirim(
                [$p->mahasiswa->user],
                'Surat pengantar TA perlu diperbaiki',
                $catatan,
                route('pengajuan.show', $p, false),
                $p,
                $pemeriksa,
            );
        });
    }

    /** Pelaku tugas ProsesSuratPengantar: surat resmi mulai diproses offline. Nomor surat opsional. */
    public function proses(SuratPengantar $surat, User $pelaku, ?string $nomorSurat = null): SuratPengantar
    {
        return $this->dalamTransaksi($surat, function (SuratPengantar $s, Pengajuan $p) use ($pelaku, $nomorSurat) {
            if ($s->status !== StatusSuratPengantar::Lengkap) {
                throw new WorkflowException("Surat pengantar hanya dapat diproses setelah dinyatakan lengkap (status sekarang \"{$s->status->label()}\").");
            }

            $s->forceFill([
                'status' => StatusSuratPengantar::Diproses,
                'nomor_surat' => filled($nomorSurat) ? trim($nomorSurat) : null,
                'diproses_oleh' => $pelaku->id,
                'diproses_pada' => now(),
            ])->save();

            LogAktivitas::catat($pelaku, 'surat_pengantar_diproses', 'Surat pengantar sedang diproses secara offline'.($s->nomor_surat ? " (nomor {$s->nomor_surat})" : '').'.', $p);

            $this->notifikasi->kirim(
                [$p->mahasiswa->user],
                'Surat pengantar TA sedang diproses',
                'Surat pengantar Anda sedang diproses. Anda akan diberi tahu saat sudah bisa diambil.',
                route('pengajuan.show', $p, false),
                $p,
                $pelaku,
            );
        });
    }

    /** Surat resmi sudah jadi dan diambil mahasiswa; mahasiswa boleh mengunggah surat balasan. */
    public function selesai(SuratPengantar $surat, User $pelaku): SuratPengantar
    {
        return $this->dalamTransaksi($surat, function (SuratPengantar $s, Pengajuan $p) use ($pelaku) {
            if ($s->status !== StatusSuratPengantar::Diproses) {
                throw new WorkflowException("Surat pengantar hanya dapat diselesaikan setelah diproses (status sekarang \"{$s->status->label()}\").");
            }

            $s->forceFill(['status' => StatusSuratPengantar::Selesai, 'diambil_pada' => now()])->save();

            LogAktivitas::catat($pelaku, 'surat_pengantar_selesai', 'Surat pengantar selesai dan diserahkan kepada mahasiswa.', $p);

            $this->notifikasi->kirim(
                [$p->mahasiswa->user],
                'Surat pengantar TA sudah dapat diambil',
                'Setelah surat dibalas instansi tujuan, unggah surat balasan di halaman pengajuan Anda.',
                route('pengajuan.show', $p, false),
                $p,
                $pelaku,
            );
        });
    }

    private function pastikanLengkap(SuratPengantar $s, Pengajuan $p): void
    {
        $kurangData = $s->isianKurang();
        if ($kurangData) {
            throw new WorkflowException('Data surat pengantar belum lengkap: '.implode(', ', $kurangData).'.');
        }

        $kurangDokumen = $p->persyaratanPengantarKurang();
        if ($kurangDokumen->isNotEmpty()) {
            throw new WorkflowException('Dokumen surat pengantar belum lengkap: '.$kurangDokumen->pluck('nama')->implode(', ').'.');
        }
    }

    /** Pengajuan TA yang masih hidup dikunci, lalu dikembalikan dalam keadaan segar. */
    private function kunciPengajuan(Pengajuan $pengajuan): Pengajuan
    {
        $p = Pengajuan::query()->whereKey($pengajuan->id)->lockForUpdate()->with(['mahasiswa.user', 'prodi', 'suratPengantar'])->firstOrFail();

        if ($p->jenis !== JenisPengajuan::Ta) {
            throw new WorkflowException('Surat pengantar hanya tersedia untuk pengajuan Tugas Akhir.');
        }
        if ($p->status->berhenti()) {
            throw new WorkflowException("Pengajuan berstatus \"{$p->status->label()}\", surat pengantar tidak dapat diubah.");
        }

        return $p;
    }

    /** Jalankan aksi pada surat yang dikunci beserta pengajuan induknya. */
    private function dalamTransaksi(SuratPengantar $surat, callable $aksi): SuratPengantar
    {
        return DB::transaction(function () use ($surat, $aksi) {
            $segar = SuratPengantar::query()->whereKey($surat->id)->lockForUpdate()->firstOrFail();
            $p = $this->kunciPengajuan($segar->pengajuan);
            $aksi($segar, $p);

            return $segar->fresh();
        });
    }
}
