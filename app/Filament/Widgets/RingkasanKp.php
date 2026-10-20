<?php

namespace App\Filament\Widgets;

use App\Enums\JenisPengajuan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSurat;
use App\Models\Pengajuan;
use App\Models\SuratPermohonan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanKp extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Kerja Praktik (KP)';

    protected function getStats(): array
    {
        $kp = fn () => Pengajuan::query()->where('jenis', JenisPengajuan::Kp->value);

        return [
            Stat::make('Pengajuan KP', $kp()->where('status', '!=', StatusPengajuan::Draft->value)->count())
                ->description($kp()->where('status', StatusPengajuan::Draft->value)->count().' masih draft'),
            Stat::make('Menunggu verifikasi', $kp()->where('status', StatusPengajuan::Diajukan->value)->where('status_administrasi', StatusAdministrasi::Menunggu->value)->count())
                ->color('warning'),
            Stat::make('Disetujui', $kp()->where('status', StatusPengajuan::Disetujui->value)->count())->color('info'),
            Stat::make('Selesai / diarsipkan', $kp()->where('status', StatusPengajuan::Selesai->value)->count())->color('success'),
            Stat::make('Surat KP berjalan', SuratPermohonan::query()->whereIn('status', [StatusSurat::Diajukan->value, StatusSurat::Diproses->value])->count())
                ->description('Diajukan atau sedang diproses'),
        ];
    }
}
