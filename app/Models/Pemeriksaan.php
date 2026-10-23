<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatusDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pemeriksaan extends Model
{
    protected $table = 'pemeriksaan';

    protected $fillable = ['pengajuan_id', 'dokumen_id', 'pemeriksa_id', 'peran', 'keputusan', 'catatan'];

    protected function casts(): array
    {
        return [
            'peran' => Role::class,
            'keputusan' => StatusDokumen::class,
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenPengajuan::class, 'dokumen_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemeriksa_id');
    }
}
