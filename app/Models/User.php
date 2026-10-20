<?php

namespace App\Models;

use App\Enums\Peran;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'aktif', 'semua_prodi'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    public function mahasiswa()
    {
        return $this->hasOne(Mahasiswa::class);
    }

    public function dosen()
    {
        return $this->hasOne(Dosen::class);
    }

    /** Prodi yang menjadi cakupan kerja Operator Akademik. */
    public function prodiOperator(): BelongsToMany
    {
        return $this->belongsToMany(Prodi::class, 'operator_prodi');
    }

    /** Role valid pertama milik pengguna; null bila tidak punya satu pun dari empat aktor. */
    public function peranUtama(): ?Peran
    {
        foreach (Peran::cases() as $peran) {
            if ($this->hasRole($peran->value)) {
                return $peran;
            }
        }

        return null;
    }

    /** Boleh masuk sistem: akun aktif dan memiliki salah satu dari empat role. */
    public function bolehLogin(): bool
    {
        return $this->aktif && $this->peranUtama() !== null;
    }

    /**
     * Cakupan prodi untuk Operator Akademik. null = seluruh prodi (assignment
     * eksplisit lewat flag semua_prodi); array kosong = tidak ada akses.
     *
     * @return array<int, int>|null
     */
    public function cakupanProdi(): ?array
    {
        if ($this->semua_prodi) {
            return null;
        }

        return $this->prodiOperator()->pluck('prodis.id')->map(fn ($id) => (int) $id)->all();
    }

    public function bolehMengelolaProdi(int $prodiId): bool
    {
        if (! $this->hasRole(Peran::OperatorAkademik->value)) {
            return false;
        }

        $cakupan = $this->cakupanProdi();

        return $cakupan === null || in_array($prodiId, $cakupan, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif && $this->hasRole(Peran::Admin->value);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'semua_prodi' => 'boolean',
        ];
    }
}
