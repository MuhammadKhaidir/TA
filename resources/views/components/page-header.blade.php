@props(['judul', 'sub' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-navy-900 sm:text-2xl">{{ $judul }}</h1>
        @if ($sub)
            <p class="mt-1 text-sm text-slate-500">{{ $sub }}</p>
        @endif
    </div>
    @isset($aksi)
        <div class="flex flex-wrap items-center gap-2">{{ $aksi }}</div>
    @endisset
</div>
