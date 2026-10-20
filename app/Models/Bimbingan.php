<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\StatusBimbingan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bimbingan extends Model
{
    protected $table = 'bimbingans';

    // Kolom validasi (status_validasi, catatan_dospem, divalidasi_*) sengaja tidak fillable:
    // hanya diubah lewat BimbinganService oleh Dospem yang berwenang.
    protected $fillable = ['mahasiswa_id', 'jenis', 'tanggal', 'topik', 'catatan', 'tindak_lanjut', 'bukti_path'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => JenisPengajuan::class,
            'status_validasi' => StatusBimbingan::class,
            'divalidasi_pada' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
}
