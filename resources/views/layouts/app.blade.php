<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistem UAP') &mdash; Fasilkom Unsri</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="hidden w-64 shrink-0 flex-col bg-brand-950 lg:flex">
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gold-500 text-sm font-bold text-brand-950">
                    UAP
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">Sistem UAP</p>
                    <p class="text-xs text-brand-200/70">Fasilkom Unsri</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                @auth
                    @php
                        $navItems = [
                            'mahasiswa' => ['mahasiswa.dashboard', 'Dashboard Saya'],
                            'sekdep_koor_prodi' => ['sekdep.dashboard', 'SekDep / Koor. Prodi'],
                            'penata' => ['penata.dashboard', 'Penata'],
                            'pengelola_layanan' => ['pengelola.dashboard', 'Pengelola Layanan'],
                            'pengadministrasi_perkantoran' => ['administrasi.dashboard', 'Pengadministrasi'],
                            'dosen_penguji' => ['dosen.dashboard', 'Dosen Penguji'],
                        ];
                    @endphp
                    @foreach ($navItems as $role => [$routeName, $label])
                        @if (auth()->user()->hasRole($role))
                            <a href="{{ route($routeName) }}"
                               class="nav-link {{ request()->routeIs($routeName) ? 'nav-link-active' : '' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                                {{ $label }}
                            </a>
                        @endif
                    @endforeach
                    @if (auth()->user()->hasRole('admin'))
                        <a href="/admin" class="nav-link">
                            <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                            Panel Admin
                        </a>
                    @endif
                @endauth
            </nav>

            <div class="border-t border-white/10 p-3">
                <p class="px-2 pb-2 text-xs text-brand-200/60">Bagan Alir POS 020/POS/FASILKOM/2026</p>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex min-h-screen flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('page-subtitle')
                        <p class="text-sm text-slate-500">@yield('page-subtitle')</p>
                    @endif
                </div>
                @auth
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ str(auth()->user()->getRoleNames()->first() ?? '-')->headline() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Keluar</button>
                        </form>
                    </div>
                @endauth
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8">
                @if (session('success'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-medium">Tindakan tidak dapat diproses:</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
