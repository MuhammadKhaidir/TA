@extends('layouts.app')

@section('judul', 'Dokumen Resmi')

@section('konten')
    @php($bisaProses = auth()->user()->can('prosesDokumenResmi', $pengajuan))

    <x-page-header :judul="'Dokumen Resmi: '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.dokumen-resmi.index') }}" class="btn btn-secondary">Kembali</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @include('partials.pengajuan.dokumen-resmi', ['hapus' => $bisaProses])

            @if ($bisaProses)
                <section class="card">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">Unggah Dokumen Resmi</h2></div>
                    <form method="POST" action="{{ route('operator.dokumen-resmi.store', $pengajuan) }}" enctype="multipart/form-data" class="card-body grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="jenis" class="label">Jenis dokumen</label>
                            <select id="jenis" name="jenis" required class="input">
                                @foreach ($pengajuan->jenis->jenisDokumenResmi() as $kode => $label)
                                    <option value="{{ $kode }}" @selected(old('jenis') === $kode)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('jenis')<p class="galat">{{ $message }}</p>@enderror
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
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-navy-700 hover:file:bg-navy-100">
                            @error('berkas')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" />Unggah dokumen resmi</button>
                        </div>
                    </form>
                </section>

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
                        <button type="submit" class="btn btn-primary" @disabled($pengajuan->dokumenResmi->isEmpty())>
                            <x-icon name="archive" class="h-4 w-4" />Arsipkan
                        </button>
                        @if ($pengajuan->dokumenResmi->isEmpty())
                            <p class="hint">Unggah minimal satu dokumen resmi terlebih dahulu.</p>
                        @endif
                    </form>
                </section>
            @elseif ($pengajuan->status === \App\Enums\StatusPengajuan::Diarsipkan)
                <div class="alert alert-info">
                    Pengajuan ini sudah diarsipkan. <a href="{{ route('operator.arsip.show', $pengajuan) }}" class="underline">Buka di arsip</a>.
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
