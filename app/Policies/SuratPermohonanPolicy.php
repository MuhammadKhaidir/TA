<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\SuratPermohonan;
use App\Models\User;

class SuratPermohonanPolicy
{
    public function view(User $user, SuratPermohonan $surat): bool
    {
        $pengajuan = $surat->pengajuan;

        return ($user->hasRole(Peran::Mahasiswa->value) && $user->mahasiswa?->id === $pengajuan->mahasiswa_id)
            || $user->bolehMengelolaProdi($pengajuan->prodi_id);
    }

    public function proses(User $user, SuratPermohonan $surat): bool
    {
        return $user->bolehMengelolaProdi($surat->pengajuan->prodi_id);
    }
}
