@extends('layouts.app')

@section('judul', ($pengajuan ? 'Ubah' : 'Buat').' Pengajuan '.$jenis->singkat())

@section('konten')
    @php($edit = $pengajuan !== null)

    <x-page-header :judul="($edit ? 'Ubah' : 'Buat').' Pengajuan '.$jenis->label()" sub="Isi data pengajuan. Dokumen persyaratan diunggah setelah draft tersimpan." />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST"
              action="{{ $edit ? route('mahasiswa.pengajuan.update', $pengajuan) : route('mahasiswa.pengajuan.store', $jenis->value) }}"
              class="card lg:col-span-2">
            @csrf
            @if ($edit)
                @method('PUT')
            @endif

            <div class="card-body space-y-5">
                <div>
                    <label for="judul" class="label">Judul {{ $jenis->label() }}</label>
                    <input id="judul" name="judul" type="text" value="{{ old('judul', $pengajuan?->judul) }}" required class="input">
                    @error('judul')<p class="galat">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="instansi" class="label">
                        {{ $jenis === \App\Enums\JenisPengajuan::Kp ? 'Instansi / tempat KP' : 'Instansi / lokasi penelitian (opsional)' }}
                    </label>
                    <input id="instansi" name="instansi" type="text" value="{{ old('instansi', $pengajuan?->instansi) }}"
                           @required($jenis === \App\Enums\JenisPengajuan::Kp) class="input">
                    @error('instansi')<p class="galat">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deskripsi" class="label">Deskripsi singkat (opsional)</label>
                    <textarea id="deskripsi" name="deskripsi" rows="4" class="input">{{ old('deskripsi', $pengajuan?->deskripsi) }}</textarea>
                    @error('deskripsi')<p class="galat">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="pembimbing_id" class="label">Dosen pembimbing</label>
                    <select id="pembimbing_id" name="pembimbing_id" required class="input">
                        <option value="">Pilih dosen</option>
                        @foreach ($dosen as $d)
                            <option value="{{ $d->id }}" @selected((string) old('pembimbing_id', $pengajuan?->pembimbing_id) === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Operator akademik dapat menyesuaikan dosen pembimbing bila diperlukan.</p>
                    @error('pembimbing_id')<p class="galat">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-4">
                <button type="submit" class="btn btn-primary">{{ $edit ? 'Simpan perubahan' : 'Simpan sebagai draft' }}</button>
                <a href="{{ $edit ? route('mahasiswa.pengajuan.show', $pengajuan) : route('mahasiswa.pengajuan.index', $jenis->value) }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>

        <section class="card self-start">
            <div class="card-header"><h2 class="font-semibold text-slate-900">Dokumen yang Diminta</h2></div>
            @if ($persyaratan->isEmpty())
                <x-empty pesan="Belum ada persyaratan yang diatur." />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($persyaratan as $syarat)
                        <li class="px-5 py-3 text-sm">
                            <div class="font-medium text-slate-900">
                                {{ $syarat->nama }}
                                @unless ($syarat->wajib)<span class="text-xs font-normal text-slate-500">(opsional)</span>@endunless
                            </div>
                            <div class="text-xs text-slate-500">{{ strtoupper(implode(', ', $syarat->ekstensiList())) }} &middot; maks. {{ $syarat->ukuranMaksManusia() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
