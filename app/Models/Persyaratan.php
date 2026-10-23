<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persyaratan extends Model
{
    /** Ekstensi yang boleh dipilih admin. Sengaja tidak memuat html, svg, zip, atau berkas yang dapat dieksekusi. */
    public const EKSTENSI_DIIZINKAN = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    protected $table = 'persyaratan';

    protected $fillable = [
        'jenis_pengajuan', 'nama', 'deskripsi', 'pemeriksa', 'wajib',
        'urutan', 'ukuran_maks_kb', 'ekstensi', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'jenis_pengajuan' => JenisPengajuan::class,
            'pemeriksa' => Role::class,
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'urutan' => 'integer',
            'ukuran_maks_kb' => 'integer',
        ];
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPengajuan::class, 'persyaratan_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeUntuk(Builder $query, JenisPengajuan $jenis): Builder
    {
        return $query->where('jenis_pengajuan', $jenis->value);
    }

    /** @return list<string> */
    public function ekstensiList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower((string) $this->ekstensi)))));
    }

    /** Aturan validasi unggahan, dibaca langsung dari pengaturan persyaratan. */
    public function aturanBerkas(): array
    {
        return [
            'required',
            'file',
            'mimes:'.implode(',', $this->ekstensiList()),
            'max:'.$this->ukuran_maks_kb,
        ];
    }

    public function ukuranMaksManusia(): string
    {
        return $this->ukuran_maks_kb >= 1024
            ? number_format($this->ukuran_maks_kb / 1024, 0, ',', '.').' MB'
            : $this->ukuran_maks_kb.' KB';
    }
}
