<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\JenisPersyaratan;
use App\Enums\Peran;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSuratBalasan;
use App\Enums\StatusSuratPengantar;
use App\Enums\TugasAdministrasi;
use App\Support\HakAkses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Pengajuan Tugas Akhir atau Kerja Praktik seorang mahasiswa.
 *
 * Kolom status, status_administrasi, status_akademik, dospem_id, mahasiswa_id,
 * prodi_id dan periode TIDAK fillable: hanya diubah oleh PengajuanService.
 */
class Pengajuan extends Model
{
    protected $fillable = [
        'judul',
        'instansi_nama', 'instansi_alamat', 'bidang_kegiatan', 'tanggal_mulai', 'tanggal_selesai',
        'pembimbing_lapangan_nama', 'pembimbing_lapangan_kontak',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPengajuan::class,
            'status' => StatusPengajuan::class,
            'status_administrasi' => StatusAdministrasi::class,
            'status_akademik' => StatusAkademik::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'diajukan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
            'setuju_dospem1_pada' => 'datetime',
            'setuju_dospem2_pada' => 'datetime',
            'wajib_surat_balasan' => 'boolean',
        ];
    }

    /**
     * Gerbang SK berlaku untuk pengajuan TA baru. Pengajuan yang sudah berjalan sebelum revisi
     * (kolom bernilai false) dikecualikan; baris yang baru dibuat dan belum dimuat ulang dianggap berlaku.
     */
    public function gerbangSuratBalasan(): bool
    {
        return $this->getAttribute('wajib_surat_balasan') ?? true;
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    /** Pembimbing 1. */
    public function dospem(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dospem_id');
    }

    public function dospem2(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dospem2_id');
    }

    public function digantikanOleh(): BelongsTo
    {
        return $this->belongsTo(self::class, 'digantikan_oleh_id');
    }

    public function dokumens(): HasMany
    {
        return $this->hasMany(PengajuanDokumen::class);
    }

    public function suratKeputusan(): HasOne
    {
        return $this->hasOne(SuratKeputusan::class);
    }

    /** Surat pengantar terbaru (satu pengajuan hanya punya satu yang aktif; lainnya Dibatalkan). */
    public function suratPengantar(): HasOne
    {
        return $this->hasOne(SuratPengantar::class)->latestOfMany();
    }

    public function suratPengantars(): HasMany
    {
        return $this->hasMany(SuratPengantar::class);
    }

    public function suratBalasan(): HasOne
    {
        return $this->hasOne(SuratBalasan::class);
    }

    public function sidangs(): HasMany
    {
        return $this->hasMany(SidangTa::class);
    }

    public function suratPermohonans(): HasMany
    {
        return $this->hasMany(SuratPermohonan::class);
    }

    public function riwayats(): MorphMany
    {
        return $this->morphMany(LogAktivitas::class, 'subjek')->orderBy('created_at')->orderBy('id');
    }

    /**
     * Batasi pengajuan sesuai kewenangan pengguna. Satu-satunya sumber aturan
     * "siapa melihat apa", dipakai daftar, dashboard, dan policy.
     */
    public function scopeDapatDilihatOleh(Builder $query, User $user): Builder
    {
        if ($user->hasRole(Peran::OperatorAkademik->value)) {
            $this->batasiTerkirimAtauBerpengantar($query);
            $cakupan = $user->cakupanProdi();

            return $cakupan === null ? $query : $query->whereIn('prodi_id', $cakupan);
        }

        if ($user->hasRole(Peran::Dospem->value)) {
            $dosenId = $user->dosen?->id ?? 0;

            return $query->where('status', '!=', StatusPengajuan::Draft->value)
                ->where(fn (Builder $q) => $q->where('dospem_id', $dosenId)->orWhere('dospem2_id', $dosenId));
        }

        if ($user->hasRole(Peran::Mahasiswa->value)) {
            return $query->where('mahasiswa_id', $user->mahasiswa?->id ?? 0);
        }

        // Admin mengelola sistem, bukan memproses pengajuan. Pengecualian: bila fakultas mengalihkan tugas
        // pemeriksaan kelengkapan/validasi surat balasan kepada Admin lewat Pengaturan [K-01], ia melihat
        // pengajuan untuk mengerjakan tugas itu. Dengan pengaturan bawaan, Admin tidak melihat apa pun.
        if ($user->hasRole(Peran::Admin->value)
            && (HakAkses::boleh($user, TugasAdministrasi::PeriksaKelengkapan) || HakAkses::boleh($user, TugasAdministrasi::ValidasiSuratBalasan))) {
            return $this->batasiTerkirimAtauBerpengantar($query);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Pemeriksa melihat pengajuan yang sudah dikirim, dan juga pengajuan TA yang masih draft tetapi
     * surat pengantarnya sudah dikirim (tahap surat pengantar berjalan sebelum pengajuan SK dikirim).
     */
    private function batasiTerkirimAtauBerpengantar(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->orWhereHas('suratPengantars', fn (Builder $s) => $s->where('status', '!=', StatusSuratPengantar::Draft->value)));
    }

    public function bolehDiubahMahasiswa(): bool
    {
        return in_array($this->status, [StatusPengajuan::Draft, StatusPengajuan::PerluRevisi], true);
    }

    /** @return Collection<int, Persyaratan> */
    public function persyaratanPengajuan(): Collection
    {
        return Persyaratan::berlakuUntuk($this->jenis->jenisPersyaratan(), $this->prodi_id)->get();
    }

    /** Persyaratan wajib yang dokumennya belum diunggah. */
    public function persyaratanWajibKurang(): Collection
    {
        $terisi = $this->dokumens()->pluck('persyaratan_id')->all();

        return $this->persyaratanPengajuan()
            ->filter(fn (Persyaratan $p) => $p->wajib && ! in_array($p->id, $terisi, true))
            ->values();
    }

    public function dokumenPersyaratan(int $persyaratanId): ?PengajuanDokumen
    {
        return $this->dokumens->firstWhere('persyaratan_id', $persyaratanId);
    }

    /** Dokumen Kumpulan A pada surat pengantar TA (persyaratan berjenis ta_surat_pengantar). */
    public function persyaratanPengantar(): Collection
    {
        return Persyaratan::berlakuUntuk(JenisPersyaratan::TaSuratPengantar, $this->prodi_id)->get();
    }

    public function persyaratanPengantarKurang(): Collection
    {
        $terisi = $this->dokumens()->pluck('persyaratan_id')->all();

        return $this->persyaratanPengantar()
            ->filter(fn (Persyaratan $p) => $p->wajib && ! in_array($p->id, $terisi, true))
            ->values();
    }

    /** Gerbang SK: surat balasan sudah dinyatakan Valid oleh Operator. */
    public function suratBalasanValid(): bool
    {
        return $this->suratBalasan()->where('status', StatusSuratBalasan::Valid->value)->exists();
    }

    /** Posisi pembimbing (1 atau 2) untuk dosen tertentu pada pengajuan ini; null bila bukan pembimbingnya. */
    public function posisiPembimbing(Dosen|int|null $dosen): ?int
    {
        $id = $dosen instanceof Dosen ? $dosen->id : $dosen;
        if ($id === null) {
            return null;
        }

        return match (true) {
            $id === $this->dospem_id => 1,
            $id === $this->dospem2_id => 2,
            default => null,
        };
    }

    /** Pembimbing yang ditetapkan, berurutan (pembimbing 1 lalu 2). @return Collection<int, Dosen> */
    public function pembimbings(): Collection
    {
        return collect([$this->dospem, $this->dospem2])->filter()->values();
    }

    /** Semua pembimbing yang ditetapkan sudah menyetujui. */
    public function semuaPembimbingSetuju(): bool
    {
        return $this->dospem_id !== null
            && $this->setuju_dospem1_pada !== null
            && ($this->dospem2_id === null || $this->setuju_dospem2_pada !== null);
    }

    /** Dosen ini masih ditunggu persetujuannya pada pengajuan yang sedang diperiksa. */
    public function menungguPersetujuan(Dosen $dosen): bool
    {
        $posisi = $this->posisiPembimbing($dosen);

        return $this->status === StatusPengajuan::Diajukan
            && $this->status_akademik === StatusAkademik::Menunggu
            && $posisi !== null
            && ($posisi === 1 ? $this->setuju_dospem1_pada : $this->setuju_dospem2_pada) === null;
    }

    public function siapTerbitSk(): bool
    {
        return $this->jenis === JenisPengajuan::Ta
            && $this->status === StatusPengajuan::Disetujui
            && $this->status_administrasi === StatusAdministrasi::Terverifikasi
            && $this->status_akademik === StatusAkademik::Disetujui
            && $this->dospem_id !== null
            && (! $this->gerbangSuratBalasan() || $this->suratBalasanValid())
            && ! $this->suratKeputusan()->exists();
    }

    public function judulTampil(): string
    {
        return $this->judul ?: ($this->instansi_nama ?: '-');
    }
}
