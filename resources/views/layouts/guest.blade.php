<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Masuk') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Dipakai halaman galat (403, 404). Halaman login memakai layouts.auth. --}}
<body class="tema-pasir bg-gradient-to-br from-sand-50 to-sand-100 font-sans text-ink-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="mb-8 flex items-center gap-3">
            @if (file_exists(public_path('images/UnsriLogo.png')))
                <img src="{{ asset('images/UnsriLogo.png') }}" alt="Logo Universitas Sriwijaya" class="h-11 w-auto shrink-0 object-contain">
            @else
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-ink-800 text-base font-bold text-white">FK</div>
            @endif
            <div class="leading-tight">
                <div class="font-serif text-base font-bold text-ink-900">Arsip Berkas KP dan TA</div>
                <div class="text-sm text-ink-600">Fasilkom Universitas Sriwijaya</div>
            </div>
        </div>

        <div class="w-full max-w-md">
            @yield('konten')
        </div>

        <p class="mt-8 text-center text-xs text-ink-500">
            Sistem Pengumpulan dan Arsip Digital Berkas Kerja Praktik dan Tugas Akhir
        </p>
    </div>
</body>
</html>
