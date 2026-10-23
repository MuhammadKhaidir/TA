@extends('layouts.app')

@section('judul', $user ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('konten')
    @php($edit = $user !== null)

    <x-page-header :judul="$edit ? 'Ubah Pengguna' : 'Tambah Pengguna'"
                   sub="Perubahan di sini langsung dipakai pada profil, dashboard, dan seluruh halaman yang menampilkan data pengguna ini." />

    <form method="POST" action="{{ $edit ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-3xl">
        @csrf
        @if ($edit)
            @method('PUT')
        @endif

        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="label">Nama lengkap</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required class="input">
                @error('name')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required class="input">
                @error('email')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="nomor_induk" class="label">Nomor induk (NIM / NIP / ID)</label>
                <input id="nomor_induk" name="nomor_induk" type="text" value="{{ old('nomor_induk', $user?->nomor_induk) }}" required class="input">
                @error('nomor_induk')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="role" class="label">Role</label>
                <select id="role" name="role" required class="input">
                    @foreach (\App\Enums\Role::pilihan() as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('role', $user?->role?->value) === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="program_studi_id" class="label">Program studi</label>
                <select id="program_studi_id" name="program_studi_id" class="input">
                    <option value="">-</option>
                    @foreach ($prodi as $ps)
                        <option value="{{ $ps->id }}" @selected((string) old('program_studi_id', $user?->program_studi_id) === (string) $ps->id)>{{ $ps->namaLengkap() }}</option>
                    @endforeach
                </select>
                <p class="hint">Wajib untuk mahasiswa.</p>
                @error('program_studi_id')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="angkatan" class="label">Angkatan</label>
                <input id="angkatan" name="angkatan" type="number" min="1990" max="2100" value="{{ old('angkatan', $user?->angkatan) }}" class="input">
                <p class="hint">Wajib untuk mahasiswa; diabaikan untuk role lain.</p>
                @error('angkatan')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="no_hp" class="label">Nomor HP</label>
                <input id="no_hp" name="no_hp" type="text" value="{{ old('no_hp', $user?->no_hp) }}" class="input">
                @error('no_hp')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="label">Kata sandi {{ $edit ? 'baru' : '' }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" @required(! $edit) class="input">
                <p class="hint">{{ $edit ? 'Kosongkan bila tidak ingin mengganti. ' : '' }}Minimal 8 karakter.</p>
                @error('password')<p class="galat">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <input type="hidden" name="aktif" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aktif" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300"
                           @checked(old('aktif', $user?->aktif ?? true))>
                    Akun aktif (pengguna nonaktif tidak dapat login)
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-4">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
