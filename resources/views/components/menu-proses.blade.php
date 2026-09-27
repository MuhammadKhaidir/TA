{{--
    Kartu menu proses yang bisa diklik (accordion, tanpa JS — pakai <details> native).

    Saat ringkasan (summary) diklik, panel di bawahnya terbuka menampilkan:
      1. Syarat      — slot bernama `syarat` (isi dengan <li> ... </li>)
      2. Keterangan  — slot bernama `keterangan`, opsional. Untuk menampilkan
                        status pengajuan yang sudah berjalan/diproses.
      3. Aksi        — slot default ($slot). Tombol/aksi atau form pengajuan.

    Badge status (bila ada) tetap terlihat di ringkasan walau panel tertutup,
    supaya mahasiswa langsung tahu status tanpa perlu membuka panel.

    Pola ini sengaja dibuat generik agar mudah dipakai lagi untuk menu-menu
    proses lain (mis. pengajuan surat lain) di masa depan:

        <x-menu-proses title="..." subtitle="..." :badge="$status">
            <x-slot:syarat> <li>...</li> </x-slot:syarat>
            <x-slot:keterangan> ... </x-slot:keterangan>
            ...aksi/form...
        </x-menu-proses>
--}}
@props([
    'title',
    'subtitle' => null,
    'badge' => null, // instance \App\Enums\SidangStatus atau null
    'open' => false,
])

<details {{ $attributes->merge(['class' => 'card group']) }} @if($open) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5 select-none">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
            @if ($subtitle)
                <p class="text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
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

    <div class="space-y-5 border-t border-slate-100 p-5">
        @isset($syarat)
            <div>
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Syarat</h4>
                <ul class="space-y-1.5 text-sm text-slate-600">
                    {{ $syarat }}
                </ul>
            </div>
        @endisset

        @isset($keterangan)
            <div>
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Status Pengajuan</h4>
                {{ $keterangan }}
            </div>
        @endisset

        @if ($slot->isNotEmpty())
            <div>
                {{ $slot }}
            </div>
        @endif
    </div>
</details>