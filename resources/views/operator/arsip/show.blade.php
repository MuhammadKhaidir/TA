@extends('layouts.app')

@section('judul', 'Arsip Berkas')

@section('konten')
    <x-page-header :judul="$pengajuan->jenis->label().': '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.arsip.index') }}" class="btn btn-secondary">Kembali ke arsip</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @include('partials.pengajuan.dokumen-resmi')
            @include('partials.pengajuan.dokumen')
        </div>

        <div class="space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="font-semibold text-slate-900">Informasi Arsip</h2></div>
                <dl class="divide-y divide-slate-100 px-5 py-2">
                    <div class="dl-baris"><dt>Diarsipkan</dt><dd>{{ $pengajuan->arsip?->diarsipkan_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="dl-baris"><dt>Oleh</dt><dd>{{ $pengajuan->arsip?->petugas?->name ?? '-' }}</dd></div>
                    @if (filled($pengajuan->arsip?->catatan))
                        <div class="dl-baris"><dt>Catatan</dt><dd class="whitespace-pre-line">{{ $pengajuan->arsip->catatan }}</dd></div>
                    @endif
                </dl>
            </section>

            @include('partials.pengajuan.ringkasan')
            @include('partials.pengajuan.riwayat')
        </div>
    </div>
@endsection
