{{--
    Kartu menu proses mahasiswa (accordion native, tanpa JS).
    Panel berisi tiga bagian:
      1. Syarat      - slot `syarat` (isi dengan <li>)
      2. Keterangan  - slot `keterangan`, opsional, untuk status pengajuan berjalan
                       (judul bagian ditulis sendiri di dalam slot)
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

<details {{ $attributes->merge(['class' => 'mhs-card group overflow-hidden']) }} @if($open) open @endif>
    <summary class="flex cursor-pointer list-none select-none items-center justify-between gap-4 px-6 py-5 transition-colors hover:bg-sand-100/50 [&::-webkit-details-marker]:hidden">
        <div class="flex min-w-0 items-center gap-4">
            <span class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sand-200 text-ink-800 sm:flex">
                <x-mhs.icon name="doc" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-ink-900 sm:text-lg">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-ink-600 sm:text-sm">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            @if ($badge)
                @include('partials.mhs-status-pill', ['status' => $badge])
            @endif
            <x-mhs.icon name="chevron-down" class="h-5 w-5 text-ink-500 transition-transform duration-200 group-open:rotate-180" />
        </div>
    </summary>

    <div class="border-t border-sand-200">
        @isset($syarat)
            <section class="px-6 pb-2 pt-5">
                <h4 class="text-sm font-semibold uppercase tracking-wider text-ink-600">Syarat</h4>
                <ul class="mt-2 divide-y divide-sand-200/80">
                    {{ $syarat }}
                </ul>
            </section>
        @endisset

        @isset($keterangan)
            <section class="px-6 pb-6 pt-3">
                {{ $keterangan }}
            </section>
        @endisset

        @if ($slot->isNotEmpty())
            <section class="border-t border-sand-200 px-6 py-6">
                <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-ink-600">{{ $aksiTitle }}</h4>
                {{ $slot }}
            </section>
        @endif
    </div>
</details>