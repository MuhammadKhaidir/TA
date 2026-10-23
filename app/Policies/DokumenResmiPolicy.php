<?php

namespace App\Policies;

use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Models\User;

class DokumenResmiPolicy
{
    public function view(User $user, DokumenResmi $dokumen): bool
    {
        $pengajuan = $dokumen->pengajuan;

        return $pengajuan instanceof Pengajuan && $user->can('view', $pengajuan);
    }
}
