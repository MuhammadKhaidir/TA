<?php

namespace App\Models;

use App\Enums\JenisUjian;
use App\Enums\SidangStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sidang extends Model
{
    protected $fillable = [
        'mahasiswa_id',
        'jenis',
        'status',
        'tanggal_pengajuan',
        'jumlah_konsultasi_saat_ajukan',
        'judul_ta',
        'tanggal_sidang',
        'jam_sidang',
        'media',
        'tempat',
        'nilai_usep',
        'dkn_terverifikasi',
        'nomor_sk_penguji',
        'jumlah_penjadwalan_ulang',
        'alasan_penjadwalan_ulang',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUjian::class,
            'status' => SidangStatus::class,
            'tanggal_pengajuan' => 'date',
            'tanggal_sidang' => 'date',
            'dkn_terverifikasi' => 'boolean',
            'jumlah_konsultasi_saat_ajukan' => 'integer',
            'jumlah_penjadwalan_ulang' => 'integer',
            'nilai_usep' => 'integer',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function pengujis(): BelongsToMany
    {
        return $this->belongsToMany(Dosen::class, 'sidang_pengujis')
            ->using(SidangPenguji::class)
            ->withPivot([
                'id', 'peran', 'konfirmasi', 'alasan_berhalangan', 'nilai_diinput', 'waktu_input_nilai',
            ])
            ->withTimestamps();
    }

    public function sidangPengujis(): HasMany
    {
        return $this->hasMany(SidangPenguji::class);
    }

    public function dokumens(): HasMany
    {
        return $this->hasMany(Dokumen::class);
    }

    public function riwayats(): HasMany
    {
        return $this->hasMany(SidangRiwayat::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Ambil dokumen terbaru untuk satu jenis tertentu.
     */
    public function dokumenTerbaru(string $jenisDokumen): ?Dokumen
    {
        return $this->dokumens
            ->where('jenis_dokumen', $jenisDokumen)
            ->sortByDesc('created_at')
            ->first();
    }

    public function semuaNilaiSudahDiinput(): bool
    {
        if ($this->pengujis->isEmpty()) {
            return false;
        }

        return $this->pengujis->every(fn (Dosen $dosen) => (bool) $dosen->pivot->nilai_diinput);
    }

    public function pengujiBelumInputNilai(): \Illuminate\Support\Collection
    {
        return $this->pengujis->reject(fn (Dosen $dosen) => (bool) $dosen->pivot->nilai_diinput);
    }

    /**
     * Batas waktu (SLA Mutu Baku: 1 hari) untuk tindakan pada langkah aktif
     * saat ini, dihitung dari kapan status ini mulai berlaku.
     */
    public function batasWaktuAksi(): ?Carbon
    {
        if ($this->status === SidangStatus::Selesai) {
            return null;
        }

        $acuan = $this->riwayats->last()?->created_at ?? $this->updated_at;

        return $acuan?->copy()->addDay();
    }

    public function aksiTerlambat(): bool
    {
        $batas = $this->batasWaktuAksi();

        return $batas !== null && now()->greaterThan($batas);
    }

    /**
     * Peringatan POS #2: SK Penguji harus terbit paling lambat pada minggu
     * pelaksanaan sidang.
     */
    public function risikoSkBelumTerbit(): bool
    {
        if (! $this->tanggal_sidang || $this->nomor_sk_penguji) {
            return false;
        }

        return in_array($this->status, [
            SidangStatus::Diajukan,
            SidangStatus::MenungguVerifikasiPenata,
            SidangStatus::MenungguProsesSk,
        ], true) && now()->greaterThanOrEqualTo($this->tanggal_sidang->copy()->subDays(7));
    }

    /**
     * Peringatan POS #4: mahasiswa wajib mengirim file TA H-1 sebelum sidang.
     */
    public function batasUnggahDokumenTa(): ?Carbon
    {
        return $this->tanggal_sidang?->copy()->subDay()->endOfDay();
    }

    public function terlambatUnggahDokumenTa(): bool
    {
        $batas = $this->batasUnggahDokumenTa();

        if (! $batas || $this->dokumenTerbaru('dokumen_ta')) {
            return false;
        }

        return now()->greaterThan($batas) && in_array($this->status, [
            SidangStatus::SiapUjian,
            SidangStatus::PelaksanaanUjian,
        ], true);
    }

    /**
     * Peringatan POS #5: batas maksimal durasi sidang skripsi 1 jam.
     */
    public function batasSelesaiSidang(): ?Carbon
    {
        $menit = $this->jenis?->batasDurasiMenit();

        if (! $menit || ! $this->tanggal_sidang || ! $this->jam_sidang) {
            return null;
        }

        $jam = $this->jam_sidang instanceof Carbon ? $this->jam_sidang->format('H:i:s') : $this->jam_sidang;

        return Carbon::parse($this->tanggal_sidang->format('Y-m-d').' '.$jam)->addMinutes($menit);
    }

    public function catatRiwayat(int $langkahKe, string $judul, ?string $keterangan = null, ?string $role = null, ?int $userId = null): SidangRiwayat
    {
        return $this->riwayats()->create([
            'langkah_ke' => $langkahKe,
            'judul' => $judul,
            'keterangan' => $keterangan,
            'role' => $role,
            'user_id' => $userId,
        ]);
    }
}
