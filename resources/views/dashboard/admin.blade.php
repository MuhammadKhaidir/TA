@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    @php
        $hitung = fn (string $role) => (int) ($perRole[$role] ?? 0);
    @endphp

    <x-page-header judul="Dashboard" sub="Ringkasan data pengguna dan pengaturan sistem." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Mahasiswa" :nilai="$hitung('mahasiswa')" :href="route('admin.users.index', ['role' => 'mahasiswa'])" />
        <x-stat label="Dosen" :nilai="$hitung('dosen')" :href="route('admin.users.index', ['role' => 'dosen'])" />
        <x-stat label="Operator dan Admin" :nilai="$hitung('operator') + $hitung('admin')" :href="route('admin.users.index', ['role' => 'operator'])" />
        <x-stat label="Program Studi" :nilai="$jumlahProdi" :href="route('admin.program-studi.index')" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Persyaratan Aktif" :nilai="$jumlahPersyaratan" hint="KP dan TA" :href="route('admin.persyaratan.index')" />
    </div>

    <section class="card mt-6">
        <div class="card-header">
            <h2 class="font-semibold text-slate-900">Aktivitas Terbaru</h2>
            <a href="{{ route('admin.log.index') }}" class="tautan text-sm">Lihat semua</a>
        </div>
        @if ($logTerbaru->isEmpty())
            <x-empty pesan="Belum ada aktivitas tercatat." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th></tr></thead>
                    <tbody>
                        @foreach ($logTerbaru as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td>{{ $log->deskripsi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
