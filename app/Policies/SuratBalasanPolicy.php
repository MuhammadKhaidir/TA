<?php

namespace App\Policies;

use App\Enums\TugasAdministrasi;
use App\Models\Pengajuan;
use App\Models\SuratBalasan;
use App\Models\User;
use App\Support\HakAkses;

class SuratBalasanPolicy
{
    /** Unduh berkas: siapa pun yang boleh melihat pengajuannya (aturan tunggal ada di scope Pengajuan). */
    public function view(User $user, SuratBalasan $surat): bool
    {
        return Pengajuan::query()->dapatDilihatOleh($user)->whereKey($surat->pengajuan_id)->exists();
    }

    /** Validasi checklist: pemegang tugas ValidasiSuratBalasan pada prodi terkait. */
    public function validasi(User $user, SuratBalasan $surat): bool
    {
        return HakAkses::bolehPadaProdi($user, TugasAdministrasi::ValidasiSuratBalasan, $surat->pengajuan->prodi_id);
    }
}
