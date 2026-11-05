@props(['label', 'nilai', 'hint' => null, 'href' => null, 'icon' => 'stack-solid'])
@if ($href)
    <a href="{{ $href }}" class="card kartu-stat relative block overflow-hidden px-5 pb-4 pt-5 transition">
@else
    <div class="card kartu-stat relative overflow-hidden px-5 pb-4 pt-5">
@endif
        {{-- Gelombang hias di sudut kanan bawah --}}
        <svg viewBox="0 0 160 70" class="pointer-events-none absolute bottom-0 right-0 h-16 w-36" fill="none" aria-hidden="true">
            <path d="M0 70 C40 66 70 40 110 28 C130 22 148 22 160 24 V70Z" fill="#f1e2c2" opacity=".55" />
            <path d="M20 70 C60 62 92 44 128 38 C142 36 152 36 160 38" stroke="#d9b36b" stroke-width="1.2" opacity=".6" />
        </svg>

        <div class="relative flex items-center gap-4">
            <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-b from-[#f6e8c8] to-[#efd8aa] text-sand-600 ring-1 ring-[#e3c98f]/70">
                <x-pasir.icon :name="$icon" class="h-8 w-8" />
            </span>
            <span class="h-14 w-px shrink-0 bg-[#e3d0a8]" aria-hidden="true"></span>
            <div class="min-w-0">
                <div class="font-serif text-[15px] font-bold leading-tight text-ink-900">{{ $label }}</div>
                <div class="stat-angka">{{ $nilai }}</div>
            </div>
        </div>
        @if ($hint)
            <div class="relative mt-3 text-[13px] leading-snug text-ink-600">{{ $hint }}</div>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
