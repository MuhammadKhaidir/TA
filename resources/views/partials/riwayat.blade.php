@php
    /** @var \Illuminate\Support\Collection $riwayats  log aktivitas milik satu pengajuan */
    $catatan = ['revisi_administrasi' => 'Catatan Operator Akademik', 'revisi_akademik' => 'Catatan Dosen Pembimbing'];
@endphp
<ol class="space-y-3">
    @forelse ($riwayats->reverse() as $r)
        <li class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-ink-900">{{ \Illuminate\Support\Str::of($r->aksi)->replace('_', ' ')->ucfirst() }}</p>
                <p class="text-xs text-ink-500">{{ $r->created_at->translatedFormat('d M Y H:i') }}</p>
            </div>
            <p class="mt-0.5 text-xs text-ink-500">
                {{ $r->user?->name ?? 'Sistem' }}@if ($r->peran) &middot; {{ \App\Enums\Peran::tryFrom($r->peran)?->label() ?? $r->peran }}@endif
            </p>
            @if ($r->keterangan)
                <p class="mt-1.5 text-sm {{ isset($catatan[$r->aksi]) ? 'rounded-lg bg-rose-50 px-3 py-2 text-rose-800' : 'text-ink-700' }}">
                    @isset($catatan[$r->aksi])<span class="block text-xs font-semibold uppercase tracking-wide">{{ $catatan[$r->aksi] }}</span>@endisset
                    {{ $r->keterangan }}
                </p>
            @endif
        </li>
    @empty
        <li class="text-sm text-ink-500">Belum ada riwayat.</li>
    @endforelse
</ol>
