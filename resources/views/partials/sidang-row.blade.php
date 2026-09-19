@php
    /** @var \App\Models\Sidang $sidang */
@endphp
<li class="flex flex-wrap items-center justify-between gap-3 py-3">
    <div class="min-w-0">
        <p class="truncate text-sm font-medium text-slate-800">{{ $sidang->mahasiswa->nama }}</p>
        <p class="truncate text-xs text-slate-400">
            {{ $sidang->mahasiswa->nim }} &middot; {{ $sidang->jenis->label() }}
            @if ($sidang->tanggal_sidang)
                &middot; {{ $sidang->tanggal_sidang->translatedFormat('d M Y') }}
            @endif
        </p>
    </div>
    <div class="flex shrink-0 items-center gap-3">
        @if ($sidang->aksiTerlambat())
            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">Terlambat</span>
        @endif
        @include('partials.status-badge', ['status' => $sidang->status])
        <a href="{{ route('sidang.show', $sidang) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a>
    </div>
</li>
