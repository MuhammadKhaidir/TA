<?php

namespace App\Models;

use App\Enums\Peran;
use App\Enums\StatusSidangTa;
use App\Enums\TugasAdministrasi;
use App\Support\HakAkses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Pengajuan sidang TA. Semua kolom status/jadwal tidak fillable: hanya diubah SidangService.
 */
class SidangTa extends Model
{
    protected $table = 'sidang_tas';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => StatusSidangTa::class,
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'setuju_dospem1_pada' => 'datetime',
            'setuju_dospem2_pada' => 'datetime',
            'jadwal_mulai' => 'datetime',
            'dijadwalkan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function dokumens(): HasMany
    {
        return $this->hasMany(SidangTaDokumen::class);
    }

    public function penguji(): HasMany
    {
        return $this->hasMany(SidangTaPenguji::class)->orderBy('urutan')->orderBy('id');
    }

    public function digantikanOleh(): BelongsTo
    {
        return $this->belongsTo(self::class, 'digantikan_oleh_id');
    }

    public function riwayats(): MorphMany
    {
        return $this->morphMany(LogAktivitas::class, 'subjek')->orderBy('created_at')->orderBy('id');
    }

    /** Batasi sidang sesuai kewenangan pengguna (mirip Pengajuan::dapatDilihatOleh). */
    public function scopeDapatDilihatOleh(Builder $query, User $user): Builder
    {
        if ($user->hasRole(Peran::OperatorAkademik->value)) {
            $query->where('status', '!=', StatusSidangTa::Draft->value);
            $cakupan = $user->cakupanProdi();

            return $cakupan === null ? $query : $query->whereHas('pengajuan', fn ($p) => $p->whereIn('prodi_id', $cakupan));
        }

        if ($user->hasRole(Peran::Dospem->value)) {
            $dosenId = $user->dosen?->id ?? 0;

            return $query->where('status', '!=', StatusSidangTa::Draft->value)
                ->whereHas('pengajuan', fn ($p) => $p->where(fn ($q) => $q->where('dospem_id', $dosenId)->orWhere('dospem2_id', $dosenId)));
        }

        if ($user->hasRole(Peran::Mahasiswa->value)) {
            return $query->where('mahasiswa_id', $user->mahasiswa?->id ?? 0);
        }

        // Admin mengerjakan penjadwalan dan konfirmasi di panel /admin. Lewat halaman web ia hanya melihat sidang
        // bila pemeriksaan kelengkapan dialihkan kepadanya lewat Pengaturan [K-01].
        if ($user->hasRole(Peran::Admin->value) && HakAkses::boleh($user, TugasAdministrasi::PeriksaKelengkapan)) {
            return $query->where('status', '!=', StatusSidangTa::Draft->value);
        }

        return $query->whereRaw('1 = 0');
    }

    public function bolehDiubahMahasiswa(): bool
    {
        return $this->status->bolehDiubahMahasiswa();
    }

    public function jadwalSelesai(): ?Carbon
    {
        return $this->jadwal_mulai?->copy()->addMinutes($this->durasi_menit ?? Pengaturan::durasiSidangMenit());
    }

    public function dokumenPersyaratan(int $persyaratanId): ?SidangTaDokumen
    {
        return $this->dokumens->firstWhere('persyaratan_id', $persyaratanId);
    }
}
