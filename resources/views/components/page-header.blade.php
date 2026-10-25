@props(['judul', 'sub' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="judul-halaman">{{ $judul }}</h1>
        @if ($sub)
            <p class="sub-halaman">{{ $sub }}</p>
        @endif
    </div>
    @isset($aksi)
        <div class="flex flex-wrap items-center gap-2">{{ $aksi }}</div>
    @endisset
</div>
