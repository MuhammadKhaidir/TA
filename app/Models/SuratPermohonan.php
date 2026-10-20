<?php

namespace App\Models;

use App\Enums\StatusSurat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratPermohonan extends Model
{
    protected $table = 'surat_permohonans';

    protected $fillable = [
        'pengajuan_id', 'jenis_surat_id', 'status', 'catatan_mahasiswa', 'catatan_operator',
        'nomor_surat', 'file_path', 'nama_file_asli', 'diproses_oleh', 'diproses_pada',
    ];

    protected function casts(): array
    {
        return ['status' => StatusSurat::class, 'diproses_pada' => 'datetime'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class);
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
