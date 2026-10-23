@extends('layouts.app')

@section('judul', 'Data Master')

@section('konten')
    <x-page-header judul="Data Master: Program Studi" sub="Daftar program studi yang dipakai pada data mahasiswa, dosen, dan laporan.">
        <x-slot:aksi>
            <a href="{{ route('admin.program-studi.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" />Tambah Program Studi</a>
        </x-slot:aksi>
    </x-page-header>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada program studi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Jenjang</th><th>Pengguna</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $ps)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $ps->kode }}</td>
                                <td>{{ $ps->nama }}</td>
                                <td>{{ $ps->jenjang }}</td>
                                <td>
                                    <a href="{{ route('admin.users.index', ['prodi' => $ps->id]) }}" class="tautan">{{ $ps->pengguna_count }} pengguna</a>
                                </td>
                                <td>
                                    @if ($ps->aktif)
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">Aktif</span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-600 ring-slate-300">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('admin.program-studi.edit', $ps) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('admin.program-studi.destroy', $ps) }}" class="ml-1 inline" data-konfirmasi="Hapus program studi {{ $ps->nama }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary btn-sm text-red-600">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
