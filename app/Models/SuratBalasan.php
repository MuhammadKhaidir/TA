<?php

namespace App\Models;

use App\Enums\StatusDokumen;
use App\Models\Concerns\PunyaBerkas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat balasan Tugas Akhir yang diunggah mahasiswa. Memakai StatusDokumen
 * (menunggu / diterima / revisi) seperti dokumen persyaratan.
 */
class SuratBalasan extends Model
{
    use PunyaBerkas;

    protected $table = 'surat_balasan';

    protected $fillable = [
        'pengajuan_id', 'diunggah_oleh', 'versi', 'terbaru',
        'nama_asli', 'path', 'mime', 'ukuran', 'status',
        'diperiksa_oleh', 'diperiksa_at', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'terbaru' => 'boolean',
            'versi' => 'integer',
            'ukuran' => 'integer',
            'status' => StatusDokumen::class,
            'diperiksa_at' => 'datetime',
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

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperiksa_oleh');
    }
}
