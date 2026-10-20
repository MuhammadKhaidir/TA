<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\StatusBimbingan;
use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    protected $fillable = [
        'user_id',
        'nim',
        'nama',
        'prodi_id',
        'angkatan',
        'no_hp',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function pengajuans(): HasMany
    {
        return $this->hasMany(Pengajuan::class);
    }

    public function bimbingans(): HasMany
    {
        return $this->hasMany(Bimbingan::class);
    }

    /** Pengajuan TA/KP yang masih berjalan (belum selesai) untuk satu jenis. */
    public function pengajuanBerjalan(JenisPengajuan $jenis): ?Pengajuan
    {
        return $this->pengajuans()
            ->where('jenis', $jenis->value)
            ->where('status', '!=', StatusPengajuan::Selesai->value)
            ->latest('id')
            ->first();
    }

    /** @return array{tercatat: int, divalidasi: int, minimal: ?int} */
    public function ringkasanBimbingan(JenisPengajuan $jenis): array
    {
        $query = $this->bimbingans()->where('jenis', $jenis->value);

        return [
            'tercatat' => (clone $query)->count(),
            'divalidasi' => (clone $query)->where('status_validasi', StatusBimbingan::Divalidasi->value)->count(),
            'minimal' => Pengaturan::minimalBimbingan($jenis->value),
        ];
    }
}
