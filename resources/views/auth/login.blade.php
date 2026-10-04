{{--
    Halaman login.
    - Desktop (lg ke atas): tinggi persis 1 layar, tanpa scroll halaman. Ukuran teks, jarak, dan input
      ikut mengecil/membesar mengikuti tinggi layar (clamp + vh).
    - Layar kecil: panel foto disembunyikan, halaman boleh scroll biasa.
    Latar panel kiri: public/images/UnsriBG.png
    Butuh palet sand/ink dan kelas mhs-* dari patch dashboard mahasiswa.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &mdash; Sistem Pendaftaran Ujian Akhir Program</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-sand-50 font-sans text-ink-900 antialiased">
    @php
        $latar = file_exists(public_path('images/UnsriBG.png')) ? asset('images/UnsriBG.png') : null;
    @endphp

    <div class="flex min-h-screen lg:h-screen lg:overflow-hidden">
        {{-- Panel informasi (kiri) --}}
        <div class="relative hidden w-1/2 flex-col overflow-hidden bg-gradient-to-b from-sand-50 to-sand-100 px-[clamp(2rem,5vw,4rem)] py-[clamp(1.25rem,4.5vh,3rem)] lg:flex">
            {{-- Foto: selebar panel, menempel di bawah, menghilang halus ke atas --}}
            @if ($latar)
                <img src="{{ $latar }}" alt=""
                     class="pointer-events-none absolute inset-x-0 bottom-0 h-auto w-full saturate-[.8] sepia-[.3]"
                     style="-webkit-mask-image: linear-gradient(to top, #000 0%, #000 40%, transparent 100%); mask-image: linear-gradient(to top, #000 0%, #000 40%, transparent 100%);">
            @endif
            {{-- Pudarkan ke krem. Naikkan angka /xx kalau teks kurang jelas, turunkan kalau foto terlalu pucat. --}}
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-sand-50 via-sand-50/70 to-sand-50/10"></div>

            {{-- Ornamen sudut kanan atas (hanya layar lebar) --}}
            <svg class="pointer-events-none absolute right-0 top-0 hidden h-[36vh] w-auto text-gold-400 2xl:block" viewBox="0 0 100 340" aria-hidden="true">
                <polygon points="50,0 100,0 100,42 50,95" fill="currentColor" opacity=".4" />
                <path d="M8 340V186L100 82" fill="none" stroke="currentColor" stroke-width="16" opacity=".4" />
            </svg>

            {{-- Merek --}}
            <div class="relative z-10 flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gold-400 font-serif text-base font-bold text-ink-900">UAP</div>
                <div class="h-10 w-px bg-ink-800/50"></div>
                <div class="leading-tight">
                    <p class="font-serif text-lg font-bold text-ink-900">Sistem Pendaftaran</p>
                    <p class="text-base text-ink-700">Ujian Akhir Program</p>
                </div>
            </div>

            {{-- Pesan utama --}}
            <div class="relative z-10 max-w-[38rem] flex-1 pt-[clamp(1rem,9vh,6rem)]">
                <div class="flex items-center gap-4">
                    <span class="h-px w-10 bg-sand-400"></span>
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-sand-500">POS 020/POS/FASILKOM/2026</p>
                </div>
                <h1 class="mt-[clamp(0.5rem,2vh,1.25rem)] font-serif text-[length:clamp(1.5rem,min(4.2vh,2.6vw),2.5rem)] font-bold leading-[1.3] text-ink-900">
                    Mendigitalkan alur pendaftaran sidang, dari pengajuan mahasiswa hingga nilai lengkap di SIMAK.
                </h1>
                <p class="mt-[clamp(0.5rem,2vh,1.5rem)] max-w-lg text-[length:clamp(0.8125rem,1.9vh,0.9375rem)] leading-relaxed text-ink-600">
                    16 langkah pada Bagan Alir POS &mdash; Mahasiswa, SekDep/Koor. Prodi, Penata, Pengelola Layanan, Pengadministrasi Perkantoran, dan Dosen Penguji &mdash; tercatat dan dapat ditelusuri di satu tempat.
                </p>
            </div>

            <div class="relative z-10 flex items-center gap-4">
                <span class="h-px w-10 bg-sand-400"></span>
                <p class="text-xs text-ink-700">Fakultas Ilmu Komputer &middot; Universitas Sriwijaya</p>
            </div>
        </div>

        {{-- Form login (kanan). Kalau layar sangat pendek, hanya panel ini yang scroll, bukan seluruh halaman. --}}
        <div class="flex w-full flex-1 flex-col bg-gradient-to-br from-sand-50 to-sand-100/60 px-6 py-8 lg:overflow-y-auto lg:px-10 lg:py-6">
            <div class="m-auto w-full max-w-[34rem]">
                {{-- Merek ringkas untuk layar kecil --}}
                <div class="mb-5 flex items-center gap-3 lg:hidden">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gold-400 font-serif text-sm font-bold text-ink-900">UAP</div>
                    <p class="font-serif text-lg font-bold text-ink-900">Sistem Pendaftaran UAP</p>
                </div>

                <div class="rounded-xl border border-sand-200 bg-sand-50 px-[clamp(1.5rem,3.5vw,3rem)] py-[clamp(1.25rem,4vh,2.5rem)] shadow-[0_24px_60px_-24px_rgba(118,87,50,0.28)]">
                    <h2 class="font-serif text-[length:clamp(1.5rem,4vh,2.25rem)] font-bold leading-tight text-ink-900">Masuk</h2>
                    <p class="mt-1.5 text-pretty text-[length:clamp(0.8125rem,1.9vh,1rem)] text-ink-600">Gunakan akun yang diberikan sesuai peran Anda pada alur POS.</p>

                    @if ($errors->any())
                        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.process') }}" class="mt-[clamp(1rem,3vh,1.5rem)] flex flex-col gap-[clamp(0.75rem,2vh,1.25rem)]">
                        @csrf

                        <div>
                            <label for="email" class="mhs-label font-serif font-semibold text-ink-900">Email</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
                                    <path d="m4.5 7.5 7.5 5.5 7.5-5.5" />
                                </svg>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                                       class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                       placeholder="nama@ta.test">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="mhs-label font-serif font-semibold text-ink-900">Kata Sandi</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
                                    <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
                                </svg>
                                <input id="password" type="password" name="password" required autocomplete="current-password"
                                       class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-11 placeholder:font-mono placeholder:text-xs"
                                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                                <button type="button" id="toggle-password" aria-label="Tampilkan kata sandi" aria-pressed="false"
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
                        </div>

                        <label class="flex items-center gap-2.5 font-serif text-sm text-ink-700">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-sand-300 bg-sand-50 text-ink-800 focus:ring-sand-400">
                            Ingat saya
                        </label>

                        <button type="submit" class="mhs-btn-primary w-full rounded-lg py-[clamp(0.5rem,1.6vh,0.75rem)] font-serif">Masuk</button>
                    </form>

                    <div class="mt-[clamp(0.75rem,2.5vh,1.5rem)] rounded-lg border border-sand-200 bg-sand-50 px-4 py-3">
                        <p class="font-serif text-xs font-semibold text-ink-900">
                            Akun demo (prototipe) &mdash; kata sandi:
                            <code class="ml-1 rounded bg-sand-200/60 px-1.5 py-0.5 font-mono text-[11px] font-normal text-ink-700">password</code>
                        </p>
                        <ul class="mt-1.5 columns-2 gap-x-6 font-serif text-xs leading-5 text-ink-700">
                            <li class="break-inside-avoid">mahasiswa@ta.test</li>
                            <li class="break-inside-avoid">penata@ta.test</li>
                            <li class="break-inside-avoid">administrasi@ta.test</li>
                            <li class="break-inside-avoid">admin@ta.test</li>
                            <li class="break-inside-avoid">sekdep@ta.test</li>
                            <li class="break-inside-avoid">pengelola@ta.test</li>
                            <li class="break-inside-avoid">dosen@ta.test</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tampilkan / sembunyikan kata sandi.
        (function () {
            var tombol = document.getElementById('toggle-password');
            var input = document.getElementById('password');
            if (! tombol || ! input) return;
            tombol.addEventListener('click', function () {
                var tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                tombol.setAttribute('aria-pressed', tampil ? 'true' : 'false');
                tombol.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
                tombol.querySelector('[data-eye="show"]').classList.toggle('hidden', tampil);
                tombol.querySelector('[data-eye="hide"]').classList.toggle('hidden', ! tampil);
            });
        })();
    </script>
</body>
</html>