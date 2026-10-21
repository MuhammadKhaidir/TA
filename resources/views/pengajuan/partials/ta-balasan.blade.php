@php
    /**
     * Tahap 2 pengajuan TA: surat balasan instansi + validasi checklist Operator.
     * Variabel: $pengajuan, $sp, $sb, $checklistSb, $milik, $bisaValidasiSb, $tombolKecil.
     */
    use App\Enums\StatusSuratBalasan;
    use App\Enums\StatusSuratPengantar;

    $p = $pengajuan;
    $spSelesai = $sp?->status === StatusSuratPengantar::Selesai;
    $bisaUnggah = $milik && $spSelesai && $sb?->status !== StatusSuratBalasan::Valid && ! $p->status->berhenti();
@endphp

<section class="mhs-card space-y-4 p-6" id="tahap-balasan">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="mhs-eyebrow">Tahap 2</p>
            <h3 class="text-base font-semibold text-ink-900">Surat balasan instansi</h3>
            <p class="mt-1 text-xs text-ink-600">Unggah surat balasan dari instansi tujuan. Pengajuan SK baru dapat dikirim setelah surat balasan dinyatakan valid.</p>
        </div>
        @if ($sb)
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $sb->status->badgeClasses() }}">{{ $sb->status->label() }}</span>
        @endif
    </div>

    @if (! $spSelesai && ! $sb)
        <p class="text-sm text-ink-600">Surat balasan dapat diunggah setelah surat pengantar selesai dan diambil.</p>
    @endif

    @if ($sb)
        <p class="text-sm text-ink-700">
            Berkas:
            <a class="font-medium text-ink-900 underline" href="{{ route('balasan.unduh', $p) }}">{{ $sb->nama_file_asli ?? 'Unduh surat balasan' }}</a>
            &middot; diunggah {{ $sb->updated_at->translatedFormat('d M Y H:i') }}
        </p>

        @if ($sb->status === StatusSuratBalasan::TidakValid && $sb->catatan_operator)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="text-xs font-semibold uppercase tracking-wide">Catatan Operator</p>
                <p class="mt-1">{{ $sb->catatan_operator }}</p>
            </div>
        @endif

        @if ($sb->checklist && $sb->status !== StatusSuratBalasan::Menunggu)
            <ul class="space-y-1 text-sm">
                @foreach ($checklistSb as $kunci => $label)
                    <li class="flex items-start gap-2">
                        <x-mhs.icon :name="($sb->checklist[$kunci] ?? false) ? 'check-circle' : 'x-circle'" class="mt-0.5 h-5 w-5 shrink-0 {{ ($sb->checklist[$kunci] ?? false) ? 'text-emerald-600' : 'text-rose-500' }}" />
                        <span class="text-ink-700">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    @if ($bisaUnggah)
        <form method="POST" action="{{ route('mahasiswa.balasan.unggah', $p) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 border-t border-sand-200 pt-4">
            @csrf
            <input type="file" name="berkas" required accept=".pdf,.jpg,.jpeg,.png"
                   class="mhs-input max-w-xs text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink-800 hover:file:bg-sand-300">
            <button type="submit" class="mhs-btn-primary">{{ $sb ? 'Unggah ulang surat balasan' : 'Unggah surat balasan' }}</button>
        </form>
    @endif

    {{-- Validasi checklist oleh pemegang tugas --}}
    @if ($bisaValidasiSb && $sb->status === StatusSuratBalasan::Menunggu)
        <form method="POST" action="{{ route('operator.balasan.validasi', $sb) }}" class="space-y-3 border-t border-sand-200 pt-4">
            @csrf
            <p class="mhs-eyebrow">Checklist validasi</p>
            @foreach ($checklistSb as $kunci => $label)
                <label class="flex items-start gap-2 text-sm text-ink-800">
                    <input type="hidden" name="checklist[{{ $kunci }}]" value="0">
                    <input type="checkbox" name="checklist[{{ $kunci }}]" value="1" class="mt-0.5 rounded border-sand-400">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            <div>
                <label class="mhs-label" for="catatan-sb">Catatan (wajib bila ada butir yang belum terpenuhi)</label>
                <textarea id="catatan-sb" name="catatan" rows="2" maxlength="2000" class="mhs-input"></textarea>
            </div>
            <button type="submit" class="mhs-btn-primary">Simpan hasil validasi</button>
        </form>
    @endif
</section>
