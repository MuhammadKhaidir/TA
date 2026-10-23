@extends('layouts.app')

@section('judul', 'Pemeriksaan Berkas')

@section('konten')
    <x-page-header :judul="$pengajuan->jenis->label().': '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('dosen.bimbingan.index') }}" class="btn btn-secondary">Kembali</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @include('partials.pengajuan.dokumen')

            @can('periksa', $pengajuan)
                @include('partials.pengajuan.form-periksa', [
                    'peran' => \App\Enums\Role::Dosen,
                    'aksi' => route('dosen.pemeriksaan.store', $pengajuan),
                ])
            @else
                <div class="alert alert-info">
                    Pengajuan ini tidak sedang menunggu pemeriksaan Anda (status: {{ $pengajuan->status->label() }}).
                </div>
            @endcan

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
