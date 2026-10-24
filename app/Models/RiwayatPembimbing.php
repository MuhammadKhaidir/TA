<?php

namespace App\Models;

use App\Enums\StatusPembimbing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak setiap pemilihan, konfirmasi, penolakan, dan penetapan dosen pembimbing. */
class RiwayatPembimbing extends Model
{
    protected $table = 'riwayat_pembimbing';

    protected $fillable = ['pengajuan_id', 'dosen_id', 'user_id', 'status', 'catatan'];

    protected function casts(): array
    {
        return ['status' => StatusPembimbing::class];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    /** Dosen yang bersangkutan pada baris riwayat ini. */
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    /** Pengguna yang melakukan tindakan (mahasiswa, dosen, atau operator). */
    public function oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
