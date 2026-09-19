@extends('layouts.app')

@section('title', 'Dashboard Penata')
@section('page-title', 'Penata')
@section('page-subtitle', 'Langkah 3, 4, 6, dan 15 pada Bagan Alir POS')

@section('content')
    @include('partials.antrean-card', [
        'judul' => 'Perlu Tindakan Anda',
        'items' => $antrean,
        'kosong' => 'Tidak ada sidang yang menunggu verifikasi atau diteruskan SK.',
    ])

    @include('partials.antrean-card', [
        'judul' => 'Eskalasi Nilai Belum Lengkap (Langkah 15)',
        'items' => $eskalasiNilai,
        'kosong' => 'Tidak ada sidang dengan nilai yang masih tertunda.',
    ])
@endsection
