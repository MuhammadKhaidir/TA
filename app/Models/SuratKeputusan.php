<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratKeputusan extends Model
{
    protected $table = 'surat_keputusans';

    protected $fillable = [
        'pengajuan_id', 'template_sk_id', 'nomor', 'tanggal', 'file_path', 'isi_html', 'diterbitkan_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function penerbit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterbitkan_oleh');
    }
}
