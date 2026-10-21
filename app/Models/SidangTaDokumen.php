<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangTaDokumen extends Model
{
    protected $table = 'sidang_ta_dokumens';

    protected $fillable = ['sidang_ta_id', 'persyaratan_id', 'file_path', 'nama_file_asli', 'uploaded_by'];

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(SidangTa::class, 'sidang_ta_id');
    }

    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(Persyaratan::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
