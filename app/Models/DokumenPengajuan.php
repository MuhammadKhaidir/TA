<?php

namespace App\Models;

use App\Enums\StatusDokumen;
use App\Models\Concerns\PunyaBerkas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DokumenPengajuan extends Model
{
    use PunyaBerkas;

    protected $table = 'dokumen_pengajuan';

    protected $fillable = [
        'pengajuan_id', 'persyaratan_id', 'diunggah_oleh', 'versi', 'terbaru',
        'nama_asli', 'path', 'mime', 'ukuran', 'status',
    ];

    protected function casts(): array
    {
        return [
            'terbaru' => 'boolean',
            'versi' => 'integer',
            'ukuran' => 'integer',
            'status' => StatusDokumen::class,
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(Persyaratan::class, 'persyaratan_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function pemeriksaan(): HasMany
    {
        return $this->hasMany(Pemeriksaan::class, 'dokumen_id');
    }
}
