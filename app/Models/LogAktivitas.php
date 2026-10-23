<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    protected $fillable = ['user_id', 'aksi', 'deskripsi', 'subjek_type', 'subjek_id', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo('subjek');
    }
}
