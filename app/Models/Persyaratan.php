<?php

namespace App\Models;

use App\Enums\JenisPersyaratan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persyaratan extends Model
{
    protected $table = 'persyaratans';

    protected $fillable = ['jenis', 'nama', 'deskripsi', 'wajib', 'waktu_penyerahan', 'urutan', 'aktif', 'prodi_id'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPersyaratan::class,
            'wajib' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Persyaratan aktif untuk satu jenis dan prodi (null pada kolom prodi = berlaku untuk semua prodi). */
    public function scopeBerlakuUntuk(Builder $query, JenisPersyaratan $jenis, ?int $prodiId): Builder
    {
        return $query->where('jenis', $jenis->value)
            ->where('aktif', true)
            ->where(fn (Builder $q) => $q->whereNull('prodi_id')->orWhere('prodi_id', $prodiId))
            ->orderBy('urutan')
            ->orderBy('id');
    }
}
