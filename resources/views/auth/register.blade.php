@extends('layouts.auth')

@section('judul', 'Daftar')

@section('konten')
    <h2 class="font-serif text-[length:clamp(1.5rem,4vh,2.25rem)] font-bold leading-tight text-ink-900">Daftar akun mahasiswa</h2>
    <p class="mt-1.5 text-pretty text-[length:clamp(0.8125rem,1.9vh,1rem)] text-ink-600">Isi data diri Anda. Akun dosen dan admin dibuat oleh admin sistem.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
        @csrf

        <div class="sm:col-span-2">
            <label for="name" class="label font-serif font-semibold text-ink-900">Nama lengkap</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="input rounded-lg bg-sand-50 px-4 py-2.5">
            @error('name')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="email" class="label font-serif font-semibold text-ink-900">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                   class="input rounded-lg bg-sand-50 px-4 py-2.5">
            @error('email')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nomor_induk" class="label font-serif font-semibold text-ink-900">NIM</label>
            <input id="nomor_induk" name="nomor_induk" type="text" inputmode="numeric" value="{{ old('nomor_induk') }}" required maxlength="30" autocomplete="off"
                   class="input rounded-lg bg-sand-50 px-4 py-2.5">
            @error('nomor_induk')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="angkatan" class="label font-serif font-semibold text-ink-900">Angkatan</label>
            <input id="angkatan" name="angkatan" type="number" min="1990" max="{{ now()->year }}" value="{{ old('angkatan') }}" required placeholder="{{ now()->year }}"
                   class="input rounded-lg bg-sand-50 px-4 py-2.5">
            @error('angkatan')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="program_studi_id" class="label font-serif font-semibold text-ink-900">Program studi</label>
            <select id="program_studi_id" name="program_studi_id" required class="input rounded-lg bg-sand-50 px-4 py-2.5">
                <option value="">Pilih program studi</option>
                @foreach ($prodi as $ps)
                    <option value="{{ $ps->id }}" @selected((string) old('program_studi_id') === (string) $ps->id)>{{ $ps->namaLengkap() }}</option>
                @endforeach
            </select>
            @error('program_studi_id')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <x-input-sandi id="password" label="Kata sandi" />
        <x-input-sandi id="password_confirmation" label="Ulangi kata sandi" />

        <p class="hint -mt-2 sm:col-span-2">Kata sandi minimal 8 karakter.</p>

        <div class="sm:col-span-2">
            <button type="submit" class="btn btn-primary w-full rounded-lg py-2.5 font-serif">Daftar</button>
        </div>
    </form>

    <p class="mt-5 text-center font-serif text-sm text-ink-700">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="tautan font-semibold underline underline-offset-2">Masuk</a>
    </p>
@endsection
