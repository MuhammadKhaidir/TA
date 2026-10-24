@extends('layouts.app')

@section('judul', 'Pengajuan '.$jenis->singkat())

@section('konten')
    <x-page-header :judul="'Pengajuan '.$jenis->label()" :sub="'Daftar pengajuan '.$jenis->label().' Anda.'">
        <x-slot:aksi>
            @if ($bisaBuat)
                <a href="{{ route('mahasiswa.pengajuan.create', $jenis->value) }}" class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />Buat Pengajuan {{ $jenis->singkat() }}
                </a>
            @endif
        </x-slot:aksi>
    </x-page-header>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty :pesan="'Belum ada pengajuan '.$jenis->label().'. Klik tombol Buat Pengajuan untuk memulai.'" />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr><th>Judul</th><th>Dosen Pembimbing</th><th>Status</th><th>Diperbarui</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $p->judul }}</td>
                                <td>{{ $p->ringkasanPembimbing() }}</td>
                                <td><x-status-badge :status="$p->status" /></td>
                                <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="text-right"><a href="{{ route('mahasiswa.pengajuan.show', $p) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
