<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisSurat extends Model
{
    protected $fillable = ['kode', 'nama', 'deskripsi', 'persyaratan', 'template_path', 'template_nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function permohonans(): HasMany
    {
        return $this->hasMany(SuratPermohonan::class);
    }
}
