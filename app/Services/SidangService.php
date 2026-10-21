<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Enums\StatusBimbingan;
use App\Enums\StatusSidangTa;
use App\Enums\TugasAdministrasi;
use App\Exceptions\WorkflowException;
use App\Models\Bimbingan;
use App\Models\Dosen;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\SidangTa;
use App\Models\SidangTaDokumen;
use App\Models\SidangTaPenguji;
use App\Models\User;
use App\Support\HakAkses;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Modul sidang TA: pengajuan (SK terbit sebagai gerbang), verifikasi kelengkapan Operator,
 * persetujuan Pembimbing 1 dan 2 (dengan pemeriksaan minimal bimbingan [K-05]), penjadwalan
 * beserta penguji dan pemeriksaan bentrok jadwal, lalu konfirmasi Sidang Selesai [K-09].
 *
 * Service ini hanya memeriksa kondisi data/status; "siapa boleh" ditentukan SidangTaPolicy
 * (yang membaca HakAkses) di controller / panel Admin.
 */
class SidangService
{
    public function __construct(
        private readonly NotifikasiService $notifikasi,
        private readonly KartuKonsultasiService $kartu,
    ) {}

    // ------------------------------------------------------------------
    // Mahasiswa
    // ------------------------------------------------------------------

    /**
     * Membuat draft pengajuan sidang untuk TA yang SK-nya sudah terbit. Hanya satu pengajuan sidang
     * yang boleh aktif per TA (pengajuan ulang hanya setelah yang lama dibatalkan).
     */
    public function buat(Pengajuan $ta, User $actor): SidangTa
    {
        return DB::transaction(function () use ($ta, $actor) {
            $p = Pengajuan::query()->whereKey($ta->id)->lockForUpdate()->with(['mahasiswa'])->firstOrFail();

            $this->pastikanSkTerbit($p);

            if ($p->sidangs()->whereIn('status', StatusSidangTa::nilaiAktif())->exists()) {
                throw new WorkflowException('Pengajuan sidang untuk TA ini sudah ada.');
            }

            $sidang = new SidangTa;
            $sidang->forceFill([
                'pengajuan_id' => $p->id,
                'mahasiswa_id' => $p->mahasiswa_id,
                'status' => StatusSidangTa::Draft,
            ])->save();

            LogAktivitas::catat($actor, 'sidang_dibuat', 'Draft pengajuan sidang dibuat.', $sidang);

            return $sidang;
        });
    }

    public function unggahDokumen(SidangTa $sidang, Persyaratan $persyaratan, UploadedFile $file, User $actor): SidangTaDokumen
    {
        $sidang->loadMissing('pengajuan');
        $this->pastikanBolehDiubah($sidang);

        if ($persyaratan->jenis !== JenisPersyaratan::TaSidang
            || ! $persyaratan->aktif
            || ($persyaratan->prodi_id !== null && $persyaratan->prodi_id !== $sidang->pengajuan->prodi_id)) {
            throw new WorkflowException('Persyaratan tersebut tidak berlaku untuk pengajuan sidang Anda.');
        }
        if ($persyaratan->tautan_otomatis !== null) {
            throw new WorkflowException("Dokumen \"{$persyaratan->nama}\" ditautkan otomatis oleh sistem, tidak perlu diunggah.");
        }

        $lama = $sidang->dokumens()->where('persyaratan_id', $persyaratan->id)->first();
        $path = $file->store("sidang/{$sidang->id}", PengajuanService::DISK);

        try {
            $dokumen = DB::transaction(function () use ($sidang, $persyaratan, $file, $path, $actor) {
                $segar = SidangTa::query()->whereKey($sidang->id)->lockForUpdate()->firstOrFail();
                $this->pastikanBolehDiubah($segar);

                $dokumen = SidangTaDokumen::query()->updateOrCreate(
                    ['sidang_ta_id' => $sidang->id, 'persyaratan_id' => $persyaratan->id],
                    ['file_path' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'uploaded_by' => $actor->id],
                );
                LogAktivitas::catat($actor, 'sidang_dokumen_diunggah', "Dokumen sidang \"{$persyaratan->nama}\" diunggah.", $sidang);

                return $dokumen;
            });
        } catch (\Throwable $e) {
            Storage::disk(PengajuanService::DISK)->delete($path);
            throw $e;
        }

        if ($lama && $lama->file_path !== $path) {
            Storage::disk(PengajuanService::DISK)->delete($lama->file_path);
        }

        return $dokumen;
    }

