{{-- Isi modal "Lihat data" surat pengantar di panel Admin: isian + dokumen Kumpulan A (unduhan lewat route terotorisasi). --}}
@php
    $mhs = $surat->pengajuan->mahasiswa;
    $dokumens = $surat->pengajuan->dokumens->filter(fn ($d) => $d->persyaratan?->jenis === \App\Enums\JenisPersyaratan::TaSuratPengantar);
@endphp
<div class="space-y-4 text-sm">
    <p><span class="font-semibold">{{ $mhs->nama }}</span> ({{ $mhs->nim }})</p>
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach (\App\Models\SuratPengantar::daftarIsian() as $kunci => [$label])
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                <dd class="mt-0.5 whitespace-pre-line">{{ $surat->data[$kunci] ?? '-' }}</dd>
            </div>
        @endforeach
    </dl>
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dokumen</p>
        <ul class="mt-1 list-inside list-disc">
            @forelse ($dokumens as $d)
                <li>{{ $d->persyaratan->nama }}: <a class="underline" target="_blank" rel="noopener" href="{{ route('pengajuan.dokumen.unduh', $d) }}">{{ $d->nama_file_asli ?? 'Unduh' }}</a></li>
            @empty
                <li>Belum ada dokumen.</li>
            @endforelse
        </ul>
    </div>
    @if ($surat->catatan_operator)
        <p><span class="font-semibold">Catatan pemeriksa:</span> {{ $surat->catatan_operator }}</p>
    @endif
</div>
