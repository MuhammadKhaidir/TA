@php($menu = \App\Support\Menu::untuk(auth()->user()))

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0" aria-label="Menu utama">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 px-5">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">FK</div>
        <div class="leading-tight">
            <div class="text-sm font-semibold text-navy-900">Arsip KP &amp; TA</div>
            <div class="text-xs text-slate-500">Fasilkom Unsri</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <div class="px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ auth()->user()->role->label() }}</div>
        @foreach ($menu as $butir)
            <a href="{{ $butir['url'] }}" class="menu-link {{ $butir['aktif'] ? 'menu-link-aktif' : '' }}" @if ($butir['aktif']) aria-current="page" @endif>
                <x-icon :name="$butir['icon']" />
                <span>{{ $butir['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="shrink-0 space-y-1 border-t border-slate-200 px-3 py-3">
        <a href="{{ route('profil.show') }}" class="menu-link {{ request()->routeIs('profil.*') ? 'menu-link-aktif' : '' }}">
            <x-icon name="user" />
            <span>Profil</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="menu-link w-full text-left">
                <x-icon name="logout" />
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
