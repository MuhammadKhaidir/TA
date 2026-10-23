@extends('layouts.app')

@section('judul', $prodi ? 'Ubah Program Studi' : 'Tambah Program Studi')

@section('konten')
    @php($edit = $prodi !== null)

    <x-page-header :judul="$edit ? 'Ubah Program Studi' : 'Tambah Program Studi'" />

    <form method="POST" action="{{ $edit ? route('admin.program-studi.update', $prodi) : route('admin.program-studi.store') }}" class="card max-w-2xl">
        @csrf
        @if ($edit)
            @method('PUT')
        @endif

        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div>
                <label for="kode" class="label">Kode</label>
                <input id="kode" name="kode" type="text" value="{{ old('kode', $prodi?->kode) }}" required class="input">
                @error('kode')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="jenjang" class="label">Jenjang</label>
                <select id="jenjang" name="jenjang" required class="input">
                    @foreach ($jenjang as $j)
                        <option value="{{ $j }}" @selected(old('jenjang', $prodi?->jenjang ?? 'S1') === $j)>{{ $j }}</option>
                    @endforeach
                </select>
                @error('jenjang')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="nama" class="label">Nama program studi</label>
                <input id="nama" name="nama" type="text" value="{{ old('nama', $prodi?->nama) }}" required class="input">
                @error('nama')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <input type="hidden" name="aktif" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aktif" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300"
                           @checked(old('aktif', $prodi?->aktif ?? true))>
                    Aktif (dapat dipilih pada pengguna baru)
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-4">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.program-studi.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
