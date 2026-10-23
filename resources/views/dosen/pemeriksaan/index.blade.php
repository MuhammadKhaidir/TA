@extends('layouts.app')

@section('judul', 'Pemeriksaan Berkas')

@section('konten')
    <x-page-header judul="Pemeriksaan Berkas" sub="Berkas mahasiswa bimbingan Anda yang menunggu pemeriksaan akademik." />

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Tidak ada berkas yang menunggu pemeriksaan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Jenis</th><th>Judul</th><th>Masuk antrean</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->mahasiswa->programStudi?->namaLengkap() ?? '-' }}</td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="text-right"><a href="{{ route('dosen.pemeriksaan.show', $p) }}" class="btn btn-primary btn-sm">Periksa</a></td>
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
