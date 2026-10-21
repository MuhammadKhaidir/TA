<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Notifikasi dalam sistem untuk satu penerima. Dibuat lewat NotifikasiService. */
class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    protected $fillable = ['user_id', 'judul', 'isi', 'url', 'subjek_type', 'subjek_id'];

    protected function casts(): array
    {
        return ['dibaca_pada' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo('subjek');
    }

    public function scopeBelumDibaca($query)
    {
        return $query->whereNull('dibaca_pada');
    }

    public function sudahDibaca(): bool
    {
        return $this->dibaca_pada !== null;
    }
}
