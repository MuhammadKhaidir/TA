@extends('layouts.app')

@section('judul', $syarat ? 'Ubah Persyaratan' : 'Tambah Persyaratan')

@section('konten')
    @php
        $edit = $syarat !== null;
        $ekstensiTerpilih = old('ekstensi', $syarat ? $syarat->ekstensiList() : ['pdf']);
    @endphp

    <x-page-header :judul="$edit ? 'Ubah Persyaratan' : 'Tambah Persyaratan'" />

    <form method="POST" action="{{ $edit ? route('admin.persyaratan.update', $syarat) : route('admin.persyaratan.store') }}" class="card max-w-3xl">
        @csrf
        @if ($edit)
            @method('PUT')
        @endif

        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div>
                <label for="jenis_pengajuan" class="label">Jenis pengajuan</label>
                <select id="jenis_pengajuan" name="jenis_pengajuan" required class="input">
                    @foreach (\App\Enums\JenisPengajuan::pilihan() as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('jenis_pengajuan', $syarat?->jenis_pengajuan?->value) === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('jenis_pengajuan')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="pemeriksa" class="label">Diperiksa oleh</label>
                <select id="pemeriksa" name="pemeriksa" required class="input">
                    <option value="operator" @selected(old('pemeriksa', $syarat?->pemeriksa?->value) === 'operator')>Operator akademik (administrasi)</option>
                    <option value="dosen" @selected(old('pemeriksa', $syarat?->pemeriksa?->value) === 'dosen')>Dosen pembimbing (akademik)</option>
                </select>
                @error('pemeriksa')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="nama" class="label">Nama dokumen</label>
                <input id="nama" name="nama" type="text" value="{{ old('nama', $syarat?->nama) }}" required class="input">
                @error('nama')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="deskripsi" class="label">Keterangan untuk mahasiswa (opsional)</label>
                <textarea id="deskripsi" name="deskripsi" rows="2" class="input">{{ old('deskripsi', $syarat?->deskripsi) }}</textarea>
                @error('deskripsi')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="urutan" class="label">Urutan tampil</label>
                <input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ old('urutan', $syarat?->urutan ?? 0) }}" required class="input">
                @error('urutan')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="ukuran_maks_kb" class="label">Ukuran maksimal (KB)</label>
                <input id="ukuran_maks_kb" name="ukuran_maks_kb" type="number" min="100" max="20480" value="{{ old('ukuran_maks_kb', $syarat?->ukuran_maks_kb ?? 5120) }}" required class="input">
                <p class="hint">5120 KB sama dengan 5 MB.</p>
                @error('ukuran_maks_kb')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <fieldset class="sm:col-span-2">
                <legend class="label">Format berkas yang diizinkan</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-700">
                    @foreach (\App\Models\Persyaratan::EKSTENSI_DIIZINKAN as $ext)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="ekstensi[]" value="{{ $ext }}" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300"
                                   @checked(in_array($ext, $ekstensiTerpilih, true))>
                            {{ strtoupper($ext) }}
                        </label>
                    @endforeach
                </div>
                @error('ekstensi')<p class="galat">{{ $message }}</p>@enderror
                @error('ekstensi.*')<p class="galat">{{ $message }}</p>@enderror
            </fieldset>

            <div class="flex flex-wrap gap-x-8 gap-y-3 sm:col-span-2">
                <div>
                    <input type="hidden" name="wajib" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="wajib" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300"
                               @checked(old('wajib', $syarat?->wajib ?? true))>
                        Wajib diunggah sebelum pengajuan dikirim
                    </label>
                </div>
                <div>
                    <input type="hidden" name="aktif" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="aktif" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300"
                               @checked(old('aktif', $syarat?->aktif ?? true))>
                        Aktif
                    </label>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-4">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.persyaratan.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
