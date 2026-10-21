<?php

namespace App\Models;

use App\Enums\StatusSuratPengantar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat pengantar TA. Kolom status dan kolom pemeriksaan/pemrosesan sengaja tidak fillable:
 * hanya diubah SuratPengantarService.
 */
class SuratPengantar extends Model
{
    protected $table = 'surat_pengantars';

    protected $fillable = ['data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => StatusSuratPengantar::class,
            'diajukan_pada' => 'datetime',
            'diperiksa_pada' => 'datetime',
            'diproses_pada' => 'datetime',
            'diambil_pada' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperiksa_oleh');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /** Daftar isian (kunci => [label, jenis, wajib]) dari config/ta.php. */
    public static function daftarIsian(): array
    {
        return config('ta.surat_pengantar_data', []);
    }

    /** Nama isian wajib yang belum diisi. */
    public function isianKurang(): array
    {
        $kurang = [];
        foreach (self::daftarIsian() as $kunci => [$label, , $wajib]) {
            if ($wajib && blank($this->data[$kunci] ?? null)) {
                $kurang[] = $label;
            }
        }

        return $kurang;
    }
}
