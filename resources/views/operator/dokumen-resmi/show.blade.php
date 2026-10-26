@extends('layouts.app')

@section('judul', 'Dokumen Resmi')

@section('konten')
    @php
        $pengguna = auth()->user();
        $bisaProses = $pengguna->can('prosesDokumenResmi', $pengajuan);
        $ta = $pengajuan->jenis === \App\Enums\JenisPengajuan::Ta;
        $suratTa = $pengajuan->tampilkanSuratTa();
        // Alasan SK pembimbing belum boleh terbit (null bila boleh). Hanya relevan untuk TA.
        $hambatanSk = $ta ? $pengajuan->hambatanSk() : null;
        $balasan = $pengajuan->balasanTerbaru();
        // Pada TA, surat pengantar diurus di kartu tersendiri dan tidak lewat formulir umum.
        $kecuali = $ta ? [\App\Enums\JenisPengajuan::SURAT_PENGANTAR_TA] : [];
    @endphp

    <x-page-header :judul="'Dokumen Resmi: '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.dokumen-resmi.index') }}" class="btn btn-secondary">Kembali</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Alur surat TA: ringkasan tiga langkah, lalu tindakan operator di setiap langkah --}}
            @include('partials.pengajuan.surat-ta')

            @can('terbitkanSuratPengantar', $pengajuan)
                <section class="card">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">Terbitkan Surat Pengantar TA</h2></div>
                    <form method="POST" action="{{ route('operator.dokumen-resmi.surat-pengantar', $pengajuan) }}" enctype="multipart/form-data" class="card-body grid gap-4 sm:grid-cols-2">
                        @csrf
                        <p class="text-sm text-slate-600 sm:col-span-2">
                            Unggah surat pengantar yang sudah ditandatangani (PDF). Setelah terbit, mahasiswa dapat mengunduhnya dan mengunggah surat balasan dari instansi.
                        </p>
                        <div>
                            <label for="nomor_pengantar" class="label">Nomor surat</label>
                            <input id="nomor_pengantar" name="nomor_pengantar" type="text" value="{{ old('nomor_pengantar') }}" required class="input">
                            @error('nomor_pengantar')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="tanggal_pengantar" class="label">Tanggal surat</label>
                            <input id="tanggal_pengantar" name="tanggal_pengantar" type="date" value="{{ old('tanggal_pengantar', now()->toDateString()) }}" required class="input">
                            @error('tanggal_pengantar')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="berkas_pengantar" class="label">Berkas (PDF, maks. 10 MB)</label>
                            <input id="berkas_pengantar" name="berkas_pengantar" type="file" accept=".pdf" required
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                            @error('berkas_pengantar')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" />Terbitkan surat pengantar</button>
                        </div>
                    </form>
                </section>
            @endcan

            @can('hapusSuratPengantar', $pengajuan)
                <section class="card">
                    <div class="card-body flex flex-wrap items-center justify-between gap-3">
                        <p class="max-w-xl text-sm text-slate-600">
                            Salah unggah atau salah nomor? Surat pengantar dapat ditarik selama mahasiswa belum mengunggah surat balasan.
                        </p>
                        <form method="POST" action="{{ route('operator.dokumen-resmi.destroy', [$pengajuan, $pengajuan->suratPengantarTa()]) }}" data-konfirmasi="Tarik surat pengantar ini? Anda dapat menerbitkan yang baru sesudahnya.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary">Tarik surat pengantar</button>
                        </form>
                    </div>
                </section>
            @endcan

            @can('periksaSuratBalasan', $pengajuan)
                <section class="card" id="periksa-balasan">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">Periksa Surat Balasan</h2></div>
                    <form method="POST" action="{{ route('operator.dokumen-resmi.surat-balasan', $pengajuan) }}" class="card-body space-y-4">
                        @csrf
                        <input type="hidden" name="balasan_id" value="{{ $balasan->id }}">

                        <p class="text-sm text-slate-600">
                            Versi {{ $balasan->versi }}, diunggah {{ $balasan->created_at->translatedFormat('d M Y H:i') }}:
                            <a href="{{ route('berkas.balasan', ['suratBalasan' => $balasan]) }}" target="_blank" rel="noopener" class="tautan">{{ $balasan->nama_asli }}</a>.
                            SK pembimbing baru dapat diterbitkan setelah surat balasan diterima.
                        </p>
                        @if ($balasan->status === \App\Enums\StatusDokumen::Diterima)
                            <p class="text-sm text-emerald-700">Surat balasan ini sudah diterima. Keputusan masih dapat diubah selama SK pembimbing belum terbit.</p>
                        @endif

                        <fieldset>
                            <legend class="label">Keputusan</legend>
                            <div class="flex flex-wrap gap-5 text-sm">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="keputusan" value="diterima" @checked(old('keputusan') === 'diterima') required class="h-4 w-4 accent-ink-800">
                                    Terima
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="keputusan" value="revisi" @checked(old('keputusan') === 'revisi') required class="h-4 w-4 accent-ink-800">
                                    Minta revisi
                                </label>
                            </div>
                            @error('keputusan')<p class="galat">{{ $message }}</p>@enderror
                        </fieldset>

                        <div>
                            <label for="catatan_balasan" class="label">Catatan untuk mahasiswa (wajib bila meminta revisi)</label>
                            <textarea id="catatan_balasan" name="catatan_balasan" rows="2" class="input">{{ old('catatan_balasan') }}</textarea>
                            @error('catatan_balasan')<p class="galat">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan keputusan</button>
                    </form>
                </section>
            @endcan

            @include('partials.pengajuan.dokumen-resmi', ['hapus' => $bisaProses, 'kecuali' => $kecuali, 'sembunyikanBilaKosong' => $ta && $suratTa])

            @if ($bisaProses)
                <section class="card">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">{{ $ta ? 'Terbitkan SK Pembimbing / Dokumen Lain' : 'Unggah Dokumen Resmi' }}</h2></div>
                    <form method="POST" action="{{ route('operator.dokumen-resmi.store', $pengajuan) }}" enctype="multipart/form-data" class="card-body grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="jenis" class="label">Jenis dokumen</label>
                            <select id="jenis" name="jenis" required class="input">
                                @foreach ($pengajuan->jenis->jenisDokumenResmi() as $kode => $label)
                                    @continue(in_array($kode, $kecuali, true))
                                    @php($terkunci = $kode === \App\Enums\JenisPengajuan::SK_TA && $hambatanSk !== null)
                                    <option value="{{ $kode }}" @disabled($terkunci) @selected(old('jenis') === $kode)>{{ $label }}{{ $terkunci ? ' (belum dapat diterbitkan)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('jenis')<p class="galat">{{ $message }}</p>@enderror
                            @if ($hambatanSk !== null)
                                <p class="hint">SK pembimbing belum dapat diterbitkan. {{ $hambatanSk }}</p>
                            @endif
                        </div>
                        <div>
                            <label for="nomor" class="label">Nomor dokumen</label>
                            <input id="nomor" name="nomor" type="text" value="{{ old('nomor') }}" required class="input">
                            @error('nomor')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="tanggal" class="label">Tanggal dokumen</label>
                            <input id="tanggal" name="tanggal" type="date" value="{{ old('tanggal', now()->toDateString()) }}" required class="input">
                            @error('tanggal')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="berkas" class="label">Berkas (PDF, maks. 10 MB)</label>
                            <input id="berkas" name="berkas" type="file" accept=".pdf" required
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                            @error('berkas')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" />Unggah dokumen resmi</button>
                        </div>
                    </form>
                </section>

                @php($belumBisaArsip = $pengajuan->dokumenResmi->isEmpty() || ($ta && $pengajuan->skPembimbing() === null))
                <section class="card">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">Arsipkan Berkas Final</h2></div>
                    <form method="POST" action="{{ route('operator.dokumen-resmi.arsipkan', $pengajuan) }}" class="card-body space-y-4"
                          data-konfirmasi="Arsipkan berkas final? Status pengajuan akan menjadi selesai dan tidak dapat diubah lagi.">
                        @csrf
                        <p class="text-sm text-slate-600">
                            Setelah semua dokumen resmi diunggah, pindahkan pengajuan ke arsip digital. Mahasiswa dan dosen pembimbing tetap dapat mengakses berkasnya.
                        </p>
                        <div>
                            <label for="catatan" class="label">Catatan arsip (opsional)</label>
                            <textarea id="catatan" name="catatan" rows="2" class="input">{{ old('catatan') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" @disabled($belumBisaArsip)>
                            <x-icon name="archive" class="h-4 w-4" />Arsipkan
                        </button>
                        @if ($ta && $pengajuan->skPembimbing() === null)
                            <p class="hint">Terbitkan SK Pembimbing Tugas Akhir terlebih dahulu.</p>
                        @elseif ($pengajuan->dokumenResmi->isEmpty())
                            <p class="hint">Unggah minimal satu dokumen resmi terlebih dahulu.</p>
                        @endif
                    </form>
                </section>
            @elseif ($pengajuan->status === \App\Enums\StatusPengajuan::Diarsipkan)
                <div class="alert alert-info">
                    Pengajuan ini sudah diarsipkan. <a href="{{ route('operator.arsip.show', $pengajuan) }}" class="underline">Buka di arsip</a>.
                </div>
            @elseif ($ta && $suratTa)
                <div class="alert alert-info">
                    SK pembimbing dan arsip baru dapat diproses setelah pengajuan disetujui dosen (status saat ini: {{ $pengajuan->status->label() }}).
                    Surat pengantar dan surat balasan sudah bisa dikerjakan sekarang.
                </div>
            @else
                <div class="alert alert-info">
                    Dokumen resmi baru dapat diproses setelah pengajuan disetujui dosen (status saat ini: {{ $pengajuan->status->label() }}).
                </div>
            @endif

            @include('partials.pengajuan.dokumen')
        </div>

        <div class="space-y-6">
            @include('partials.pengajuan.ringkasan')
            @include('partials.pengajuan.riwayat')
        </div>
    </div>
@endsection
