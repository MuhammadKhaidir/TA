<?php

namespace App\Models;

use App\Enums\JenisPersyaratan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persyaratan extends Model
{
    protected $table = 'persyaratans';

    /** Dokumen sidang yang ditautkan otomatis dari data sistem (tidak diunggah mahasiswa). */
    public const TAUTAN_SK_TA = 'sk_ta';

    public const TAUTAN_KARTU_KONSULTASI = 'kartu_konsultasi';

    protected $fillable = ['jenis', 'nama', 'deskripsi', 'wajib', 'waktu_penyerahan', 'tautan_otomatis', 'urutan', 'aktif', 'prodi_id'];

    /** @return array<string, string> */
    public static function opsiTautan(): array
    {
        return [
            self::TAUTAN_SK_TA => 'SK TA yang sudah terbit',
            self::TAUTAN_KARTU_KONSULTASI => 'Kartu konsultasi (PDF dibuat otomatis)',
        ];
    }

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
