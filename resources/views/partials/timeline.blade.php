@php
    /** @var \App\Models\Sidang $sidang */
    /** @var array<int,string> $langkahMaster */
    $selesai = $sidang->status->langkahSelesai();
    $aktif = $sidang->status->langkahAktif();
    $riwayatPerLangkah = $sidang->riwayats->groupBy('langkah_ke');
@endphp
<ol class="relative border-l border-slate-200 pl-6">
    @foreach ($langkahMaster as $nomor => $judul)
        @php
            $entries = $riwayatPerLangkah->get($nomor, collect());
            $isDone = $nomor <= $selesai;
            $isActive = $aktif === $nomor;
        @endphp
        <li class="mb-7 last:mb-0">
            <span @class([
                'absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-slate-50',
                'bg-emerald-500' => $isDone,
                'bg-gold-500' => $isActive && ! $isDone,
                'bg-slate-300' => ! $isDone && ! $isActive,
            ])></span>
            <div class="flex flex-wrap items-center gap-2">
                <p @class([
                    'text-sm font-semibold',
                    'text-slate-900' => $isDone || $isActive,
                    'text-slate-400' => ! $isDone && ! $isActive,
                ])>Langkah {{ $nomor }}</p>
                @if ($isActive && ! $isDone)
                    <span class="rounded-full bg-gold-50 px-2 py-0.5 text-[11px] font-medium text-gold-700 ring-1 ring-inset ring-gold-500/30">
                        Sedang berjalan &middot; {{ $sidang->status->labelPerananBerikutnya() }}
                    </span>
                @endif
            </div>
            <p @class([
                'mt-0.5 text-sm',
                'text-slate-600' => $isDone || $isActive,
                'text-slate-400' => ! $isDone && ! $isActive,
            ])>{{ $judul }}</p>

            @if ($entries->isNotEmpty())
                <div class="mt-2 space-y-2">
                    @foreach ($entries as $entry)
                        <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-slate-700">{{ $entry->judul }}</span>
                                <span class="whitespace-nowrap text-slate-400">{{ $entry->created_at->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                            @if ($entry->keterangan)
                                <p class="mt-1 text-slate-500">{{ $entry->keterangan }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </li>
    @endforeach
</ol>
