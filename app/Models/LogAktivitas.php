<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Jejak audit: setiap perubahan penting mencatat siapa, kapan, dan apa.
 * Riwayat/catatan revisi pada satu pengajuan = baris dengan subjek pengajuan itu.
 */
class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    public $timestamps = false;

    protected $fillable = ['user_id', 'peran', 'aksi', 'keterangan', 'subjek_type', 'subjek_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->created_at ??= now());
    }

    public static function catat(?User $user, string $aksi, ?string $keterangan = null, ?Model $subjek = null): self
    {
        return static::create([
            'user_id' => $user?->id,
            'peran' => $user?->peranUtama()?->value,
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo('subjek');
    }
}
