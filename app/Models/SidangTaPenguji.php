<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangTaPenguji extends Model
{
    protected $table = 'sidang_ta_penguji';

    protected $fillable = ['sidang_ta_id', 'dosen_id', 'urutan'];

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(SidangTa::class, 'sidang_ta_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
