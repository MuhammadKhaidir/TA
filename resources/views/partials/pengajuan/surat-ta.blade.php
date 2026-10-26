{{--
    Alur surat Tugas Akhir dalam tiga langkah: surat pengantar -> surat balasan -> SK pembimbing.
    Membutuhkan: $pengajuan (relasi dokumenResmi dan suratBalasan sudah dimuat)
    Dipakai halaman mahasiswa (ada formulir unggah surat balasan bila berhak) dan halaman operator (hanya status).
    Tidak menampilkan apa pun untuk pengajuan yang bukan TA atau yang belum lolos verifikasi operator.
--}}
@php
    $tahap = $pengajuan->tahapSurat();
    $bolehUnggah = $tahap !== [] && auth()->user()->can('unggahSuratBalasan', $pengajuan);
    $balasanTerbaru = $pengajuan->balasanTerbaru();
    $balasanLama = $pengajuan->suratBalasan->where('terbaru', false)->sortByDesc('versi');
@endphp

@if ($tahap !== [])
    <section class="card" id="surat-ta">
        <div class="card-header">
            <div>
                <h2 class="font-semibold text-slate-900">Surat Tugas Akhir</h2>
                <p class="mt-0.5 text-sm text-slate-500">Surat pengantar, lalu surat balasan, lalu SK pembimbing.</p>
            </div>
        </div>

        <ol class="grid divide-y divide-slate-200 md:grid-cols-3 md:divide-x md:divide-y-0">
            @foreach ($tahap as $i => $t)
                <li class="flex flex-col gap-3 p-5" data-tahap="{{ $t['kunci'] }}" data-status="{{ $t['status']->value }}">
                    <div class="flex items-center gap-3">
                        <span class="ikon-gelembung h-9 w-9 text-sm font-semibold {{ $t['status'] === \App\Enums\StatusTahapSurat::Terkunci ? 'opacity-60' : '' }}">
                            @if ($t['status'] === \App\Enums\StatusTahapSurat::Selesai)
                                <x-pasir.icon name="check" class="h-5 w-5" />
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-medium text-slate-900">{{ $t['judul'] }}</h3>
                            <span class="badge mt-1 {{ $t['status']->badge() }}">{{ $t['label'] }}</span>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600">{{ $t['keterangan'] }}</p>

                    @if ($t['dokumen'])
                        <div class="mt-auto text-xs text-slate-500">{{ $t['dokumen']->nama_asli }} ({{ $t['dokumen']->ukuranManusia() }})</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('berkas.resmi', ['dokumenResmi' => $t['dokumen'], 'unduh' => 1]) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" />Unduh</a>
                            <a href="{{ route('berkas.resmi', ['dokumenResmi' => $t['dokumen']]) }}" target="_blank" rel="noopener" class="tautan text-sm">Lihat</a>
                        </div>
                    @elseif ($t['balasan'])
                        <div class="mt-auto text-xs text-slate-500">
                            {{ $t['balasan']->nama_asli }} ({{ $t['balasan']->ukuranManusia() }}), diunggah {{ $t['balasan']->created_at->translatedFormat('d M Y H:i') }}
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('berkas.balasan', ['suratBalasan' => $t['balasan'], 'unduh' => 1]) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" />Unduh</a>
                            <a href="{{ route('berkas.balasan', ['suratBalasan' => $t['balasan']]) }}" target="_blank" rel="noopener" class="tautan text-sm">Lihat</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($bolehUnggah)
            <form method="POST" action="{{ route('mahasiswa.surat-balasan.store', $pengajuan) }}" enctype="multipart/form-data" class="space-y-4 border-t border-slate-200 p-5">
                @csrf
                @if ($balasanTerbaru?->status === \App\Enums\StatusDokumen::Revisi)
                    <div class="alert alert-peringatan !mb-0">
                        <p class="font-medium">Surat balasan perlu direvisi.</p>
                        <p class="mt-0.5">{{ $balasanTerbaru->catatan ?: 'Unggah ulang surat balasan yang sudah diperbaiki.' }}</p>
                    </div>
                @endif

                <div>
                    <label for="berkas_balasan" class="label">
                        {{ $balasanTerbaru ? 'Unggah ulang surat balasan' : 'Unggah surat balasan' }} (PDF, JPG, atau PNG, maks. 5 MB)
                    </label>
                    <input id="berkas_balasan" name="berkas_balasan" type="file" accept=".pdf,.jpg,.jpeg,.png" required
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                    @error('berkas_balasan')<p class="galat">{{ $message }}</p>@enderror
                    @if ($balasanTerbaru?->status === \App\Enums\StatusDokumen::Menunggu)
                        <p class="hint">Surat balasan Anda sedang menunggu pemeriksaan. Mengunggah lagi akan menggantikannya.</p>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" />Unggah surat balasan</button>
            </form>
        @endif

        @if ($balasanLama->isNotEmpty())
            <details class="border-t border-slate-200 px-5 py-3 text-sm">
                <summary class="cursor-pointer font-medium text-slate-700">Versi surat balasan sebelumnya ({{ $balasanLama->count() }})</summary>
                <ul class="mt-3 space-y-2">
                    @foreach ($balasanLama as $lama)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="min-w-0 text-slate-600">
                                Versi {{ $lama->versi }}: {{ $lama->nama_asli }}
                                <span class="badge ml-1 {{ $lama->status->badge() }}">{{ $lama->status->label() }}</span>
                                @if ($lama->catatan)<span class="block text-xs text-slate-500">Catatan: {{ $lama->catatan }}</span>@endif
                            </span>
                            <a href="{{ route('berkas.balasan', ['suratBalasan' => $lama]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
@endif
