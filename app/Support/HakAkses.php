<?php

namespace App\Support;

use App\Enums\Peran;
use App\Enums\TugasAdministrasi;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat yang menjawab "peran apa yang mengerjakan tugas ini?".
 * Jawabannya dibaca dari Pengaturan (`akses.*`) dan jatuh ke nilai bawaan Bagian 3 PDF
 * bila belum diatur atau nilainya tidak valid [K-01].
 */
class HakAkses
{
    public static function peranUntuk(TugasAdministrasi $tugas): Peran
    {
        $nilai = Pengaturan::ambil($tugas->kunciPengaturan());
        $peran = $nilai !== null ? Peran::tryFrom($nilai) : null;

        return ($peran !== null && array_key_exists($peran->value, TugasAdministrasi::peranDiizinkan()))
            ? $peran
            : $tugas->bawaan();
    }

    /** Pengguna aktif yang memegang peran pelaku tugas ini (kasar: tanpa cakupan prodi). */
    public static function boleh(User $user, TugasAdministrasi $tugas): bool
    {
        return $user->aktif && $user->hasRole(self::peranUntuk($tugas)->value);
    }

    /**
     * Boleh mengerjakan tugas pada data milik prodi tertentu: peran cocok, dan untuk
     * Operator Akademik prodinya masuk cakupan kerjanya. Admin tidak dibatasi prodi.
     */
    public static function bolehPadaProdi(User $user, TugasAdministrasi $tugas, int $prodiId): bool
    {
        if (! self::boleh($user, $tugas)) {
            return false;
        }

        return $user->hasRole(Peran::OperatorAkademik->value) ? $user->bolehMengelolaProdi($prodiId) : true;
    }

    /**
     * Penerima notifikasi untuk satu tugas: seluruh pemegang peran pelaku, dan untuk Operator
     * hanya yang cakupannya memuat prodi tersebut.
     *
     * @return Collection<int, User>
     */
    public static function pelaku(TugasAdministrasi $tugas, ?int $prodiId = null): Collection
    {
        $peran = self::peranUntuk($tugas);

        return User::query()->where('aktif', true)->role($peran->value)->get()
            ->filter(fn (User $u) => $prodiId === null || $peran === Peran::Admin || $u->bolehMengelolaProdi($prodiId))
            ->values();
    }
}
