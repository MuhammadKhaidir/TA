<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPembimbing;
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
        'mahasiswa_id', 'pembimbing_id', 'pembimbing_pilihan_id', 'status_pembimbing',
        'jenis', 'judul', 'deskripsi', 'instansi', 'status', 'diajukan_at',
    ];

    /** Nilai awal agar model yang baru dibuat langsung memiliki status pembimbing. */
    protected $attributes = [
        'status_pembimbing' => 'belum',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPengajuan::class,
            'status' => StatusPengajuan::class,
            'status_pembimbing' => StatusPembimbing::class,
            'diajukan_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    /** Dosen pembimbing yang sah (sudah menerima atau ditetapkan operator). Null bila belum ada. */
    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_id');
    }

    /** Khusus TA: dosen yang dipilih mahasiswa saat pengajuan. */
    public function pembimbingPilihan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_pilihan_id');
    }

    public function riwayatPembimbing(): HasMany
    {
        return $this->hasMany(RiwayatPembimbing::class, 'pengajuan_id')->orderBy('id');
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

    /**
     * Pengajuan yang sudah dikirim tetapi masih membutuhkan penetapan pembimbing oleh operator:
     * belum ada pembimbing (KP), ditolak dosen (TA), atau dosen pilihan mahasiswa sudah nonaktif.
     */
    public function scopePerluPembimbing(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [StatusPengajuan::Draft->value, StatusPengajuan::Diarsipkan->value])
            ->where(function (Builder $q) {
                $q->whereIn('status_pembimbing', [StatusPembimbing::Belum->value, StatusPembimbing::Ditolak->value])
                    ->orWhere(function (Builder $w) {
                        $w->where('status_pembimbing', StatusPembimbing::Menunggu->value)
                            ->whereHas('pembimbingPilihan', fn (Builder $d) => $d->where('aktif', false));
                    });
            });
    }

    /** Teks singkat pembimbing untuk tabel: nama dosen sah, atau keadaan terkini bila belum ada. */
    public function ringkasanPembimbing(): string
    {
        if ($this->pembimbing_id !== null) {
            return $this->pembimbing?->name ?? '-';
        }

        return match ($this->status_pembimbing) {
            StatusPembimbing::Menunggu => ($this->pembimbingPilihan?->name ?? '-').' (menunggu konfirmasi)',
            StatusPembimbing::Ditolak => 'Ditolak dosen, menunggu penetapan operator',
            default => 'Belum ditentukan',
        };
    }

    /** Penjelasan singkat tentang asal dan status dosen pembimbing. */
    public function keteranganPembimbing(): string
    {
        $pilihan = $this->pembimbingPilihan?->name ?? 'Dosen pilihan';

        return match ($this->status_pembimbing) {
            StatusPembimbing::Diterima => 'Dosen pilihan mahasiswa telah menerima permintaan pembimbing.',
            StatusPembimbing::Ditetapkan => 'Ditetapkan oleh operator akademik.',
            StatusPembimbing::Menunggu => $this->status === StatusPengajuan::Draft
                ? "{$pilihan} baru menerima permintaan setelah pengajuan dikirim."
                : "Menunggu konfirmasi dari {$pilihan}.",
            StatusPembimbing::Ditolak => "{$pilihan} menolak. Operator akademik akan menetapkan dosen pengganti.",
            default => $this->jenis === JenisPengajuan::Kp
                ? 'Ditetapkan oleh operator akademik setelah pengajuan dikirim.'
                : 'Belum ditentukan.',
        };
    }
}
