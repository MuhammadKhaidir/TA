<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'nomor_induk',
        'program_studi_id', 'angkatan', 'no_hp', 'aktif',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'aktif' => 'boolean',
            'angkatan' => 'integer',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    /** Pengajuan KP/TA milik mahasiswa ini. */
    public function pengajuan(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'mahasiswa_id');
    }

    /** Pengajuan yang dibimbing dosen ini (pembimbing sah). */
    public function bimbingan(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'pembimbing_id');
    }

    /** Pengajuan TA yang memilih dosen ini sebagai calon pembimbing. */
    public function permintaanPembimbing(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'pembimbing_pilihan_id');
    }

    public function pemeriksaan(): HasMany
    {
        return $this->hasMany(Pemeriksaan::class, 'pemeriksa_id');
    }

    public function punyaRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function scopePeran(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Menambahkan beban bimbingan dosen:
     * beban_aktif (pengajuan yang sedang dibimbing, belum diarsipkan) dan
     * beban_menunggu (permintaan TA yang belum dijawab).
     */
    public function scopeDenganBeban(Builder $query): Builder
    {
        return $query->withCount([
            'bimbingan as beban_aktif' => fn (Builder $q) => $q->where('status', '!=', StatusPengajuan::Diarsipkan->value),
            'permintaanPembimbing as beban_menunggu' => fn (Builder $q) => $q->where('status_pembimbing', StatusPembimbing::Menunggu->value),
        ]);
    }
}
