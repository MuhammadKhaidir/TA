@extends('layouts.app')

@section('judul', 'Dokumen Resmi')

@section('konten')
    <x-page-header judul="Dokumen Resmi" sub="Proses dokumen resmi (misalnya SK TA) untuk pengajuan yang sudah disetujui, lalu arsipkan." />

    <section class="card mb-6">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Perlu Diproses</h2></div>
        @if ($perluDiproses->isEmpty())
            <x-empty pesan="Tidak ada pengajuan yang menunggu dokumen resmi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Judul</th><th>Pembimbing</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($perluDiproses as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                </td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td>{{ $p->pembimbing?->name ?? '-' }}</td>
                                <td class="text-right"><a href="{{ route('operator.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Proses</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Dokumen Resmi yang Sudah Diunggah</h2></div>
        @if ($terbit->isEmpty())
            <x-empty pesan="Belum ada dokumen resmi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Dokumen</th><th>Nomor</th><th>Tanggal</th><th>Mahasiswa</th><th>Diunggah oleh</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($terbit as $r)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $r->jenisLabel() }}</td>
                                <td>{{ $r->nomor }}</td>
                                <td class="whitespace-nowrap">{{ $r->tanggal->translatedFormat('d M Y') }}</td>
                                <td>{{ $r->pengajuan->mahasiswa->name }}</td>
                                <td>{{ $r->pengunggah->name }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r, 'unduh' => 1]) }}" class="tautan ml-3">Unduh</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($terbit->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $terbit->links() }}</div>
            @endif
        @endif
    </section>
@endsection
