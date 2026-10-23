<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arsip extends Model
{
    protected $table = 'arsip';

    protected $fillable = ['pengajuan_id', 'diarsipkan_oleh', 'diarsipkan_at', 'catatan'];

    protected function casts(): array
    {
        return ['diarsipkan_at' => 'datetime'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diarsipkan_oleh');
    }
}
