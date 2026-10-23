@extends('layouts.app')

@section('judul', 'Verifikasi Administrasi')

@section('konten')
    <x-page-header judul="Verifikasi Administrasi" sub="Pengajuan yang menunggu pemeriksaan kelengkapan administrasi, diurutkan dari yang paling lama." />

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Tidak ada pengajuan yang menunggu verifikasi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Jenis</th><th>Judul</th><th>Pembimbing</th><th>Dikirim</th><th></th></tr></thead>
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
                                <td>{{ $p->pembimbing?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap">{{ $p->diajukan_at?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="text-right"><a href="{{ route('operator.verifikasi.show', $p) }}" class="btn btn-primary btn-sm">Verifikasi</a></td>
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
