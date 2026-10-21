<?php

namespace App\Models;

use App\Enums\StatusSuratBalasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Surat balasan dari instansi tujuan. Status dan checklist hanya diubah SuratBalasanService. */
class SuratBalasan extends Model
{
    protected $table = 'surat_balasans';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => StatusSuratBalasan::class,
            'checklist' => 'array',
            'divalidasi_pada' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }

    /** Butir checklist (kunci => label) dari config/ta.php. */
    public static function daftarChecklist(): array
    {
        return config('ta.surat_balasan_checklist', []);
    }
}
