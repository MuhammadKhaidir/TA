@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    <x-page-header judul="Dashboard" sub="Ringkasan pemeriksaan berkas dan bimbingan Anda." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Permintaan Pembimbing" :nilai="$permintaanPembimbing" hint="Pilihan mahasiswa TA, menunggu jawaban Anda" :href="route('dosen.bimbingan.index')" />
        <x-stat label="Menunggu Pemeriksaan" :nilai="$antrean->count()" hint="Berkas yang perlu Anda periksa" :href="route('dosen.pemeriksaan.index')" />
        <x-stat label="Mahasiswa Bimbingan" :nilai="$jumlahMahasiswa" :href="route('dosen.bimbingan.index')" />
        <x-stat label="Pengajuan Selesai" :nilai="$selesai" hint="Sudah diarsipkan" />
        <x-stat label="Dokumen Diperiksa" :nilai="$totalDiperiksa" hint="Seluruh riwayat pemeriksaan" :href="route('dosen.riwayat.index')" />
    </div>

    @if ($permintaanPembimbing > 0)
        <div class="alert alert-info mt-6">
            Ada {{ $permintaanPembimbing }} mahasiswa TA yang memilih Anda sebagai calon pembimbing.
            <a href="{{ route('dosen.bimbingan.index') }}" class="font-medium underline">Lihat dan jawab permintaan</a>
        </div>
    @endif

    <section class="card mt-6">
        <div class="card-header">
            <h2 class="font-semibold text-slate-900">Antrean Pemeriksaan</h2>
            <a href="{{ route('dosen.pemeriksaan.index') }}" class="tautan text-sm">Lihat semua</a>
        </div>
        @if ($antrean->isEmpty())
            <x-empty pesan="Tidak ada berkas yang menunggu pemeriksaan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr><th>Mahasiswa</th><th>Jenis</th><th>Judul</th><th>Masuk antrean</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($antrean->take(8) as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="text-right"><a href="{{ route('dosen.pemeriksaan.show', $p) }}" class="btn btn-primary btn-sm">Periksa</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
