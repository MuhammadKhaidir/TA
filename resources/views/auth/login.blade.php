@extends('layouts.auth')

@section('judul', 'Masuk')

@section('konten')
    <h2 class="font-serif text-[length:clamp(1.5rem,4vh,2.25rem)] font-bold leading-tight text-ink-900">Masuk ke akun Anda</h2>
    <p class="mt-1.5 text-pretty text-[length:clamp(0.8125rem,1.9vh,1rem)] text-ink-600">Gunakan email atau NIM/NIP beserta kata sandi yang terdaftar.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-[clamp(1rem,3vh,1.5rem)] flex flex-col gap-[clamp(0.75rem,2vh,1.25rem)]">
        @csrf

        <div>
            <label for="login" class="label font-serif font-semibold text-ink-900">Email / NIM / NIP</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8.5" r="3.5" />
                    <path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6" />
                </svg>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                       class="input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4">
            </div>
            @error('login')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="label font-serif font-semibold text-ink-900">Kata sandi</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
                    <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
                </svg>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-11">
                <button type="button" data-toggle-password="password" aria-label="Tampilkan kata sandi" aria-pressed="false"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-ink-500 transition hover:text-ink-800 focus:outline-none focus:ring-2 focus:ring-sand-400">
                    <svg data-eye="show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
                        <circle cx="12" cy="12" r="2.8" />
                    </svg>
                    <svg data-eye="hide" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 4l16 16" />
                        <path d="M10.6 6.1A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3 3.8M6.5 7.6A16 16 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4-.9" />
                        <path d="M9.9 9.9a2.8 2.8 0 0 0 4 4" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="galat">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 font-serif text-sm text-ink-700">
            <input type="checkbox" name="ingat" value="1" class="h-4 w-4 rounded border-sand-300 bg-sand-50 text-ink-800 focus:ring-sand-400">
            Ingat saya di perangkat ini
        </label>

        <button type="submit" class="btn btn-primary w-full rounded-lg py-[clamp(0.5rem,1.6vh,0.75rem)] font-serif">Masuk</button>
    </form>
@endsection
