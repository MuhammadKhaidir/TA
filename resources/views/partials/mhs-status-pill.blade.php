@php
    /** @var \App\Enums\SidangStatus $status */
    $pillClasses = match ($status) {
        \App\Enums\SidangStatus::Selesai => 'bg-emerald-100 text-emerald-800',
        \App\Enums\SidangStatus::Ditunda => 'bg-rose-100 text-rose-700',
        \App\Enums\SidangStatus::MenungguPenjadwalanUlang => 'bg-amber-100 text-amber-800',
        default => 'bg-sand-200 text-ink-800',
    };
@endphp
<span class="inline-flex items-center whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium {{ $pillClasses }}">
    {{ $status->label() }}
</span>