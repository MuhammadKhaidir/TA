@php($pasir = auth()->user()?->role === \App\Enums\Role::Mahasiswa)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@if ($pasir)
    {{-- Mahasiswa: tema pasir (sidebar krem, header ringkas). Role lain memakai tema navy di bawah. --}}
    <body class="tema-pasir bg-sand-50 font-sans text-ink-900 antialiased">
        @include('partials.shell-pasir')
    </body>
@else
    <body>
        <div class="min-h-screen">
            <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

            @include('partials.sidebar')

            <div class="lg:pl-64">
                @include('partials.navbar')

                <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    @include('partials.flash')
                    @yield('konten')
                </main>
            </div>
        </div>
    </body>
@endif
</html>
