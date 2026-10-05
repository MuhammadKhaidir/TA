<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Konsultasi extends Model
{
    protected $fillable = ['mahasiswa_id', 'tanggal', 'catatan', 'bukti_path'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
