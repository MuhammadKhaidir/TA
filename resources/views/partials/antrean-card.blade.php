@php
    /** @var string $judul */
    /** @var \Illuminate\Support\Collection $items */
    /** @var string|null $kosong */
@endphp
<div class="card mb-6 p-5">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $judul }}</h3>
    @if ($items->isEmpty())
        <p class="text-sm text-slate-400">{{ $kosong ?? 'Tidak ada sidang pada antrean ini.' }}</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($items as $sidang)
                @include('partials.sidang-row', ['sidang' => $sidang])
            @endforeach
        </ul>
    @endif
</div>
