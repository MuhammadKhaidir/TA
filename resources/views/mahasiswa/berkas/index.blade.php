@extends('layouts.app')

@section('judul', 'Berkas Saya')

@section('konten')
    <x-page-header judul="Berkas Saya" sub="Seluruh dokumen yang Anda unggah dan dokumen resmi yang diterbitkan untuk Anda." />

    <section class="card mb-6">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Dokumen Resmi</h2></div>
        @if ($resmi->isEmpty())
            <x-empty pesan="Belum ada dokumen resmi yang diterbitkan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Dokumen</th><th>Nomor</th><th>Tanggal</th><th>Pengajuan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($resmi as $r)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $r->jenisLabel() }}</td>
                                <td>{{ $r->nomor }}</td>
                                <td class="whitespace-nowrap">{{ $r->tanggal->translatedFormat('d M Y') }}</td>
                                <td>{{ $r->pengajuan->jenis->singkat() }}: {{ $r->pengajuan->judul }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r, 'unduh' => 1]) }}" class="tautan ml-3">Unduh</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Dokumen Persyaratan yang Diunggah</h2></div>
        @if ($dokumen->isEmpty())
            <x-empty pesan="Belum ada dokumen yang diunggah." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Persyaratan</th><th>Pengajuan</th><th>Berkas</th><th>Status</th><th>Diunggah</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($dokumen as $d)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $d->persyaratan->nama }}</td>
                                <td>
                                    <a href="{{ route('mahasiswa.pengajuan.show', $d->pengajuan) }}" class="tautan">{{ $d->pengajuan->jenis->singkat() }}</a>
                                </td>
                                <td>
                                    {{ $d->nama_asli }}
                                    <div class="text-xs text-slate-500">
                                        {{ $d->ukuranManusia() }} &middot; versi {{ $d->versi }}@unless ($d->terbaru) (versi lama)@endunless
                                    </div>
                                </td>
                                <td><x-status-badge :status="$d->status" /></td>
                                <td class="whitespace-nowrap">{{ $d->created_at->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('berkas.dokumen', ['dokumen' => $d]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                                    <a href="{{ route('berkas.dokumen', ['dokumen' => $d, 'unduh' => 1]) }}" class="tautan ml-3">Unduh</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($dokumen->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $dokumen->links() }}</div>
            @endif
        @endif
    </section>
@endsection
