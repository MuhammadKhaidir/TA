<?php

namespace App\Filament\Widgets;

use App\Enums\JenisPengajuan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\SuratKeputusan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanTa extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Tugas Akhir (TA)';

    protected function getStats(): array
    {
        $ta = fn () => Pengajuan::query()->where('jenis', JenisPengajuan::Ta->value);

        return [
            Stat::make('Pengajuan TA', $ta()->where('status', '!=', StatusPengajuan::Draft->value)->count())
                ->description($ta()->where('status', StatusPengajuan::Draft->value)->count().' masih draft'),
            Stat::make('Menunggu verifikasi', $ta()->where('status', StatusPengajuan::Diajukan->value)->where('status_administrasi', StatusAdministrasi::Menunggu->value)->count())
                ->description('Antrean Operator Akademik')->color('warning'),
            Stat::make('Perlu revisi', $ta()->where('status', StatusPengajuan::PerluRevisi->value)->count())
                ->description('Menunggu perbaikan mahasiswa')->color('danger'),
            Stat::make('Siap terbit SK', $ta()->where('status', StatusPengajuan::Disetujui->value)->count())
                ->description('Sudah diverifikasi dan disetujui')->color('info'),
            Stat::make('SK TA terbit', SuratKeputusan::query()->count())->color('success'),
        ];
    }
}
