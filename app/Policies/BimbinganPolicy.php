<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Bimbingan;
use App\Models\User;
use App\Services\BimbinganService;

class BimbinganPolicy
{
    public function __construct(private readonly BimbinganService $service) {}

    public function milik(User $user, Bimbingan $bimbingan): bool
    {
        return $user->hasRole(Peran::Mahasiswa->value) && $user->mahasiswa?->id === $bimbingan->mahasiswa_id;
    }

    /** Dospem yang ditetapkan pada pengajuan (jenis yang sama) milik mahasiswa tersebut. */
    public function validasi(User $user, Bimbingan $bimbingan): bool
    {
        if (! $user->hasRole(Peran::Dospem->value) || ! $user->dosen) {
            return false;
        }

        $pengajuan = $this->service->pengajuanTerkait($bimbingan->mahasiswa_id, $bimbingan->jenis);

        return $pengajuan !== null && $pengajuan->dospem_id === $user->dosen->id;
    }

    /** Bukti bimbingan: pemilik atau Dospem yang berwenang. */
    public function view(User $user, Bimbingan $bimbingan): bool
    {
        return $this->milik($user, $bimbingan) || $this->validasi($user, $bimbingan);
    }
}
