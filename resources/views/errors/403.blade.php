@extends('layouts.guest')

@section('judul', 'Akses ditolak')

@section('konten')
    <div class="card p-8 text-center">
        <div class="text-4xl font-semibold text-navy-700">403</div>
        <h1 class="mt-2 text-lg font-semibold text-slate-900">Akses ditolak</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ $exception->getMessage() ?: 'Anda tidak memiliki kewenangan untuk membuka halaman ini.' }}
        </p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6">Kembali ke dashboard</a>
    </div>
@endsection
