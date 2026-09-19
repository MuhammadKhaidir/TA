<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangRiwayat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sidang_id',
        'langkah_ke',
        'judul',
        'keterangan',
        'role',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'langkah_ke' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SidangRiwayat $riwayat) {
            $riwayat->created_at ??= now();
        });
    }

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(Sidang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
