<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanDokumen extends Model
{
    protected $table = 'pengajuan_dokumens';

    protected $fillable = ['pengajuan_id', 'persyaratan_id', 'file_path', 'nama_file_asli', 'uploaded_by'];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
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
