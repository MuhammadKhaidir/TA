{{-- Membutuhkan: $pengajuan (relasi riwayat.user sudah dimuat) --}}
<section class="card">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Riwayat Status</h2>
    </div>
    <ol class="px-5 py-4">
        @foreach ($pengajuan->riwayat->reverse() as $r)
            <li class="relative border-l-2 border-slate-200 pb-5 pl-5 last:pb-0">
                <span class="absolute -left-[7px] top-1 h-3 w-3 rounded-full border-2 border-white bg-navy-600"></span>
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$r->status_baru" />
                    <span class="text-xs text-slate-500">{{ $r->created_at->translatedFormat('d M Y H:i') }}</span>
                </div>
                <div class="mt-1 text-sm text-slate-600">
                    {{ $r->user ? $r->user->name.' ('.$r->user->role->label().')' : 'Sistem' }}
                </div>
                @if (filled($r->catatan))
                    <div class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $r->catatan }}</div>
                @endif
            </li>
        @endforeach
    </ol>
</section>
