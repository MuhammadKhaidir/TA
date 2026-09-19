<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    protected $fillable = [
        'user_id',
        'nim',
        'nama',
        'prodi',
        'angkatan',
        'no_hp',
        'jumlah_konsultasi',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_konsultasi' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sidangs(): HasMany
    {
        return $this->hasMany(Sidang::class);
    }

    /**
     * Peringatan POS #1: syarat minimal 12x konsultasi sebelum sidang.
     */
    public const MINIMAL_KONSULTASI = 12;

    public function memenuhiSyaratKonsultasi(): bool
    {
        return $this->jumlah_konsultasi >= self::MINIMAL_KONSULTASI;
    }
}
