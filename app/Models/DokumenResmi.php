<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Models\Concerns\PunyaBerkas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenResmi extends Model
{
    use PunyaBerkas;

    protected $table = 'dokumen_resmi';

    protected $fillable = [
        'pengajuan_id', 'diunggah_oleh', 'jenis', 'nomor', 'tanggal',
        'nama_asli', 'path', 'mime', 'ukuran',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'ukuran' => 'integer',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function jenisLabel(): string
    {
        return JenisPengajuan::labelDokumenResmi($this->jenis);
    }
}
