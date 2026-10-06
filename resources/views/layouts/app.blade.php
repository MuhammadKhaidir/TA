{{--
    Layout induk untuk semua peran selain mahasiswa (SekDep, Penata, Pengelola, dst).
    Tampilannya disamakan dengan layouts/mahasiswa (palet sand + ink, sidebar krem,
    latar public/images/BackgroundDashboard.png).

    Section yang dipakai:
      title, page-title, page-subtitle, content

    Butuh: palet sand/ink, kelas mhs-eyebrow di app.css, dan komponen <x-mhs.icon>.

    Latar:
      - $bgMode = 'page'    -> gambar jadi latar seluruh halaman (default)
      - $bgMode = 'sidebar' -> gambar hanya di bagian bawah sidebar
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

        /* Perilaku sidebar: desktop = bisa dilipat, layar kecil = drawer. */
        .mhs-sidebar { width: 18rem; }

        @media (min-width: 1024px) {
            .mhs-sidebar {
                position: sticky;
                top: 0;
                height: 100vh;
                height: 100dvh;
                transition: margin-left .3s ease, opacity .25s ease, visibility 0s;
            }
            .mhs-shell[data-collapsed="true"] .mhs-sidebar {
                margin-left: -18rem;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: margin-left .3s ease, opacity .25s ease, visibility 0s .3s;
            }
        }

        @media (max-width: 1023.98px) {
            .mhs-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 50;
                transform: translateX(-100%);
                visibility: hidden;
                transition: transform .3s ease, visibility 0s .3s;
            }
            .mhs-shell[data-drawer="open"] .mhs-sidebar {
                transform: translateX(0);
                visibility: visible;
                transition: transform .3s ease, visibility 0s;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .halaman-masuk { animation: none; }
            .mhs-sidebar { transition: none !important; }
        }
    </style>
</head>
<body class="bg-sand-50 font-sans text-ink-900 antialiased">
    @php
        // Latar dashboard: public/images/BackgroundDashboard.png
        $bgDashboard = file_exists(public_path('images/BackgroundDashboard.png'))
            ? asset('images/BackgroundDashboard.png')
            : null;

        // 'page'    -> gambar jadi latar seluruh halaman, sidebar dibuat tembus pandang
        // 'sidebar' -> gambar hanya dipasang di bagian bawah sidebar (kalau isinya cuma ilustrasi gedung)
        $bgMode = 'page';
        $bgFull = $bgDashboard && $bgMode === 'page';

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

        // Dihitung sekali, dipakai bersama oleh sidebar desktop dan drawer mobile.
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

    {{-- Latar halaman (tetap di tempat saat konten di-scroll) --}}
    <div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-br from-sand-50 via-sand-50 to-sand-100" aria-hidden="true">
        @if ($bgFull)
            <div class="absolute inset-0 bg-cover bg-left-bottom bg-no-repeat"
                 style="background-image: url('{{ $bgDashboard }}')"></div>
        @endif
    </div>

    <div id="mhs-shell" class="mhs-shell relative z-10 flex min-h-screen">
        {{-- Ingat kondisi sidebar sebelum halaman digambar, biar tidak berkedip. --}}
        <script>
            try {
                if (localStorage.getItem('mhs-sidebar-collapsed') === '1') {
                    document.getElementById('mhs-shell').dataset.collapsed = 'true';
                }
            } catch (e) {}
        </script>

        {{-- Sidebar --}}
        <aside id="mhs-sidebar"
               class="mhs-sidebar flex shrink-0 flex-col overflow-hidden border-r border-sand-200 {{ $bgFull ? 'bg-sand-100/40' : 'bg-sand-100/80' }} max-lg:bg-sand-50 max-lg:shadow-2xl">

            {{-- Ilustrasi gedung (hanya mode 'sidebar') --}}
            @if ($bgDashboard && ! $bgFull)
                <img src="{{ $bgDashboard }}" alt=""
                     class="pointer-events-none absolute inset-x-0 bottom-0 h-[55%] w-full object-cover object-left-bottom">
            @endif

            {{-- Dekorasi gelombang kalau gambar latar belum ada --}}
            @unless ($bgDashboard)
                <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-72 w-full" viewBox="0 0 288 288" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 170 C60 130 120 150 180 118 S260 104 288 94 V288 H0Z" fill="#efe3cf" opacity=".55" />
                    <path d="M0 214 C70 182 130 208 200 180 S260 170 288 158 V288 H0Z" fill="#e6d6bd" opacity=".5" />
                </svg>
            @endunless

            {{-- Logo --}}
            <div class="relative z-10 flex items-center gap-3.5 px-7 pb-6 pt-7">
                <svg viewBox="0 0 48 48" class="h-11 w-11 shrink-0" aria-hidden="true">
                    <path d="M10 25v9c0 3 6 6 14 6s14-3 14-6v-9l-14 7-14-7Z" fill="#b8955f" />
                    <path d="M24 7 2 18l22 11 22-11L24 7Z" fill="#98743f" />
                    <path d="M44 19v12" stroke="#765732" stroke-width="2" stroke-linecap="round" />
                </svg>
                <div class="leading-tight">
                    <p class="text-xl font-bold text-ink-900">Sistem TA</p>
                    <p class="text-sm text-ink-500">Fasilkom Unsri</p>
                </div>
            </div>

            {{-- Menu --}}
            <nav class="relative z-10 flex-1 space-y-2 px-4 py-3" aria-label="Menu utama">
                @foreach ($menu as $item)
                    <a href="{{ $item['url'] }}"
                       class="relative flex items-center gap-4 rounded-2xl px-5 py-3.5 text-[17px] font-medium transition-colors duration-200 {{ $item['aktif'] ? 'bg-sand-300/60 font-semibold text-ink-900' : 'text-ink-700 hover:bg-sand-200/70 hover:text-ink-900' }}"
                       @if ($item['aktif']) aria-current="page" @endif>
                        @if ($item['aktif'])
                            <span class="absolute -left-3 top-1/2 h-10 w-1.5 -translate-y-1/2 rounded-full bg-gradient-to-b from-[#c9a46a] to-[#98743f]" aria-hidden="true"></span>
                        @endif
                        <x-mhs.icon :name="$item['ikon']" class="h-6 w-6 {{ $item['aktif'] ? 'text-sand-600' : 'text-ink-600' }}" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Footer sidebar --}}
            <div class="relative z-10 flex items-center gap-3 border-t border-sand-200/70 px-7 py-5 text-sm text-ink-600" title="POS 020/POS/FASILKOM/2026">
                <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-ink-600" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z" />
                    <circle cx="12" cy="10" r="2.4" />
                </svg>
                <span>Bagan Alir POS</span>
                <span class="h-px w-14 bg-sand-300" aria-hidden="true"></span>
            </div>
        </aside>

        {{-- Lapisan gelap di belakang drawer (layar kecil) --}}
        <div id="mhs-backdrop" class="fixed inset-0 z-40 hidden bg-ink-900/30 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

        {{-- Konten --}}
        <div class="relative flex min-h-screen min-w-0 flex-1 flex-col overflow-hidden">
            <div class="pointer-events-none absolute -top-44 right-[10%] h-72 w-[42rem] rounded-full bg-sand-200/60 blur-3xl" aria-hidden="true"></div>

            <header class="relative z-20 flex items-center justify-between gap-4 border-b border-sand-200/80 bg-white/40 px-5 py-4 backdrop-blur-sm lg:px-8">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    {{-- Tombol buka/tutup sidebar --}}
                    <button type="button" id="mhs-toggle"
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[#b8955f] transition hover:bg-sand-100 hover:text-sand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sand-300"
                            aria-controls="mhs-sidebar" aria-expanded="true" aria-label="Buka atau tutup menu samping">
                        <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 6 6 6-6 6" />
                            <path d="m13 6 6 6-6 6" />
                        </svg>
                    </button>

                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-bold leading-tight text-ink-900 sm:text-3xl">@yield('page-title', 'Dashboard')</h1>
                        <p class="mt-0.5 truncate text-sm text-ink-600 sm:text-base">@yield('page-subtitle', 'Sistem Informasi Pengumpulan Tugas Akhir')</p>
                    </div>
                </div>

                {{-- Ranting hias di header --}}
                <svg viewBox="0 0 160 100" class="pointer-events-none absolute bottom-0 right-72 hidden h-24 w-40 text-[#c9a46a] opacity-50 xl:block" fill="none" aria-hidden="true">
                    <path d="M4 96 C40 80 96 54 150 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
                    <g fill="currentColor">
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(34 82) rotate(12)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(34 82) rotate(100)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(70 63) rotate(18)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(70 63) rotate(100)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(109 37) rotate(22)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(109 37) rotate(100)" />
                        <path d="M0 0 C5 -7 5 -17 0 -24 C-5 -17 -5 -7 0 0Z" transform="translate(150 4) rotate(50) scale(.8)" />
                    </g>
                </svg>

                @auth
                    <details class="relative shrink-0" data-dropdown>
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-full bg-white/80 py-1.5 pl-1.5 pr-4 shadow-[0_4px_20px_rgba(152,116,63,0.12)] ring-1 ring-sand-200/80 transition hover:bg-white [&::-webkit-details-marker]:hidden">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-b from-sand-100 to-sand-200 text-sand-600 ring-1 ring-sand-300/60">
                                <x-mhs.icon name="user" class="h-6 w-6" />
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block text-sm font-semibold text-ink-900">{{ $pengguna->name }}</span>
                                <span class="block text-xs text-ink-500">{{ str($pengguna->getRoleNames()->first() ?? '-')->headline() }}</span>
                            </span>
                            <x-mhs.icon name="chevron-down" class="h-4 w-4 text-ink-500" />
                        </summary>

                        <div class="absolute right-0 mt-3 w-64 rounded-2xl border border-sand-200 bg-white p-2 shadow-lg">
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

            <main class="halaman-masuk relative z-10 flex-1 px-5 py-7 lg:px-8">
                <div class="mx-auto w-full max-w-[90rem]">
                    @if (session('success'))
                        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm text-emerald-800 shadow-sm">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-800 shadow-sm">
                            <p class="font-medium">Tindakan tidak dapat diproses:</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
        (function () {
            var shell    = document.getElementById('mhs-shell');
            var toggle   = document.getElementById('mhs-toggle');
            var backdrop = document.getElementById('mhs-backdrop');
            var desktop  = window.matchMedia('(min-width: 1024px)');
            var KEY      = 'mhs-sidebar-collapsed';

            function sync() {
                var open = desktop.matches
                    ? shell.dataset.collapsed !== 'true'
                    : shell.dataset.drawer === 'open';
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                backdrop.classList.toggle('hidden', shell.dataset.drawer !== 'open');
            }

            function closeDrawer() {
                delete shell.dataset.drawer;
                sync();
            }

            toggle.addEventListener('click', function () {
                if (desktop.matches) {
                    var collapsed = shell.dataset.collapsed === 'true';
                    if (collapsed) { delete shell.dataset.collapsed; } else { shell.dataset.collapsed = 'true'; }
                    try { localStorage.setItem(KEY, collapsed ? '0' : '1'); } catch (e) {}
                } else {
                    if (shell.dataset.drawer === 'open') { delete shell.dataset.drawer; } else { shell.dataset.drawer = 'open'; }
                }
                sync();
            });

            backdrop.addEventListener('click', closeDrawer);
            desktop.addEventListener('change', closeDrawer);

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                closeDrawer();
                document.querySelectorAll('details[data-dropdown][open]').forEach(function (d) {
                    d.removeAttribute('open');
                });
            });

            // Tutup dropdown pengguna saat klik di luar.
            document.addEventListener('click', function (e) {
                document.querySelectorAll('details[data-dropdown][open]').forEach(function (d) {
                    if (! d.contains(e.target)) d.removeAttribute('open');
                });
            });

            sync();
        })();
    </script>
</body>
</html>