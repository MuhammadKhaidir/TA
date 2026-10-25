{{--
    Kerangka halaman tema pasir untuk semua role (Mahasiswa, Dosen, Operator, Admin): sidebar krem yang bisa dilipat + header ringkas.
    Hanya tampilan. Menu tetap dari App\Support\Menu::untuk(), sehingga isi dan hak akses menu tidak berubah.

    Latar halaman: public/images/BackgroundDashboard.png (dilewati bila berkasnya tidak ada).
    Perilaku buka/tutup sidebar ada di resources/js/app.js (id shell-pasir, toggle-pasir, backdrop-pasir).
--}}
@php
    $pengguna = auth()->user();
    $menu = \App\Support\Menu::untuk($pengguna);
    $latar = file_exists(public_path('images/BackgroundDashboard.png')) ? asset('images/BackgroundDashboard.png') : null;
@endphp

{{-- Latar halaman (tetap di tempat saat konten di-scroll) --}}
<div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-br from-sand-50 via-sand-50 to-sand-100" aria-hidden="true">
    @if ($latar)
        <div class="absolute inset-0 bg-cover bg-left-bottom bg-no-repeat" style="background-image: url('{{ $latar }}')"></div>
    @endif
</div>

<div id="shell-pasir" class="shell-pasir relative z-10 flex min-h-screen">
    {{-- Ingat kondisi sidebar sebelum halaman digambar, supaya tidak berkedip. --}}
    <script>
        try {
            if (localStorage.getItem('sidebar-pasir-lipat') === '1') {
                document.getElementById('shell-pasir').dataset.collapsed = 'true';
            }
        } catch (e) {}
    </script>

    {{-- Sidebar --}}
    <aside id="sidebar-pasir"
           class="sidebar-pasir flex shrink-0 flex-col overflow-hidden border-r border-sand-200 {{ $latar ? 'bg-sand-100/40' : 'bg-sand-100/80' }} max-lg:bg-sand-50 max-lg:shadow-2xl"
           aria-label="Menu utama">

        {{-- Dekorasi gelombang bila gambar latar belum ada --}}
        @unless ($latar)
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
                <p class="text-xl font-bold text-ink-900">Arsip KP &amp; TA</p>
                <p class="text-sm text-ink-500">Fasilkom Unsri</p>
            </div>
        </div>

        {{-- Menu --}}
        <nav class="relative z-10 flex-1 space-y-2 overflow-y-auto px-4 py-3">
            <p class="sub-label px-5 pb-1">{{ $pengguna->role->label() }}</p>
            @foreach ($menu as $butir)
                <a href="{{ $butir['url'] }}"
                   class="relative flex items-center gap-4 rounded-2xl px-5 py-3.5 text-[16px] font-medium transition-colors duration-200 {{ $butir['aktif'] ? 'bg-sand-300/60 font-semibold text-ink-900' : 'text-ink-700 hover:bg-sand-200/70 hover:text-ink-900' }}"
                   @if ($butir['aktif']) aria-current="page" @endif>
                    @if ($butir['aktif'])
                        <span class="absolute -left-3 top-1/2 h-10 w-1.5 -translate-y-1/2 rounded-full bg-gradient-to-b from-[#c9a46a] to-[#98743f]" aria-hidden="true"></span>
                    @endif
                    <x-pasir.icon :name="$butir['icon']" :class="$butir['aktif'] ? 'h-6 w-6 shrink-0 text-sand-600' : 'h-6 w-6 shrink-0 text-ink-600'" />
                    <span>{{ $butir['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Profil dan keluar --}}
        <div class="relative z-10 space-y-1 border-t border-sand-200/70 px-4 py-4">
            <a href="{{ route('profil.show') }}"
               class="flex items-center gap-4 rounded-2xl px-5 py-2.5 text-[15px] font-medium transition-colors duration-200 {{ request()->routeIs('profil.*') ? 'bg-sand-300/60 font-semibold text-ink-900' : 'text-ink-700 hover:bg-sand-200/70 hover:text-ink-900' }}"
               @if (request()->routeIs('profil.*')) aria-current="page" @endif>
                <x-pasir.icon name="user" class="h-5 w-5 shrink-0 text-ink-600" />
                <span>Profil</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-4 rounded-2xl px-5 py-2.5 text-left text-[15px] font-medium text-ink-700 transition-colors duration-200 hover:bg-sand-200/70 hover:text-ink-900">
                    <x-pasir.icon name="logout" class="h-5 w-5 shrink-0 text-ink-600" />
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Lapisan gelap di belakang laci sidebar (layar kecil) --}}
    <div id="backdrop-pasir" class="fixed inset-0 z-40 hidden bg-ink-900/30 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    {{-- Konten --}}
    <div class="relative flex min-h-screen min-w-0 flex-1 flex-col overflow-hidden">
        <div class="pointer-events-none absolute -top-44 right-[10%] h-72 w-[42rem] rounded-full bg-sand-200/60 blur-3xl" aria-hidden="true"></div>

        <header class="relative z-20 flex items-center justify-between gap-4 border-b border-sand-200/80 bg-white/40 px-5 py-4 backdrop-blur-sm lg:px-8">
            <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                {{-- Tombol buka/tutup sidebar --}}
                <button type="button" id="toggle-pasir"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[#b8955f] transition hover:bg-sand-100 hover:text-sand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sand-300"
                        aria-controls="sidebar-pasir" aria-expanded="true" aria-label="Buka atau tutup menu samping">
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 6 6 6-6 6" />
                        <path d="m13 6 6 6-6 6" />
                    </svg>
                </button>

                <p class="hidden min-w-0 truncate text-sm text-ink-600 sm:block">Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA</p>
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

            <details class="relative shrink-0" data-dropdown>
                <summary class="flex cursor-pointer list-none items-center gap-3 rounded-full bg-white/80 py-1.5 pl-1.5 pr-4 shadow-[0_4px_20px_rgba(152,116,63,0.12)] ring-1 ring-sand-200/80 transition hover:bg-white [&::-webkit-details-marker]:hidden">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-b from-sand-100 to-sand-200 text-sand-600 ring-1 ring-sand-300/60">
                        <x-pasir.icon name="user" class="h-6 w-6" />
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-semibold text-ink-900">{{ $pengguna->name }}</span>
                        <span class="block text-xs text-ink-500">{{ $pengguna->role->label() }}</span>
                    </span>
                    <x-pasir.icon name="chevron-down" class="h-4 w-4 text-ink-500" />
                </summary>

                <div class="absolute right-0 mt-3 w-64 rounded-2xl border border-sand-200 bg-white p-2 shadow-lg">
                    <div class="border-b border-sand-200 px-3 pb-3 pt-2">
                        <p class="text-sm font-semibold text-ink-900">{{ $pengguna->name }}</p>
                        <p class="text-xs text-ink-500">{{ $pengguna->nomor_induk ? $pengguna->nomor_induk.' · ' : '' }}{{ $pengguna->role->label() }}</p>
                    </div>
                    <a href="{{ route('profil.show') }}" class="mt-1 flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-800 transition hover:bg-sand-100">
                        <x-pasir.icon name="user" class="h-4 w-4 text-ink-500" />
                        Profil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-ink-800 transition hover:bg-sand-100">
                            <x-pasir.icon name="logout" class="h-4 w-4 text-ink-500" />
                            Logout
                        </button>
                    </form>
                </div>
            </details>
        </header>

        <main class="relative z-10 flex-1 px-5 py-7 lg:px-8">
            <div class="mx-auto w-full max-w-[90rem]">
                @include('partials.flash')
                @yield('konten')
            </div>
        </main>
    </div>
</div>
