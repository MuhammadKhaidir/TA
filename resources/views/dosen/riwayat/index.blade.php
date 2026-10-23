@extends('layouts.app')

@section('judul', 'Riwayat Pemeriksaan')

@section('konten')
    <x-page-header judul="Riwayat Pemeriksaan" sub="Seluruh keputusan pemeriksaan dokumen yang pernah Anda berikan." />

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada riwayat pemeriksaan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Mahasiswa</th><th>Dokumen</th><th>Keputusan</th><th>Catatan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $r->pengajuan->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $r->pengajuan->jenis->singkat() }}: {{ $r->pengajuan->judul }}</div>
                                </td>
                                <td>
                                    {{ $r->dokumen?->persyaratan->nama ?? '-' }}
                                    @if ($r->dokumen)<div class="text-xs text-slate-500">versi {{ $r->dokumen->versi }}</div>@endif
                                </td>
                                <td><x-status-badge :status="$r->keputusan" /></td>
                                <td class="max-w-xs whitespace-pre-line">{{ $r->catatan ?: '-' }}</td>
                                <td class="text-right"><a href="{{ route('dosen.pemeriksaan.show', $r->pengajuan) }}" class="tautan">Buka</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($daftar->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $daftar->links() }}</div>
            @endif
        @endif
    </section>
@endsection
