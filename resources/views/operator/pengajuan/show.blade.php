@extends('layouts.app')

@section('judul', 'Kelola Pengajuan')

@section('konten')
    <x-page-header :judul="$pengajuan->jenis->label().': '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.pengajuan.index') }}" class="btn btn-secondary">Kembali</a>
            @can('verifikasi', $pengajuan)
                <a href="{{ route('operator.verifikasi.show', $pengajuan) }}" class="btn btn-primary">Verifikasi</a>
            @endcan
            @can('prosesDokumenResmi', $pengajuan)
                <a href="{{ route('operator.dokumen-resmi.show', $pengajuan) }}" class="btn btn-primary">Proses Dokumen Resmi</a>
            @endcan
            @if ($pengajuan->status === \App\Enums\StatusPengajuan::Diarsipkan)
                <a href="{{ route('operator.arsip.show', $pengajuan) }}" class="btn btn-primary">Lihat di Arsip</a>
            @endif
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @can('kelola', $pengajuan)
                <section class="card">
                    <div class="card-header"><h2 class="font-semibold text-slate-900">Dosen Pembimbing</h2></div>
                    <form method="POST" action="{{ route('operator.pengajuan.pembimbing', $pengajuan) }}" class="card-body flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-[16rem] flex-1">
                            <label for="pembimbing_id" class="label">Pilih dosen</label>
                            <select id="pembimbing_id" name="pembimbing_id" required class="input">
                                @foreach ($dosen as $d)
                                    <option value="{{ $d->id }}" @selected((int) old('pembimbing_id', $pengajuan->pembimbing_id) === $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @error('pembimbing_id')<p class="galat">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </section>
            @endcan

            @include('partials.pengajuan.dokumen')

            @if ($pengajuan->dokumenResmi->isNotEmpty())
                @include('partials.pengajuan.dokumen-resmi')
            @endif
        </div>

        <div class="space-y-6">
            @include('partials.pengajuan.ringkasan')
            @include('partials.pengajuan.riwayat')
        </div>
    </div>
@endsection
