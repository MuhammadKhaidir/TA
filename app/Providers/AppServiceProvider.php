<?php

namespace App\Providers;

use App\Models\DokumenPengajuan;
use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Policies\DokumenPengajuanPolicy;
use App\Policies\DokumenResmiPolicy;
use App\Policies\PengajuanPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Pengajuan::class, PengajuanPolicy::class);
        Gate::policy(DokumenPengajuan::class, DokumenPengajuanPolicy::class);
        Gate::policy(DokumenResmi::class, DokumenResmiPolicy::class);
    }
}
