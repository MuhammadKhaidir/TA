@extends('layouts.app')

@section('judul', 'Manajemen Pengguna')

@section('konten')
    <x-page-header judul="Manajemen Pengguna" sub="Kelola akun mahasiswa, dosen, operator akademik, dan admin.">
        <x-slot:aksi>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" />Tambah Pengguna</a>
        </x-slot:aksi>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="q" class="label">Cari</label>
            <input id="q" name="q" type="text" value="{{ $filter['q'] ?? '' }}" placeholder="Nama, email, atau nomor induk" class="input">
        </div>
        <div>
            <label for="role" class="label">Role</label>
            <select id="role" name="role" class="input">
                <option value="">Semua</option>
                @foreach (\App\Enums\Role::pilihan() as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filter['role'] ?? '') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="prodi" class="label">Program Studi</label>
            <select id="prodi" name="prodi" class="input">
                <option value="">Semua</option>
                @foreach ($prodi as $ps)
                    <option value="{{ $ps->id }}" @selected((string) ($filter['prodi'] ?? '') === (string) $ps->id)>{{ $ps->namaLengkap() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Tidak ada pengguna yang cocok." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Nama</th><th>Role</th><th>Nomor Induk</th><th>Program Studi</th><th>Angkatan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $u)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $u->email }}</div>
                                </td>
                                <td>{{ $u->role->label() }}</td>
                                <td>{{ $u->nomor_induk ?? '-' }}</td>
                                <td>{{ $u->programStudi?->namaLengkap() ?? '-' }}</td>
                                <td>{{ $u->angkatan ?? '-' }}</td>
                                <td>
                                    @if ($u->aktif)
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">Aktif</span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-600 ring-slate-300">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                    @unless ($u->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="ml-1 inline" data-konfirmasi="Hapus pengguna {{ $u->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm text-red-600">Hapus</button>
                                        </form>
                                    @endunless
                                </td>
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
