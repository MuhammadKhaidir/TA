@extends('layouts.app')

@section('title', 'Dashboard Dosen Penguji')
@section('page-title', 'Dosen Penguji')
@section('page-subtitle', $dosen ? $dosen->nama : null)

@section('content')
    @if (! $dosen)
        <div class="card p-6 text-sm text-slate-500">
            Akun Anda belum tertaut ke data dosen. Hubungi Administrator.
        </div>
    @else
        @include('partials.antrean-card', [
            'judul' => 'Perlu Tindakan Anda (Langkah 10 &amp; 12)',
            'items' => $perluTindakan,
            'kosong' => 'Tidak ada sidang yang memerlukan konfirmasi kehadiran atau input nilai.',
        ])

        @include('partials.antrean-card', [
            'judul' => 'Seluruh Sidang Sebagai Dewan Penguji',
            'items' => $sidangs,
            'kosong' => 'Anda belum ditugaskan sebagai penguji pada sidang manapun.',
        ])
    @endif
@endsection
