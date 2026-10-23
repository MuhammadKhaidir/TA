<header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
    <div class="flex items-center gap-3">
        <button type="button" data-sidebar-toggle class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka atau tutup menu">
            <x-icon name="menu" />
        </button>
        <div class="hidden text-sm text-slate-500 sm:block">Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA</div>
    </div>

    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-100 text-sm font-semibold text-navy-700">
                {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <span class="hidden text-left leading-tight sm:block">
                <span class="block text-sm font-medium text-slate-800">{{ auth()->user()->name }}</span>
                <span class="block text-xs text-slate-500">{{ auth()->user()->role->label() }}</span>
            </span>
            <x-icon name="chevron-down" class="h-4 w-4 text-slate-400" />
        </summary>

        <div class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
            <div class="border-b border-slate-100 px-4 py-2 sm:hidden">
                <div class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</div>
                <div class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</div>
            </div>
            <a href="{{ route('profil.show') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Profil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Logout</button>
            </form>
        </div>
    </details>
</header>
