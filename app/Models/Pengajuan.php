<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pengajuan extends Model
{
    protected $table = 'pengajuan';

    protected $fillable = [
        'mahasiswa_id', 'pembimbing_id', 'jenis', 'judul', 'deskripsi',
        'instansi', 'status', 'diajukan_at',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPengajuan::class,
            'status' => StatusPengajuan::class,
            'diajukan_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPengajuan::class, 'pengajuan_id');
    }

    public function dokumenTerbaru(): HasMany
    {
        return $this->hasMany(DokumenPengajuan::class, 'pengajuan_id')->where('terbaru', true);
    }

    public function pemeriksaan(): HasMany
    {
        return $this->hasMany(Pemeriksaan::class, 'pengajuan_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class, 'pengajuan_id')->orderBy('id');
    }

    public function dokumenResmi(): HasMany
    {
        return $this->hasMany(DokumenResmi::class, 'pengajuan_id');
    }

    public function arsip(): HasOne
    {
        return $this->hasOne(Arsip::class, 'pengajuan_id');
    }

    public function scopeJenis(Builder $query, JenisPengajuan $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }

    public function scopeStatus(Builder $query, StatusPengajuan ...$status): Builder
    {
        return $query->whereIn('status', array_map(fn (StatusPengajuan $s) => $s->value, $status));
    }
}
