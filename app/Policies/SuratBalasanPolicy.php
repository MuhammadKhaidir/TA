<?php

namespace App\Policies;

use App\Models\Pengajuan;
use App\Models\SuratBalasan;
use App\Models\User;

class SuratBalasanPolicy
{
    /** Mengikuti hak lihat pengajuan: mahasiswa pemilik, dosen pembimbing, operator. Admin tidak. */
    public function view(User $user, SuratBalasan $balasan): bool
    {
        $pengajuan = $balasan->pengajuan;

        return $pengajuan instanceof Pengajuan && $user->can('view', $pengajuan);
    }
}
