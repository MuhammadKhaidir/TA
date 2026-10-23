<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Masuk') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-navy-700 text-base font-bold text-white">FK</div>
            <div class="leading-tight">
                <div class="text-base font-semibold text-navy-900">Arsip Berkas KP dan TA</div>
                <div class="text-sm text-slate-500">Fasilkom Universitas Sriwijaya</div>
            </div>
        </div>

        <div class="w-full max-w-md">
            @yield('konten')
        </div>

        <p class="mt-8 text-center text-xs text-slate-400">
            Sistem Pengumpulan dan Arsip Digital Berkas Kerja Praktik dan Tugas Akhir
        </p>
    </div>
</body>
</html>
