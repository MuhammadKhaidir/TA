@extends('layouts.app')

@section('judul', 'Mahasiswa Bimbingan')

@section('konten')
    <x-page-header judul="Mahasiswa Bimbingan" sub="Mahasiswa yang menetapkan Anda sebagai dosen pembimbing KP atau TA." />

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[16rem] flex-1">
            <label for="q" class="label">Cari mahasiswa</label>
            <input id="q" name="q" type="text" value="{{ $filter['q'] ?? '' }}" placeholder="Nama atau NIM" class="input">
        </div>
        <button type="submit" class="btn btn-primary">Cari</button>
        <a href="{{ route('dosen.bimbingan.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada mahasiswa bimbingan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Angkatan</th><th>Pengajuan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->mahasiswa->programStudi?->namaLengkap() ?? '-' }}</td>
                                <td>{{ $p->mahasiswa->angkatan ?? '-' }}</td>
                                <td>{{ $p->jenis->singkat() }}: {{ $p->judul }}</td>
                                <td><x-status-badge :status="$p->status" /></td>
                                <td class="text-right"><a href="{{ route('dosen.pemeriksaan.show', $p) }}" class="btn btn-secondary btn-sm">Detail</a></td>
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