    public function hapusDokumen(SidangTaDokumen $dokumen, User $actor): void
    {
        $sidang = $dokumen->sidang;
        $this->pastikanBolehDiubah($sidang);

        $path = $dokumen->file_path;
        $nama = $dokumen->persyaratan?->nama ?? 'dokumen';
        $dokumen->delete();
        Storage::disk(PengajuanService::DISK)->delete($path);
        LogAktivitas::catat($actor, 'sidang_dokumen_dihapus', "Dokumen sidang \"{$nama}\" dihapus.", $sidang);
    }

    /** Mengirim (atau mengirim ulang setelah perbaikan) pengajuan sidang. */
    public function kirim(SidangTa $sidang, User $actor): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor) {
            $this->pastikanBolehDiubah($s);
            $this->pastikanSkTerbit($s->pengajuan);

            $kurang = $this->dokumenKurang($s);
            if ($kurang->isNotEmpty()) {
                throw new WorkflowException('Dokumen sidang belum lengkap: '.$kurang->pluck('nama')->implode(', ').'.');
            }

            $ulang = $s->diajukan_pada !== null;
            $s->forceFill([
                'status' => StatusSidangTa::Diajukan,
                'diajukan_pada' => now(),
                'setuju_dospem1_pada' => null,
                'setuju_dospem2_pada' => null,
            ])->save();

            LogAktivitas::catat($actor, $ulang ? 'sidang_diajukan_ulang' : 'sidang_diajukan', 'Pengajuan sidang dikirim untuk diverifikasi.', $s);

            $this->beri(
                HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $s->pengajuan->prodi_id),
                'Pengajuan sidang menunggu verifikasi',
                "{$s->pengajuan->mahasiswa->nama} ({$s->pengajuan->mahasiswa->nim}) mengajukan sidang TA.",
                $s,
                $actor,
            );
        });
    }

    /**
     * Membatalkan pengajuan sidang yang belum selesai (oleh mahasiswa atau pemeriksa kelengkapan).
     * Alasan wajib dan tercatat. Setelah dibatalkan, mahasiswa dapat mengajukan ulang.
     */
    public function batalkan(SidangTa $sidang, User $actor, string $alasan): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor, $alasan) {
            if (in_array($s->status, [StatusSidangTa::Draft, StatusSidangTa::Selesai, StatusSidangTa::Dibatalkan, StatusSidangTa::Digantikan], true)) {
                throw new WorkflowException("Pengajuan sidang berstatus \"{$s->status->label()}\" tidak dapat dibatalkan.");
            }
            if (blank($alasan)) {
                throw new WorkflowException('Alasan pembatalan wajib diisi.');
            }

            $s->forceFill([
                'status' => StatusSidangTa::Dibatalkan,
                'dibatalkan_pada' => now(),
                'alasan_batal' => trim($alasan),
                // Jadwal dilepas supaya slot waktu dan dosen bebas untuk sidang lain.
                'jadwal_mulai' => null,
                'dijadwalkan_pada' => null,
            ])->save();
            $s->penguji()->delete();

            LogAktivitas::catat($actor, 'sidang_dibatalkan', trim($alasan), $s);

            $mhs = $s->pengajuan->mahasiswa;
            $penerima = $actor->id === $mhs->user_id
                ? HakAkses::pelaku(TugasAdministrasi::PeriksaKelengkapan, $s->pengajuan->prodi_id)
                : [$mhs->user];
            $this->beri(collect($penerima)->merge($s->pengajuan->pembimbings()->map->user)->all(), 'Pengajuan sidang dibatalkan', trim($alasan), $s, $actor);
        });
    }

    /**
     * Mengajukan ulang sidang yang dibatalkan: draft baru berisi salinan dokumen unggahan lama,
     * sedangkan yang lama menjadi Digantikan. Dokumen tautan otomatis dihitung ulang oleh sistem.
     */
    public function ajukanUlang(SidangTa $lama, User $actor): SidangTa
    {
        $salinan = [];

        try {
            return DB::transaction(function () use ($lama, $actor, &$salinan) {
                $s = SidangTa::query()->whereKey($lama->id)->lockForUpdate()->with(['pengajuan', 'dokumens'])->firstOrFail();

                if ($s->status !== StatusSidangTa::Dibatalkan || $s->digantikan_oleh_id !== null) {
                    throw new WorkflowException('Hanya pengajuan sidang yang dibatalkan dan belum digantikan yang dapat diajukan ulang.');
                }

                $baru = $this->buat($s->pengajuan, $actor);

                foreach ($s->dokumens as $dok) {
                    if (! Storage::disk(PengajuanService::DISK)->exists($dok->file_path)) {
                        continue;
                    }
                    $tujuan = "sidang/{$baru->id}/".basename($dok->file_path);
                    Storage::disk(PengajuanService::DISK)->copy($dok->file_path, $tujuan);
                    $salinan[] = $tujuan;

                    SidangTaDokumen::create([
                        'sidang_ta_id' => $baru->id,
                        'persyaratan_id' => $dok->persyaratan_id,
                        'file_path' => $tujuan,
                        'nama_file_asli' => $dok->nama_file_asli,
                        'uploaded_by' => $dok->uploaded_by,
                    ]);
                }

                $s->forceFill(['status' => StatusSidangTa::Digantikan, 'digantikan_oleh_id' => $baru->id])->save();
                LogAktivitas::catat($actor, 'sidang_digantikan', 'Pengajuan sidang diganti dengan pengajuan ulang yang baru.', $s);
                LogAktivitas::catat($actor, 'sidang_ulang_dibuat', "Draft ini adalah pengajuan ulang dari pengajuan sidang #{$s->id}.", $baru);

                return $baru;
            });
        } catch (\Throwable $e) {
            foreach ($salinan as $berkas) {
                Storage::disk(PengajuanService::DISK)->delete($berkas);
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Pemeriksa kelengkapan (bawaan: Operator Akademik)
    // ------------------------------------------------------------------

    public function verifikasi(SidangTa $sidang, User $actor): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor) {
            if ($s->status !== StatusSidangTa::Diajukan) {
                throw new WorkflowException("Verifikasi tidak dapat dilakukan pada status \"{$s->status->label()}\".");
            }
            $this->pastikanSkTerbit($s->pengajuan);

            $kurang = $this->dokumenKurang($s);
            if ($kurang->isNotEmpty()) {
                throw new WorkflowException('Dokumen sidang belum lengkap: '.$kurang->pluck('nama')->implode(', ').'.');
            }

            $s->forceFill([
                'status' => StatusSidangTa::Terverifikasi,
                'diverifikasi_oleh' => $actor->id,
                'diverifikasi_pada' => now(),
            ])->save();

            LogAktivitas::catat($actor, 'sidang_diverifikasi', 'Kelengkapan dokumen sidang diverifikasi.', $s);

            $mhs = $s->pengajuan->mahasiswa;
            $this->beri($s->pengajuan->pembimbings()->map->user->all(), 'Pengajuan sidang menunggu persetujuan Anda', "{$mhs->nama} ({$mhs->nim}) mengajukan sidang TA.", $s, $actor);
            $this->beri([$mhs->user], 'Dokumen sidang diverifikasi', 'Pengajuan sidang Anda menunggu persetujuan pembimbing.', $s, $actor);
        });
    }

    public function kembalikan(SidangTa $sidang, User $actor, string $catatan): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor, $catatan) {
            if (! in_array($s->status, [StatusSidangTa::Diajukan, StatusSidangTa::Terverifikasi], true)) {
                throw new WorkflowException("Pengajuan sidang tidak dapat dikembalikan pada status \"{$s->status->label()}\".");
            }
            $this->pastikanCatatan($catatan);

            $this->kembalikanKePerbaikan($s);
            LogAktivitas::catat($actor, 'sidang_dikembalikan', trim($catatan), $s);
            $this->beri([$s->pengajuan->mahasiswa->user], 'Pengajuan sidang perlu diperbaiki', trim($catatan), $s, $actor);
        });
    }

    // ------------------------------------------------------------------
    // Dosen Pembimbing 1 dan 2
    // ------------------------------------------------------------------

    /**
     * Persetujuan akademik per pembimbing. Sebelum menyetujui, jumlah bimbingan TA mahasiswa yang
     * sudah divalidasi harus memenuhi minimum pengaturan `bimbingan.minimal_ta` [K-05]; bila
     * minimum belum diatur, tidak ada batas. Disetujui setelah semua pembimbing yang ditetapkan setuju [K-07].
     */
    public function setujui(SidangTa $sidang, User $actor): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor) {
            if ($s->status !== StatusSidangTa::Terverifikasi) {
                throw new WorkflowException("Persetujuan tidak dapat diberikan pada status \"{$s->status->label()}\".");
            }

            $p = $s->pengajuan;
            $posisi = $p->posisiPembimbing($actor->dosen?->id);
            if ($posisi === null) {
                throw new WorkflowException('Anda bukan pembimbing pada TA ini.');
            }
            $kolom = $posisi === 1 ? 'setuju_dospem1_pada' : 'setuju_dospem2_pada';
            if ($s->{$kolom} !== null) {
                throw new WorkflowException('Anda sudah menyetujui pengajuan sidang ini.');
            }

            $this->pastikanMinimalBimbingan($s);

            $s->{$kolom} = now();
            $semua = $s->setuju_dospem1_pada !== null && ($p->dospem2_id === null || $s->setuju_dospem2_pada !== null);
            if ($semua) {
                $s->status = StatusSidangTa::Disetujui;
            }
            $s->save();

            LogAktivitas::catat($actor, 'sidang_disetujui_dospem', $semua
                ? "Pembimbing {$posisi} menyetujui; seluruh pembimbing telah menyetujui sidang."
                : "Pembimbing {$posisi} menyetujui; menunggu pembimbing lainnya.", $s);

            if ($semua) {
                $mhs = $p->mahasiswa;
                $this->beri([$mhs->user], 'Sidang disetujui pembimbing', 'Pengajuan sidang Anda menunggu penjadwalan.', $s, $actor);
                $this->beri(HakAkses::pelaku(TugasAdministrasi::JadwalkanSidang, $p->prodi_id), 'Sidang siap dijadwalkan', "{$mhs->nama} ({$mhs->nim}) telah disetujui seluruh pembimbing.", $s, $actor);
            }
        });
    }

    public function revisi(SidangTa $sidang, User $actor, string $catatan): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor, $catatan) {
            if ($s->status !== StatusSidangTa::Terverifikasi) {
                throw new WorkflowException("Revisi tidak dapat diminta pada status \"{$s->status->label()}\".");
            }
            if ($s->pengajuan->posisiPembimbing($actor->dosen?->id) === null) {
                throw new WorkflowException('Anda bukan pembimbing pada TA ini.');
            }
            $this->pastikanCatatan($catatan);

            $this->kembalikanKePerbaikan($s);
            LogAktivitas::catat($actor, 'sidang_revisi_dospem', trim($catatan), $s);
            $this->beri([$s->pengajuan->mahasiswa->user], 'Pengajuan sidang perlu direvisi', trim($catatan), $s, $actor);
        });
    }

    // ------------------------------------------------------------------
    // Penjadwalan dan penyelesaian (bawaan: Admin)
    // ------------------------------------------------------------------

    /**
     * Menjadwalkan sidang (atau menjadwalkan ulang yang sudah dijadwalkan).
     *
     * @param  array<int, int|string>  $pengujiDosenIds  id dosen penguji, urutan = urutan penguji
     */
    public function jadwalkan(SidangTa $sidang, User $actor, CarbonInterface|string $mulai, ?string $ruang, array $pengujiDosenIds, ?int $durasiMenit = null): SidangTa
    {
        try {
            $mulai = $mulai instanceof CarbonInterface ? Carbon::instance($mulai) : Carbon::parse($mulai);
        } catch (\Throwable) {
            throw new WorkflowException('Tanggal dan jam sidang tidak valid.');
        }
        $durasi = $durasiMenit ?? Pengaturan::durasiSidangMenit();
        if ($durasi < 15 || $durasi > 480) {
            throw new WorkflowException('Durasi sidang harus antara 15 dan 480 menit.');
        }
        $ruang = filled($ruang) ? trim($ruang) : null;

        $ids = array_values(array_unique(array_map('intval', $pengujiDosenIds)));
        if (count($ids) !== count($pengujiDosenIds)) {
            throw new WorkflowException('Satu dosen tidak boleh dipilih sebagai penguji lebih dari sekali.');
        }
        $minimal = Pengaturan::minimalPenguji();
        if (count($ids) < $minimal) {
            throw new WorkflowException("Penguji minimal {$minimal} orang.");
        }

        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor, $mulai, $durasi, $ruang, $ids) {
            if (! in_array($s->status, [StatusSidangTa::Disetujui, StatusSidangTa::Dijadwalkan], true)) {
                throw new WorkflowException("Sidang berstatus \"{$s->status->label()}\" tidak dapat dijadwalkan.");
            }
            if ($mulai->lte(now())) {
                throw new WorkflowException('Jadwal sidang harus di masa depan.');
            }

            $p = $s->pengajuan;
            $penguji = Dosen::query()->whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $id) {
                $dosen = $penguji->get($id);
                if (! $dosen || ! $dosen->aktif) {
                    throw new WorkflowException('Penguji harus dipilih dari dosen aktif pada master dosen.');
                }
                if ($p->posisiPembimbing($dosen) !== null) {
                    throw new WorkflowException("{$dosen->nama} adalah pembimbing TA ini dan tidak dapat menjadi penguji.");
                }
            }

            $this->pastikanTidakBentrok($s, $mulai, $durasi, $ruang, $ids);

            $baru = $s->status === StatusSidangTa::Disetujui;
            $s->forceFill([
                'status' => StatusSidangTa::Dijadwalkan,
                'jadwal_mulai' => $mulai,
                'durasi_menit' => $durasi,
                'ruang' => $ruang,
                'dijadwalkan_oleh' => $actor->id,
                'dijadwalkan_pada' => now(),
            ])->save();

            $s->penguji()->delete();
            foreach ($ids as $urutan => $id) {
                SidangTaPenguji::create(['sidang_ta_id' => $s->id, 'dosen_id' => $id, 'urutan' => $urutan + 1]);
            }

            $ket = $mulai->format('d/m/Y H:i').' ('.$durasi.' menit)'.($ruang ? ", ruang {$ruang}" : '').'. Penguji: '.$penguji->pluck('nama')->implode(', ').'.';
            LogAktivitas::catat($actor, $baru ? 'sidang_dijadwalkan' : 'sidang_dijadwalkan_ulang', "Sidang dijadwalkan: {$ket}", $s);

            $penerima = collect([$p->mahasiswa->user])
                ->merge($p->pembimbings()->map->user)
                ->merge($penguji->map->user)
                ->all();
            $this->beri($penerima, $baru ? 'Sidang dijadwalkan' : 'Jadwal sidang diubah', $ket, $s, $actor);
        });
    }

    /** Konfirmasi Sidang Selesai (oleh pelaku tugas KonfirmasiSidangSelesai, bawaan Admin) [K-09]. */
    public function selesai(SidangTa $sidang, User $actor): SidangTa
    {
        return $this->dalamTransaksi($sidang, function (SidangTa $s) use ($actor) {
            if ($s->status !== StatusSidangTa::Dijadwalkan) {
                throw new WorkflowException('Hanya sidang yang sudah dijadwalkan yang dapat dikonfirmasi selesai.');
            }

            $s->forceFill(['status' => StatusSidangTa::Selesai, 'selesai_oleh' => $actor->id, 'selesai_pada' => now()])->save();

            LogAktivitas::catat($actor, 'sidang_selesai', 'Sidang dikonfirmasi selesai.', $s);
            $this->beri(
                collect([$s->pengajuan->mahasiswa->user])->merge($s->pengajuan->pembimbings()->map->user)->all(),
                'Sidang selesai',
                'Sidang TA telah dikonfirmasi selesai.',
                $s,
                $actor,
            );
        });
    }

    // ------------------------------------------------------------------
    // Pembacaan keadaan (dipakai view dan panel Admin)
    // ------------------------------------------------------------------

    /** @return Collection<int, Persyaratan> seluruh persyaratan sidang yang berlaku untuk prodi pengajuan */
    public function persyaratan(SidangTa $sidang): Collection
    {
        $sidang->loadMissing('pengajuan');

        return Persyaratan::berlakuUntuk(JenisPersyaratan::TaSidang, $sidang->pengajuan->prodi_id)->get();
    }

    /** Apakah dokumen yang ditautkan otomatis tersedia saat ini. */
    public function tautanTersedia(SidangTa $sidang, Persyaratan $persyaratan): bool
    {
        $sidang->loadMissing('pengajuan');

        return match ($persyaratan->tautan_otomatis) {
            Persyaratan::TAUTAN_SK_TA => $sidang->pengajuan->suratKeputusan()->exists(),
            Persyaratan::TAUTAN_KARTU_KONSULTASI => ! $this->kartu->terkunci($sidang->pengajuan),
            default => false,
        };
    }

    /** Persyaratan wajib yang belum terpenuhi (belum diunggah, atau tautan otomatis belum tersedia). */
    public function dokumenKurang(SidangTa $sidang): Collection
    {
        $terisi = $sidang->dokumens()->pluck('persyaratan_id')->all();

        return $this->persyaratan($sidang)
            ->filter(fn (Persyaratan $r) => $r->wajib && ($r->tautan_otomatis !== null
                ? ! $this->tautanTersedia($sidang, $r)
                : ! in_array($r->id, $terisi, true)))
            ->values();
    }

    /** @return array{tercatat: int, divalidasi: int, minimal: ?int} bimbingan TA mahasiswa pada sidang ini */
    public function ringkasanBimbingan(SidangTa $sidang): array
    {
        $q = Bimbingan::query()->where('mahasiswa_id', $sidang->mahasiswa_id)->where('jenis', JenisPengajuan::Ta->value);

        return [
            'tercatat' => (clone $q)->count(),
            'divalidasi' => (clone $q)->where('status_validasi', StatusBimbingan::Divalidasi->value)->count(),
            'minimal' => Pengaturan::minimalBimbingan(JenisPengajuan::Ta->value),
        ];
    }

    // ------------------------------------------------------------------

    private function pastikanSkTerbit(Pengajuan $p): void
    {
        if ($p->jenis !== JenisPengajuan::Ta || ! $p->suratKeputusan()->exists()) {
            throw new WorkflowException('Pengajuan sidang hanya dapat dibuat setelah SK TA terbit.');
        }
    }

    private function pastikanBolehDiubah(SidangTa $sidang): void
    {
        if (! $sidang->bolehDiubahMahasiswa()) {
            throw new WorkflowException("Pengajuan sidang tidak dapat diubah karena berstatus \"{$sidang->status->label()}\".");
        }
    }

    private function pastikanCatatan(string $catatan): void
    {
        if (blank($catatan)) {
            throw new WorkflowException('Catatan perbaikan wajib diisi.');
        }
    }

    private function pastikanMinimalBimbingan(SidangTa $sidang): void
    {
        $r = $this->ringkasanBimbingan($sidang);
        if ($r['minimal'] !== null && $r['divalidasi'] < $r['minimal']) {
            throw new WorkflowException("Bimbingan TA yang sudah divalidasi baru {$r['divalidasi']} dari minimal {$r['minimal']}. Persetujuan sidang belum dapat diberikan.");
        }
    }

    private function kembalikanKePerbaikan(SidangTa $s): void
    {
        $s->forceFill([
            'status' => StatusSidangTa::PerluRevisi,
            'setuju_dospem1_pada' => null,
            'setuju_dospem2_pada' => null,
        ])->save();
    }

    /**
     * Tidak boleh ada dosen (penguji maupun pembimbing) atau ruang yang sama pada sidang lain
     * yang waktunya beririsan. Pemeriksaan di PHP supaya tidak bergantung pada fungsi tanggal DB.
     *
     * @param  array<int, int>  $pengujiIds
     */
    private function pastikanTidakBentrok(SidangTa $sidang, Carbon $mulai, int $durasi, ?string $ruang, array $pengujiIds): void
    {
        $akhir = $mulai->copy()->addMinutes($durasi);
        $dosenIni = array_values(array_unique(array_merge($pengujiIds, $sidang->pengajuan->pembimbings()->pluck('id')->all())));

        $lain = SidangTa::query()
            ->with(['pengajuan.mahasiswa', 'penguji'])
            ->where('status', StatusSidangTa::Dijadwalkan->value)
            ->whereKeyNot($sidang->id)
            ->whereBetween('jadwal_mulai', [$mulai->copy()->subDay(), $mulai->copy()->addDay()])
            ->lockForUpdate()
            ->get();

        foreach ($lain as $o) {
            if (! ($o->jadwal_mulai->lt($akhir) && $o->jadwalSelesai()->gt($mulai))) {
                continue;
            }

            $dosenLain = $o->penguji->pluck('dosen_id')->merge($o->pengajuan->pembimbings()->pluck('id'))->unique()->all();
            $sama = array_values(array_intersect($dosenIni, $dosenLain));
            $mhs = $o->pengajuan->mahasiswa;
            $jam = $o->jadwal_mulai->format('d/m/Y H:i').'–'.$o->jadwalSelesai()->format('H:i');

            if ($sama !== []) {
                $nama = Dosen::query()->whereIn('id', $sama)->pluck('nama')->implode(', ');
                throw new WorkflowException("Jadwal bentrok: {$nama} sudah terjadwal pada sidang {$mhs->nama} ({$jam}).");
            }
            if ($ruang !== null && $o->ruang !== null && mb_strtolower(trim($o->ruang)) === mb_strtolower($ruang)) {
                throw new WorkflowException("Jadwal bentrok: ruang {$ruang} sudah dipakai sidang {$mhs->nama} ({$jam}).");
            }
        }
    }

    private function dalamTransaksi(SidangTa $sidang, callable $aksi): SidangTa
    {
        return DB::transaction(function () use ($sidang, $aksi) {
            $segar = SidangTa::query()->whereKey($sidang->id)->lockForUpdate()->with(['pengajuan.mahasiswa.user', 'pengajuan.dospem.user', 'pengajuan.dospem2.user'])->firstOrFail();
            $aksi($segar);

            return $segar->fresh();
        });
    }

    /** @param  iterable<User|null>  $penerima */
    private function beri(iterable $penerima, string $judul, ?string $isi, SidangTa $s, ?User $actor): void
    {
        $this->notifikasi->kirim($penerima, $judul, $isi, route('sidang.show', $s, false), $s, $actor);
    }
}
