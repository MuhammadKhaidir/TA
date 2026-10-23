@extends('layouts.app')

@section('judul', 'Pengaturan Persyaratan')

@section('konten')
    <x-page-header judul="Pengaturan Persyaratan" sub="Dokumen yang wajib diunggah mahasiswa. Perubahan langsung berlaku pada form pengajuan dan validasi unggahan.">
        <x-slot:aksi>
            <a href="{{ route('admin.persyaratan.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" />Tambah Persyaratan</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.persyaratan.index') }}" class="btn btn-sm {{ $jenis === null ? 'btn-primary' : 'btn-secondary' }}">Semua</a>
        @foreach (\App\Enums\JenisPengajuan::cases() as $j)
            <a href="{{ route('admin.persyaratan.index', ['jenis' => $j->value]) }}" class="btn btn-sm {{ $jenis === $j ? 'btn-primary' : 'btn-secondary' }}">{{ $j->label() }}</a>
        @endforeach
    </div>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada persyaratan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Urutan</th><th>Dokumen</th><th>Jenis</th><th>Pemeriksa</th><th>Format / Ukuran</th><th>Sifat</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $s)
                            <tr>
                                <td>{{ $s->urutan }}</td>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $s->nama }}</div>
                                    @if ($s->deskripsi)<div class="max-w-xs text-xs text-slate-500">{{ $s->deskripsi }}</div>@endif
                                    @if ($s->dokumen_count > 0)<div class="text-xs text-slate-500">{{ $s->dokumen_count }} berkas diunggah</div>@endif
                                </td>
                                <td>{{ $s->jenis_pengajuan->singkat() }}</td>
                                <td>{{ $s->pemeriksa === \App\Enums\Role::Dosen ? 'Dosen' : 'Operator' }}</td>
                                <td class="whitespace-nowrap">{{ strtoupper(implode(', ', $s->ekstensiList())) }}<div class="text-xs text-slate-500">maks. {{ $s->ukuranMaksManusia() }}</div></td>
                                <td>{{ $s->wajib ? 'Wajib' : 'Opsional' }}</td>
                                <td>
                                    @if ($s->aktif)
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">Aktif</span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-600 ring-slate-300">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('admin.persyaratan.edit', $s) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('admin.persyaratan.destroy', $s) }}" class="ml-1 inline" data-konfirmasi="Hapus persyaratan {{ $s->nama }}?">
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
