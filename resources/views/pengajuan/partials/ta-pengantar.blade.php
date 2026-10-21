@php
    /**
     * Tahap 1 pengajuan TA: surat pengantar (6 isian + dokumen Kumpulan A).
     * Variabel: $pengajuan, $sp, $isianSp, $persyaratanPengantar, $bisaMulaiSp, $bisaUbahSp,
     *           $bisaPeriksaSp, $bisaProsesSp, $tombolKecil.
     */
    $p = $pengajuan;
    $bisaIsi = $bisaMulaiSp || $bisaUbahSp;
    $nilaiIsian = fn (string $kunci) => old($kunci, $sp?->data[$kunci] ?? '');
@endphp

<section class="mhs-card space-y-5 p-6" id="tahap-pengantar">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="mhs-eyebrow">Tahap 1</p>
            <h3 class="text-base font-semibold text-ink-900">Surat pengantar TA</h3>
            <p class="mt-1 text-xs text-ink-600">Isi data dan unggah dokumen persyaratan. Setelah lengkap, surat diperiksa lalu diproses secara offline oleh pihak fakultas.</p>
        </div>
        @if ($sp)
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $sp->status->badgeClasses() }}">{{ $sp->status->label() }}</span>
        @else
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">Belum dibuat</span>
        @endif
    </div>

    @if ($sp?->status === \App\Enums\StatusSuratPengantar::PerluRevisi && $sp->catatan_operator)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="text-xs font-semibold uppercase tracking-wide">Catatan perbaikan</p>
            <p class="mt-1">{{ $sp->catatan_operator }}</p>
        </div>
    @endif

    @if ($sp?->nomor_surat)
        <p class="text-sm text-ink-700">Nomor surat: <span class="font-semibold">{{ $sp->nomor_surat }}</span></p>
    @endif

    {{-- Data surat pengantar --}}
    @if ($bisaIsi)
        <form method="POST" action="{{ route('mahasiswa.pengantar.simpan', $p) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            @foreach ($isianSp as $kunci => [$label, $jenis, $wajib])
                <div class="{{ $jenis === 'textarea' ? 'sm:col-span-2' : '' }}">
                    <label class="mhs-label" for="sp-{{ $kunci }}">{{ $label }}@if ($wajib) <span class="text-rose-600">*</span>@endif</label>
                    @if ($jenis === 'textarea')
                        <textarea id="sp-{{ $kunci }}" name="{{ $kunci }}" rows="2" maxlength="2000" class="mhs-input">{{ $nilaiIsian($kunci) }}</textarea>
                    @else
                        <input id="sp-{{ $kunci }}" type="{{ $jenis === 'date' ? 'date' : 'text' }}" name="{{ $kunci }}" value="{{ $nilaiIsian($kunci) }}" maxlength="255" class="mhs-input">
                    @endif
                    @error($kunci)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div class="flex justify-end sm:col-span-2">
                <button type="submit" class="mhs-btn-secondary">{{ $sp ? 'Simpan data' : 'Simpan dan mulai' }}</button>
            </div>
        </form>
    @elseif ($sp)
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            @foreach ($isianSp as $kunci => [$label])
                <div><dt class="mhs-eyebrow">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line text-ink-900">{{ $sp->data[$kunci] ?? '-' }}</dd></div>
            @endforeach
        </dl>
    @endif

    {{-- Dokumen Kumpulan A --}}
    @if ($sp)
        <div class="border-t border-sand-200 pt-4">
            <p class="mhs-eyebrow">Dokumen persyaratan surat pengantar</p>
            @include('partials.dokumen-persyaratan', ['pengajuan' => $p, 'persyaratan' => $persyaratanPengantar, 'bisaUnggah' => $bisaUbahSp])
        </div>
    @endif

    {{-- Mahasiswa: kirim --}}
    @if ($bisaUbahSp)
        <div class="flex justify-end border-t border-sand-200 pt-4">
            <form method="POST" action="{{ route('mahasiswa.pengantar.kirim', $p) }}">
                @csrf
                <button type="submit" class="mhs-btn-primary">{{ $sp->diajukan_pada ? 'Kirim ulang surat pengantar' : 'Kirim surat pengantar' }}</button>
            </form>
        </div>
    @endif

    {{-- Pemeriksa kelengkapan --}}
    @if ($bisaPeriksaSp && in_array($sp->status, [\App\Enums\StatusSuratPengantar::Diajukan, \App\Enums\StatusSuratPengantar::Lengkap], true))
        <div class="grid grid-cols-1 gap-6 border-t border-sand-200 pt-4 lg:grid-cols-2">
            @if ($sp->status === \App\Enums\StatusSuratPengantar::Diajukan)
                <form method="POST" action="{{ route('operator.pengantar.periksa', $sp) }}" class="space-y-2">
                    @csrf
                    <p class="text-sm text-ink-600">Data dan dokumen sudah diperiksa dan lengkap.</p>
                    <button type="submit" class="mhs-btn-primary">Nyatakan lengkap</button>
                </form>
            @endif
            <form method="POST" action="{{ route('operator.pengantar.kembalikan', $sp) }}" class="space-y-2">
                @csrf
                <label class="mhs-label">Kembalikan ke mahasiswa untuk diperbaiki</label>
                <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Jelaskan bagian yang perlu diperbaiki" required></textarea>
                <button type="submit" class="{{ $tombolKecil }}">Kembalikan dengan catatan</button>
            </form>
        </div>
    @endif

    {{-- Pemroses offline (bawaan: Admin; di web hanya bila tugas ini dialihkan ke pengguna yang membuka halaman) --}}
    @if ($bisaProsesSp && in_array($sp->status, [\App\Enums\StatusSuratPengantar::Lengkap, \App\Enums\StatusSuratPengantar::Diproses], true))
        <div class="border-t border-sand-200 pt-4">
            @if ($sp->status === \App\Enums\StatusSuratPengantar::Lengkap)
                <form method="POST" action="{{ route('operator.pengantar.proses', $sp) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="mhs-label" for="nomor-sp">Nomor surat (opsional)</label>
                        <input id="nomor-sp" type="text" name="nomor_surat" maxlength="255" class="mhs-input">
                    </div>
                    <button type="submit" class="mhs-btn-primary">Mulai proses offline</button>
                </form>
            @else
                <form method="POST" action="{{ route('operator.pengantar.selesai', $sp) }}">
                    @csrf
                    <button type="submit" class="mhs-btn-primary">Surat selesai dan sudah diserahkan</button>
                </form>
            @endif
        </div>
    @endif
</section>
