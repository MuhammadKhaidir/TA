@extends('layouts.guest')

@section('judul', 'Halaman tidak ditemukan')

@section('konten')
    <div class="card p-8 text-center">
        <div class="text-4xl font-semibold text-navy-700">404</div>
        <h1 class="mt-2 text-lg font-semibold text-slate-900">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ $exception->getMessage() ?: 'Halaman atau data yang Anda cari tidak tersedia.' }}
        </p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6">Kembali ke dashboard</a>
    </div>
@endsection
