<?php

namespace App\Support;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat log aktivitas. Dipanggil dari controller dan service pada tindakan penting.
 */
class Aktivitas
{
    public static function catat(string $aksi, string $deskripsi, ?Model $subjek = null, ?User $user = null): void
    {
        $user ??= auth()->user();

        LogAktivitas::create([
            'user_id' => $user?->getKey(),
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'ip_address' => request()->ip(),
        ]);
    }
}
