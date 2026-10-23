@extends('layouts.guest')

@section('judul', 'Masuk')

@section('konten')
    <div class="card p-8">
        <h1 class="text-xl font-semibold text-navy-900">Masuk ke akun Anda</h1>
        <p class="mt-1 text-sm text-slate-500">Gunakan email atau NIM/NIP beserta kata sandi yang terdaftar.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="login" class="label">Email / NIM / NIP</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="input">
                @error('login')
                    <p class="galat">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="label">Kata sandi</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
                @error('password')
                    <p class="galat">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="ingat" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-300">
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="btn btn-primary w-full">Masuk</button>
        </form>
    </div>
@endsection
