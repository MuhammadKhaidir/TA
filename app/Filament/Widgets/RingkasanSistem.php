<?php

namespace App\Filament\Widgets;

use App\Enums\Peran;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use App\Services\SkService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanSistem extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Konfigurasi dan akun';

    protected function getStats(): array
    {
        $kurang = app(SkService::class)->kekuranganKonfigurasi();
        $akun = fn (Peran $p) => User::query()->where('aktif', true)->role($p->value)->count();

        return [
            $kurang === []
                ? Stat::make('Penerbitan SK TA', 'Siap')->description('Template, nomor, dan penandatangan sudah diatur')->color('success')
                : Stat::make('Penerbitan SK TA', 'Belum lengkap')->description('Kurang: '.implode(', ', $kurang))->color('danger'),
            Stat::make('Akun aktif', $akun(Peran::Mahasiswa) + $akun(Peran::Dospem) + $akun(Peran::OperatorAkademik) + $akun(Peran::Admin))
                ->description($akun(Peran::Mahasiswa).' mahasiswa, '.$akun(Peran::Dospem).' dospem, '.$akun(Peran::OperatorAkademik).' operator, '.$akun(Peran::Admin).' admin'),
            Stat::make('Program studi aktif', Prodi::query()->aktif()->count())
                ->description('Periode berjalan: '.(PeriodeAkademik::query()->aktif()->latest('id')->value('nama') ?? 'belum diatur')),
        ];
    }
}
