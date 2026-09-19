<?php

namespace App\Models;

use App\Enums\JenisDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    protected $fillable = [
        'sidang_id',
        'jenis_dokumen',
        'nomor_sk',
        'file_path',
        'nama_file_asli',
        'keterangan',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'jenis_dokumen' => JenisDokumen::class,
        ];
    }

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(Sidang::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->file_path);
    }
}
