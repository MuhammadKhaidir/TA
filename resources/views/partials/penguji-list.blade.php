@php
    /** @var \App\Models\Sidang $sidang */
@endphp
@if ($sidang->pengujis->isEmpty())
    <p class="text-sm text-slate-400">Tim penguji belum ditetapkan.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($sidang->pengujis as $dosen)
            <li class="flex items-center justify-between gap-3 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $dosen->nama }}</p>
                    <p class="text-xs text-slate-400">{{ $dosen->pivot->peran->label() }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $dosen->pivot->konfirmasi->badgeClasses() }}">
                        {{ $dosen->pivot->konfirmasi->label() }}
                    </span>
                    @if (in_array($sidang->status, [\App\Enums\SidangStatus::PelaksanaanUjian, \App\Enums\SidangStatus::Selesai], true))
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $dosen->pivot->nilai_diinput,
                            'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20' => ! $dosen->pivot->nilai_diinput,
                        ])>
                            {{ $dosen->pivot->nilai_diinput ? 'Nilai sudah diinput' : 'Nilai belum diinput' }}
                        </span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
