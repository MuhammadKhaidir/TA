@extends('layouts.app')

@section('judul', 'Verifikasi Administrasi')

@section('konten')
    <x-page-header :judul="'Verifikasi: '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.verifikasi.index') }}" class="btn btn-secondary">Kembali</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @include('partials.pengajuan.dokumen')

            @can('verifikasi', $pengajuan)
                @include('partials.pengajuan.form-periksa', [
                    'peran' => \App\Enums\Role::Operator,
                    'aksi' => route('operator.verifikasi.store', $pengajuan),
                ])
            @else
                <div class="alert alert-info">
                    Pengajuan ini tidak sedang menunggu verifikasi administrasi (status: {{ $pengajuan->status->label() }}).
                </div>
            @endcan
        </div>

        <div class="space-y-6">
            @include('partials.pengajuan.ringkasan')
            @include('partials.pengajuan.riwayat')
        </div>
    </div>
@endsection
