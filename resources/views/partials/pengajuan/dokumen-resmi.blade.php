{{--
    Membutuhkan: $pengajuan (relasi dokumenResmi sudah dimuat)
    Opsional: $hapus (true bila operator boleh menghapus dokumen resmi)
              $kecuali (kode jenis dokumen yang tidak ditampilkan di sini karena sudah tampil di kartu lain, mis. surat TA)
              $sembunyikanBilaKosong (true: kartu tidak ditampilkan sama sekali bila tidak ada dokumen untuk ditampilkan)
--}}
@php
    $hapus = $hapus ?? false;
    $daftarResmi = $pengajuan->dokumenResmi->reject(fn ($resmi) => in_array($resmi->jenis, $kecuali ?? [], true));
@endphp

@if ($daftarResmi->isNotEmpty() || ! ($sembunyikanBilaKosong ?? false))
<section class="card">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Dokumen Resmi</h2>
    </div>

    @if ($daftarResmi->isEmpty())
        <x-empty pesan="Belum ada dokumen resmi yang diterbitkan." />
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($daftarResmi as $resmi)
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-900">{{ $resmi->jenisLabel() }}</div>
                        <div class="text-sm text-slate-600">
                            Nomor {{ $resmi->nomor }} &middot; {{ $resmi->tanggal->translatedFormat('d M Y') }}
                        </div>
                        <div class="text-xs text-slate-500">{{ $resmi->nama_asli }} ({{ $resmi->ukuranManusia() }})</div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('berkas.resmi', ['dokumenResmi' => $resmi]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                        <a href="{{ route('berkas.resmi', ['dokumenResmi' => $resmi, 'unduh' => 1]) }}" class="tautan">Unduh</a>
                        @if ($hapus)
                            <form method="POST" action="{{ route('operator.dokumen-resmi.destroy', [$pengajuan, $resmi]) }}" data-konfirmasi="Hapus dokumen resmi ini?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endif
