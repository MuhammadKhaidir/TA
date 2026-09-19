@extends('layouts.app')

@section('title', 'Dashboard SekDep / Koor. Prodi')
@section('page-title', 'SekDep / Koor. Prodi')
@section('page-subtitle', 'Langkah 2, 6, 10, dan 16 pada Bagan Alir POS')

@section('content')
    @include('partials.antrean-card', [
        'judul' => 'Perlu Tindakan Anda',
        'items' => $antrean,
        'kosong' => 'Tidak ada sidang yang menunggu jadwal, distribusi SK, atau penjadwalan ulang.',
    ])

    @include('partials.antrean-card', [
        'judul' => 'Ditunda — Menunggu Syarat Konsultasi (Peringatan #1)',
        'items' => $ditunda,
        'kosong' => 'Tidak ada pengajuan yang ditunda.',
    ])

    @include('partials.antrean-card', [
        'judul' => 'Eskalasi Nilai Belum Lengkap (Langkah 16)',
        'items' => $eskalasiNilai,
        'kosong' => 'Tidak ada sidang dengan nilai yang masih tertunda.',
    ])
@endsection
