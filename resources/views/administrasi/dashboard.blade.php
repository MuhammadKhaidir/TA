@extends('layouts.app')

@section('title', 'Dashboard Pengadministrasi Perkantoran')
@section('page-title', 'Pengadministrasi Perkantoran')
@section('page-subtitle', 'Langkah 7, 8, 9, dan 11 pada Bagan Alir POS')

@section('content')
    @include('partials.antrean-card', [
        'judul' => 'Perlu Tindakan Anda',
        'items' => $antrean,
        'kosong' => 'Tidak ada sidang yang menunggu penyiapan administrasi.',
    ])

    @include('partials.antrean-card', [
        'judul' => 'Siap Dilaksanakan — Menunggu Hari-H (Langkah 11)',
        'items' => $siapUjian,
        'kosong' => 'Tidak ada sidang yang siap diserahkan berkasnya.',
    ])
@endsection
