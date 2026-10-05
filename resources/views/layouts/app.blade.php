{{--
    Layout induk untuk semua peran selain mahasiswa (SekDep, Penata, Pengelola, dst).
    Tampilannya disamakan dengan layouts/mahasiswa (palet sand + ink, sidebar krem).

    Section yang dipakai:
      title, page-title, page-subtitle, content

    Butuh (dari patch dashboard mahasiswa): palet sand/ink + kelas mhs-* di app.css,
    dan komponen <x-mhs.icon>.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistem TA') &mdash; Fasilkom Unsri</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Animasi fade-in untuk konten halaman (dipakai semua halaman yang extends layout ini) */
        @keyframes halaman-masuk {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .halaman-masuk {
            animation: halaman-masuk .5s ease-out backwards;
        }
        @media (prefers-reduced-motion: reduce) {
            .halaman-masuk { animation: none; }
        }
    </style>
</head>
<body class="bg-sand-50 font-sans text-ink-900 antialiased">
    @php
        // Ilustrasi gedung (opsional): taruh berkas di public/images/gedung-fasilkom.png
        $gedung = file_exists(public_path('images/gedung-fasilkom.png')) ? asset('images/gedung-fasilkom.png') : null;
        $pengguna = auth()->user();
        $mhsLogin = $pengguna?->mahasiswa;

        // Menu per peran: [peran => [nama route, label]]
        $navItems = [
            'mahasiswa' => ['mahasiswa.dashboard', 'Dashboard Saya'],
            'sekdep_koor_prodi' => ['sekdep.dashboard', 'SekDep / Koor. Prodi'],
            'penata' => ['penata.dashboard', 'Penata'],
            'pengelola_layanan' => ['pengelola.dashboard', 'Pengelola Layanan'],
            'pengadministrasi_perkantoran' => ['administrasi.dashboard', 'Pengadministrasi'],
            'dosen_penguji' => ['dosen.dashboard', 'Dosen Penguji'],
        ];

        // Dihitung sekali, dipakai bersama oleh sidebar dan navigasi layar kecil.
        $menu = [];
        if ($pengguna) {
            foreach ($navItems as $peran => [$namaRoute, $label]) {
                if ($pengguna->hasRole($peran)) {
                    $menu[] = [
                        'url' => route($namaRoute),
                        'label' => $label,
                        'ikon' => 'home',
                        'aktif' => request()->routeIs($namaRoute),
                    ];
                }
            }
            if ($pengguna->hasRole('admin')) {
                $menu[] = ['url' => '/admin', 'label' => 'Panel Admin', 'ikon' => 'shield', 'aktif' => false];
            }
        }
    @endphp

    <div class="flex min-h-screen bg-gradient-to-br from-sand-50 via-sand-50 to-sand-100">
        {{-- Sidebar --}}
        <aside class="relative hidden w-72 shrink-0 flex-col overflow-hidden border-r border-sand-200 bg-sand-100/80 lg:sticky lg:top-0 lg:flex lg:h-screen">
            {{-- Dekorasi bawah --}}
            @if ($gedung)
                <img src="{{ $gedung }}" alt="" class="pointer-events-none absolute inset-x-0 bottom-0 w-full object-cover opacity-30 mix-blend-multiply">
            @endif
            <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-72 w-full" viewBox="0 0 288 288" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 170 C60 130 120 150 180 118 S260 104 288 94 V288 H0Z" fill="#efe3cf" opacity=".55" />
                <path d="M0 214 C70 182 130 208 200 180 S260 170 288 158 V288 H0Z" fill="#e6d6bd" opacity=".5" />
            </svg>

            <div class="relative z-10 flex items-center gap-3 px-7 py-7">
                <svg viewBox="0 0 48 48" class="h-11 w-11 shrink-0" aria-hidden="true">
                    <path d="M10 25v9c0 3 6 6 14 6s14-3 14-6v-9l-14 7-14-7Z" fill="#b8955f" />
                    <path d="M24 7 2 18l22 11 22-11L24 7Z" fill="#98743f" />
                    <path d="M44 19v12" stroke="#765732" stroke-width="2" stroke-linecap="round" />
                </svg>
                <div class="leading-tight">
                    <p class="text-lg font-bold text-ink-900">Sistem TA</p>
                    <p class="text-sm text-ink-500">Fasilkom Unsri</p>
                </div>
            </div>

            <nav class="relative z-10 flex-1 space-y-1.5 px-4 py-2">
                @foreach ($menu as $item)
                    <a href="{{ $item['url'] }}"
                       class="mhs-nav-link {{ $item['aktif'] ? 'mhs-nav-link-active' : '' }}"
                       @if ($item['aktif']) aria-current="page" @endif>
                        <x-mhs.icon :name="$item['ikon']" class="h-6 w-6 {{ $item['aktif'] ? 'text-sand-600' : 'text-ink-600' }}" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="relative z-10 flex items-center gap-2.5 px-7 py-5 text-sm text-ink-600" title="POS 020/POS/FASILKOM/2026">
                <x-mhs.icon name="shield" class="h-5 w-5 text-ink-700" />
                Bagan Alir POS
            </div>
        </aside>

        {{-- Konten --}}
        <div class="relative flex min-h-screen min-w-0 flex-1 flex-col overflow-hidden">
            <div class="pointer-events-none absolute -top-44 right-[10%] h-72 w-[42rem] rounded-full bg-sand-200/60 blur-3xl" aria-hidden="true"></div>

            <header class="relative z-20 flex items-center justify-between gap-4 border-b border-sand-200/80 bg-white/50 px-6 py-5 backdrop-blur lg:px-10">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-ink-900 sm:text-2xl">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('page-subtitle')
                        <p class="mt-0.5 text-sm text-ink-600 sm:text-base">@yield('page-subtitle')</p>
                    @endif
                </div>

                @auth
                    <details class="relative shrink-0" data-dropdown>
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-full py-1 pl-1 pr-3 transition hover:bg-sand-100 [&::-webkit-details-marker]:hidden">
                            <span class="mhs-icon-bubble h-12 w-12">
                                <x-mhs.icon name="user" class="h-6 w-6" />
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block text-sm font-semibold text-ink-900">{{ $pengguna->name }}</span>
                                <span class="block text-xs text-ink-500">{{ str($pengguna->getRoleNames()->first() ?? '-')->headline() }}</span>
                            </span>
                            <x-mhs.icon name="chevron-down" class="h-4 w-4 text-ink-500" />
                        </summary>

                        <div class="absolute right-0 mt-2 w-64 rounded-2xl border border-sand-200 bg-white p-2 shadow-lg">
                            <div class="border-b border-sand-200 px-3 pb-3 pt-2">
                                @if ($mhsLogin)
                                    <p class="mhs-eyebrow">NIM</p>
                                    <p class="text-sm font-medium text-ink-900">{{ $mhsLogin->nim }}</p>
                                    @if ($mhsLogin->prodi)
                                        <p class="mt-0.5 text-xs text-ink-500">{{ $mhsLogin->prodi }}</p>
                                    @endif
                                @else
                                    <p class="mhs-eyebrow">Peran</p>
                                    <p class="text-sm font-medium text-ink-900">
                                        {{ $pengguna->getRoleNames()->map(fn ($r) => str($r)->headline())->join(', ') ?: '-' }}
                                    </p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="pt-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-ink-800 transition hover:bg-sand-100">
                                    <x-mhs.icon name="logout" class="h-4 w-4 text-ink-500" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </details>
                @endauth
            </header>

            {{-- Navigasi ringkas untuk layar kecil (sidebar disembunyikan di bawah lg) --}}
            @if (count($menu) > 0)
                <nav class="relative z-10 flex gap-2 overflow-x-auto border-b border-sand-200/80 bg-sand-100/70 px-6 py-2.5 lg:hidden">
                    @foreach ($menu as $item)
                        <a href="{{ $item['url'] }}"
                           class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium {{ $item['aktif'] ? 'bg-sand-300/70 text-ink-900' : 'text-ink-700' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            <main class="halaman-masuk relative z-10 flex-1 px-6 py-8 lg:px-10">
                @if (session('success'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-800">
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

    <script>
        // Tutup dropdown pengguna saat klik di luar.
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details[data-dropdown][open]').forEach(function (d) {
                if (! d.contains(e.target)) d.removeAttribute('open');
            });
        });
    </script>
</body>
</html>