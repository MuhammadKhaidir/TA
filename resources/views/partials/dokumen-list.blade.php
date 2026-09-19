@php
    /** @var \Illuminate\Support\Collection $dokumens */
    /** @var \App\Models\Sidang $sidang */
@endphp
@if ($dokumens->isEmpty())
    <p class="text-sm text-slate-400">Belum ada dokumen yang diunggah.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($dokumens as $dok)
            <li class="flex items-center justify-between gap-3 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $dok->jenis_dokumen->label() }}</p>
                    <p class="truncate text-xs text-slate-400">
                        {{ $dok->nama_file_asli ?? basename($dok->file_path) }}
                        @if ($dok->nomor_sk) &middot; No. {{ $dok->nomor_sk }} @endif
                        &middot; {{ $dok->created_at->translatedFormat('d M Y') }}
                    </p>
                </div>
                @if ($dok->file_path)
                    <a href="{{ route('sidang.dokumen.unduh', [$sidang, $dok]) }}"
                       class="btn-secondary shrink-0 !px-2.5 !py-1 text-xs">Unduh</a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
