<?php

namespace App\Models;

use App\Enums\Peran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dosen extends Model
{
    protected $fillable = [
        'user_id',
        'nip',
        'nama',
        'prodi_id',
        'aktif',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Pengajuan yang dibimbingnya sebagai Pembimbing 1. */
    public function pengajuans(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'dospem_id');
    }

    /** Pengajuan yang dibimbingnya sebagai Pembimbing 2. */
    public function pengajuansKedua(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'dospem2_id');
    }

    /**
     * Dosen yang sah ditetapkan sebagai pembimbing: aktif dan punya akun
     * aktif dengan role dospem (supaya dapat benar-benar memeriksa pengajuan).
     */
    public function scopeLayakMembimbing(Builder $query): Builder
    {
        return $query->where('aktif', true)->whereHas('user', function (Builder $q) {
            $q->where('aktif', true)->whereHas('roles', fn (Builder $r) => $r->where('name', Peran::Dospem->value));
        });
    }

    public function layakMembimbing(): bool
    {
        return static::query()->layakMembimbing()->whereKey($this->getKey())->exists();
    }
}
