{{--
    Kartu menu proses (accordion native, tanpa JS).
    Panel berisi tiga bagian:
      1. Syarat      - slot `syarat` (isi dengan <li>)
      2. Keterangan  - slot `keterangan`, opsional, untuk status pengajuan berjalan
      3. Aksi        - slot default, tombol atau form pengajuan
    Badge status tetap tampil di ringkasan walau panel tertutup.
--}}
@props([
    'title',
    'subtitle' => null,
    'badge' => null, // instance \App\Enums\SidangStatus atau null
    'open' => false,
    'aksiTitle' => 'Formulir Pengajuan',
])

<details {{ $attributes->merge(['class' => 'card group overflow-hidden']) }} @if($open) open @endif>
    <summary class="flex cursor-pointer list-none select-none items-center justify-between gap-4 px-6 py-5 transition-colors hover:bg-slate-50/70 [&::-webkit-details-marker]:hidden">
        <div class="flex items-center gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1Zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd" />
                </svg>
            </span>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if ($badge)
                @include('partials.status-badge', ['status' => $badge])
            @endif
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                 class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200 group-open:rotate-180">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </div>
    </summary>

    <div class="divide-y divide-slate-100 border-t border-slate-100">
        @isset($syarat)
            <section class="px-6 py-5">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Syarat</h4>
                <ul class="space-y-4 text-sm text-slate-600">
                    {{ $syarat }}
                </ul>
            </section>
        @endisset

        @isset($keterangan)
            <section class="px-6 py-5">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Status Pengajuan</h4>
                {{ $keterangan }}
            </section>
        @endisset

        @if ($slot->isNotEmpty())
            <section class="px-6 py-5">
                <h4 class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $aksiTitle }}</h4>
                {{ $slot }}
            </section>
        @endif
    </div>
</details>