@extends('layouts.app')

@section('title', 'Dashboard Pengelola Layanan')
@section('page-title', 'Pengelola Layanan')
@section('page-subtitle', 'Langkah 5, 13, dan 14 pada Bagan Alir POS')

@section('content')
    @include('partials.antrean-card', [
        'judul' => 'Menunggu Penerbitan SK Penguji',
        'items' => $antrean,
        'kosong' => 'Tidak ada sidang yang menunggu SK Penguji.',
    ])

    @include('partials.antrean-card', [
        'judul' => 'Pemeriksaan Nilai SIMAK (Langkah 13)',
        'items' => $pemeriksaanNilai,
        'kosong' => 'Tidak ada sidang yang sedang menunggu nilai.',
    ])
@endsection
