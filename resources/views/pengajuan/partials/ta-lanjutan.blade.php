@php
    /**
     * Setelah SK terbit: kartu konsultasi (PDF) dan jalan menuju sidang.
     * Variabel: $pengajuan, $kartuAlasanKunci, $sidang, $bisaAjukanSidang.
     */
    $p = $pengajuan;
@endphp

<section class="mhs-card space-y-5 p-6" id="tahap-lanjutan">
    <div>
        <p class="mhs-eyebrow">Tahap berikutnya</p>
        <h3 class="text-base font-semibold text-ink-900">Kartu konsultasi dan sidang</h3>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
        <div>
            <p class="text-sm font-semibold text-ink-900">Kartu konsultasi (PDF)</p>
            @if ($kartuAlasanKunci)
                <p class="text-xs text-ink-600">{{ $kartuAlasanKunci }}</p>
            @else
                <p class="text-xs text-ink-600">Disusun otomatis dari catatan bimbingan TA.</p>
            @endif
        </div>
        @if ($kartuAlasanKunci)
            <span class="inline-flex items-center gap-1.5 rounded-xl border border-sand-300 bg-sand-100 px-4 py-2 text-sm font-medium text-ink-500" aria-disabled="true">Terkunci</span>
        @else
            <a href="{{ route('pengajuan.kartu.unduh', $p) }}" class="mhs-btn-secondary">Unduh kartu konsultasi</a>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sand-200 bg-white/60 px-4 py-3">
        <div>
            <p class="text-sm font-semibold text-ink-900">Sidang Tugas Akhir</p>
            @if ($sidang)
                <p class="text-xs text-ink-600">
                    Status: <span class="font-medium">{{ $sidang->status->label() }}</span>
                    @if ($sidang->jadwal_mulai) &middot; {{ $sidang->jadwal_mulai->translatedFormat('d M Y H:i') }}@endif
                </p>
            @elseif ($p->suratKeputusan)
                <p class="text-xs text-ink-600">SK sudah terbit. Pengajuan sidang dapat dibuat.</p>
            @else
                <p class="text-xs text-ink-600">Pengajuan sidang dapat dibuat setelah SK TA terbit.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($sidang)
                <a href="{{ route('sidang.show', $sidang) }}" class="mhs-btn-secondary">Buka sidang</a>
            @endif
            @if ($bisaAjukanSidang)
                <form method="POST" action="{{ route('mahasiswa.sidang.store', $p) }}">
                    @csrf
                    <button type="submit" class="mhs-btn-primary">{{ $sidang ? 'Ajukan sidang baru' : 'Ajukan sidang' }}</button>
                </form>
            @endif
        </div>
    </div>
</section>
