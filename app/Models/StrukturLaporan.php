<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StrukturLaporan extends Model
{
    protected $table = 'struktur_laporans';

    protected $fillable = ['urutan', 'judul', 'deskripsi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
