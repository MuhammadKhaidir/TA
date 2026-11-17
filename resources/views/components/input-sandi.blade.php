{{--
    Kolom kata sandi dengan tombol tampil/sembunyikan (skripnya di resources/js/app.js, lewat data-toggle-password).
    Dipakai di halaman daftar. Atribut: id (wajib), label (wajib), name (bawaan: sama dengan id), autocomplete.
--}}
@props(['id', 'label', 'name' => null, 'autocomplete' => 'new-password'])

@php($nama = $name ?? $id)

<div>
    <label for="{{ $id }}" class="label font-serif font-semibold text-ink-900">{{ $label }}</label>
    <div class="relative">
        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
            <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
        </svg>
        <input id="{{ $id }}" name="{{ $nama }}" type="password" required autocomplete="{{ $autocomplete }}"
               class="input rounded-lg bg-sand-50 py-2.5 pl-11 pr-11">
        <button type="button" data-toggle-password="{{ $id }}" aria-label="Tampilkan kata sandi" aria-pressed="false"
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
    @error($nama)
        <p class="galat">{{ $message }}</p>
    @enderror
</div>
