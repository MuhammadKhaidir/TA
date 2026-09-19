@php
    /** @var \App\Enums\SidangStatus $status */
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $status->badgeClasses() }}">
    {{ $status->label() }}
</span>
