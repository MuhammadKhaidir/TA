@props(['label', 'nilai', 'hint' => null, 'href' => null])
@if ($href)
    <a href="{{ $href }}" class="card block p-5 transition hover:border-navy-300">
@else
    <div class="card p-5">
@endif
        <div class="text-sm text-slate-500">{{ $label }}</div>
        <div class="stat-angka">{{ $nilai }}</div>
        @if ($hint)
            <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
