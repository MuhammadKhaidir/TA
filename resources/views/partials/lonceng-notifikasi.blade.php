{{-- Lonceng notifikasi dalam sistem di header: tautan ke daftar + jumlah yang belum dibaca. --}}
@php
    $belumDibaca = auth()->check() ? app(\App\Services\NotifikasiService::class)->jumlahBelumDibaca(auth()->user()) : 0;
@endphp
<a href="{{ route('notifikasi.index') }}"
   class="relative ml-auto mr-3 grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/80 text-ink-700 shadow-[0_4px_20px_rgba(152,116,63,0.12)] ring-1 ring-sand-200/80 transition hover:bg-white"
   aria-label="Notifikasi{{ $belumDibaca ? ' ('.$belumDibaca.' belum dibaca)' : '' }}">
    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 9a6 6 0 1 1 12 0c0 4.5 1.5 6 2 7H4c.5-1 2-2.5 2-7Z" />
        <path d="M10 19a2 2 0 0 0 4 0" />
    </svg>
    @if ($belumDibaca > 0)
        <span class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-rose-600 px-1.5 text-center text-[11px] font-semibold leading-5 text-white" data-jumlah-notifikasi>{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
    @endif
</a>
