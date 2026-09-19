<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dosen extends Model
{
    protected $fillable = [
        'user_id',
        'nip',
        'nama',
        'prodi',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sidangs(): BelongsToMany
    {
        return $this->belongsToMany(Sidang::class, 'sidang_pengujis')
            ->using(SidangPenguji::class)
            ->withPivot([
                'peran', 'konfirmasi', 'alasan_berhalangan', 'nilai_diinput', 'waktu_input_nilai',
            ])
            ->withTimestamps();
    }
}
