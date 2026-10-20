<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\Peran;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
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
        ];
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

    public function dospem(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dospem_id');
    }

    public function dokumens(): HasMany
    {
        return $this->hasMany(PengajuanDokumen::class);
    }

    public function suratKeputusan(): HasOne
    {
        return $this->hasOne(SuratKeputusan::class);
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
            $query->where('status', '!=', StatusPengajuan::Draft->value);
            $cakupan = $user->cakupanProdi();

            return $cakupan === null ? $query : $query->whereIn('prodi_id', $cakupan);
        }

        if ($user->hasRole(Peran::Dospem->value)) {
            return $query->where('status', '!=', StatusPengajuan::Draft->value)
                ->where('dospem_id', $user->dosen?->id ?? 0);
        }

        if ($user->hasRole(Peran::Mahasiswa->value)) {
            return $query->where('mahasiswa_id', $user->mahasiswa?->id ?? 0);
        }

        // Admin mengelola sistem, bukan memproses pengajuan; peran lain tidak punya akses.
        return $query->whereRaw('1 = 0');
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

    public function siapTerbitSk(): bool
    {
        return $this->jenis === JenisPengajuan::Ta
            && $this->status === StatusPengajuan::Disetujui
            && $this->status_administrasi === StatusAdministrasi::Terverifikasi
            && $this->status_akademik === StatusAkademik::Disetujui
            && $this->dospem_id !== null
            && ! $this->suratKeputusan()->exists();
    }

    public function judulTampil(): string
    {
        return $this->judul ?: ($this->instansi_nama ?: '-');
    }
}
