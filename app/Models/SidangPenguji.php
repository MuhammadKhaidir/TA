<?php

namespace App\Models;

use App\Enums\KonfirmasiKehadiran;
use App\Enums\PerananPenguji;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SidangPenguji extends Pivot
{
    public $incrementing = true;

    protected $table = 'sidang_pengujis';

    protected $fillable = [
        'sidang_id',
        'dosen_id',
        'peran',
        'konfirmasi',
        'alasan_berhalangan',
        'nilai_diinput',
        'waktu_input_nilai',
    ];

    protected function casts(): array
    {
        return [
            'peran' => PerananPenguji::class,
            'konfirmasi' => KonfirmasiKehadiran::class,
            'nilai_diinput' => 'boolean',
            'waktu_input_nilai' => 'datetime',
        ];
    }

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(Sidang::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
