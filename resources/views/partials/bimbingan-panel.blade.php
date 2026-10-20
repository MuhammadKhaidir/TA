@php
    /**
     * @var \App\Enums\JenisPengajuan $jenis
     * @var \Illuminate\Support\Collection $daftar
     * @var array{tercatat:int,divalidasi:int,minimal:?int} $ringkasan
     * @var bool $bisaCatat
     */
    $minimal = $ringkasan['minimal'];
    $persen = $minimal ? min(100, (int) round($ringkasan['divalidasi'] / $minimal * 100)) : null;
@endphp
<div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
        <p class="mhs-eyebrow">Tercatat</p>
        <p class="mt-1 text-2xl font-semibold text-ink-900">{{ $ringkasan['tercatat'] }}</p>
    </div>
    <div class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
        <p class="mhs-eyebrow">Divalidasi dosen</p>
        <p class="mt-1 text-2xl font-semibold text-ink-900">{{ $ringkasan['divalidasi'] }}</p>
    </div>
    <div class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
        <p class="mhs-eyebrow">Minimum</p>
        <p class="mt-1 text-2xl font-semibold text-ink-900">{{ $minimal ?? 'Belum ditetapkan' }}</p>
        @if ($persen !== null)
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-sand-200"><div class="h-full rounded-full bg-sand-500" style="width: {{ $persen }}%"></div></div>
        @endif
    </div>
</div>

@if ($daftar->isNotEmpty())
    <ul class="mb-5 max-h-96 divide-y divide-sand-200/80 overflow-y-auto rounded-xl border border-sand-200 px-4">
        @foreach ($daftar as $b)
            <li class="py-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink-900">{{ $b->tanggal->translatedFormat('d M Y') }} &middot; {{ $b->topik ?? 'Bimbingan' }}</p>
                        @if ($b->catatan)<p class="text-xs text-ink-600">{{ $b->catatan }}</p>@endif
                        @if ($b->tindak_lanjut)<p class="text-xs text-ink-500">Tindak lanjut: {{ $b->tindak_lanjut }}</p>@endif
                        @if ($b->catatan_dospem)<p class="mt-1 rounded-lg bg-sand-100 px-2.5 py-1.5 text-xs text-ink-800">Dosen: {{ $b->catatan_dospem }}</p>@endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($b->bukti_path)
                            <a href="{{ route('bimbingan.bukti', $b) }}" target="_blank" rel="noopener" class="text-xs font-medium text-ink-800 underline">Bukti</a>
                        @endif
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $b->status_validasi->badgeClasses() }}">{{ $b->status_validasi->label() }}</span>
                        @if ($b->status_validasi !== \App\Enums\StatusBimbingan::Divalidasi)
                            <form method="POST" action="{{ route('mahasiswa.bimbingan.destroy', $b) }}" onsubmit="return confirm('Hapus catatan bimbingan ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-rose-600 hover:underline">Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@else
    <p class="mb-5 rounded-xl border border-dashed border-sand-300 px-4 py-5 text-sm text-ink-500">Belum ada bimbingan yang dicatat.</p>
@endif

@if ($bisaCatat)
    <form method="POST" action="{{ route('mahasiswa.bimbingan.store', $jenis) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mhs-label">Tanggal bimbingan</label>
                <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="mhs-input" required>
            </div>
            <div>
                <label class="mhs-label">Topik yang dibahas</label>
                <input type="text" name="topik" value="{{ old('topik') }}" maxlength="255" class="mhs-input" required>
            </div>
            <div>
                <label class="mhs-label">Catatan hasil bimbingan (opsional)</label>
                <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input">{{ old('catatan') }}</textarea>
            </div>
            <div>
                <label class="mhs-label">Tindak lanjut / revisi (opsional)</label>
                <textarea name="tindak_lanjut" rows="2" maxlength="1000" class="mhs-input">{{ old('tindak_lanjut') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="mhs-label">Foto bukti (opsional)</label>
                <input type="file" name="bukti" accept="image/*"
                       class="mhs-input text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink-800 hover:file:bg-sand-300">
            </div>
        </div>
        <div class="flex justify-end border-t border-sand-200 pt-4">
            <button type="submit" class="mhs-btn-primary">Catat Bimbingan</button>
        </div>
    </form>
@else
    <p class="text-sm text-ink-600">Bimbingan dapat dicatat setelah pengajuan dikirim dan dosen pembimbing ditetapkan oleh Operator Akademik.</p>
@endif
