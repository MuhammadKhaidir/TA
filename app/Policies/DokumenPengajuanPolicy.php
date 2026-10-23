<?php

namespace App\Policies;

use App\Models\DokumenPengajuan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Berkas mengikuti kewenangan atas pengajuannya: pemilik, dosen pembimbing, dan operator.
 */
class DokumenPengajuanPolicy
{
    public function view(User $user, DokumenPengajuan $dokumen): bool
    {
        $pengajuan = $dokumen->pengajuan;

        return $pengajuan instanceof Pengajuan && $user->can('view', $pengajuan);
    }
}
