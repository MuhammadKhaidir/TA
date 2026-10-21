<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi dalam sistem. Hanya mencatat baris; tidak mengirim email atau pesan luar.
 * Dipanggil dari service alur kerja SETELAH perubahan status berhasil, di dalam transaksi
 * yang sama, sehingga notifikasi tidak pernah muncul untuk perubahan yang dibatalkan.
 */
class NotifikasiService
{
    /**
     * @param  iterable<User>  $penerima
     * @param  User|null  $kecuali  pelaku aksi; tidak perlu diberi tahu tentang aksinya sendiri
     */
    public function kirim(iterable $penerima, string $judul, ?string $isi = null, ?string $url = null, ?Model $subjek = null, ?User $kecuali = null): int
    {
        $sudah = [];

        foreach ($penerima as $user) {
            if (! $user instanceof User || isset($sudah[$user->id]) || $user->id === $kecuali?->id) {
                continue;
            }
            $sudah[$user->id] = true;

            Notifikasi::create([
                'user_id' => $user->id,
                'judul' => $judul,
                'isi' => $isi,
                'url' => $url,
                'subjek_type' => $subjek?->getMorphClass(),
                'subjek_id' => $subjek?->getKey(),
            ]);
        }

        return count($sudah);
    }

    public function tandaiDibaca(Notifikasi $notifikasi): void
    {
        if ($notifikasi->dibaca_pada === null) {
            $notifikasi->forceFill(['dibaca_pada' => now()])->save();
        }
    }

    public function tandaiSemuaDibaca(User $user): int
    {
        return Notifikasi::query()->where('user_id', $user->id)->whereNull('dibaca_pada')->update(['dibaca_pada' => now()]);
    }

    public function jumlahBelumDibaca(User $user): int
    {
        return Notifikasi::query()->where('user_id', $user->id)->whereNull('dibaca_pada')->count();
    }
}
