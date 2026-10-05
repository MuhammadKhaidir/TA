{{--
    Halaman registrasi. Disalin dari auth/login.blade.php; yang berbeda HANYA isi kartu form.
    - Desktop (lg ke atas): tinggi persis 1 layar, tanpa scroll halaman. Ukuran teks, jarak, dan input
      ikut mengecil/membesar mengikuti tinggi layar (clamp + vh).
    - Layar kecil: panel foto disembunyikan, halaman boleh scroll biasa.
    Latar panel kiri: slideshow public/images/UnsriBG.png + FasilkomUnsri.png (ganti tiap 5 detik, transisi opacity),
    tampil UTUH (tanpa crop); hanya tepi atas dan kanannya dilembutkan.
    Panel kiri = setengah layar, tanpa warna sendiri dan tanpa garis pembatas: gradasi krem dipasang di
    pembungkus halaman sehingga kiri dan kanan satu warna.
    Butuh palet sand/ink dan kelas mhs-* dari patch dashboard mahasiswa.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar &mdash; Sistem Pendaftaran Ujian Akhir Program</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-sand-50 font-sans text-ink-900 antialiased">
    @php
        // Foto latar panel kiri, bergantian tiap 5 detik. Tambah nama berkas di sini kalau mau lebih banyak.
        $fotoLatar = collect(['UnsriBG.png', 'FasilkomUnsri.png'])
            ->filter(fn ($berkas) => file_exists(public_path('images/' . $berkas)))
            ->map(fn ($berkas) => asset('images/' . $berkas))
            ->values()
            ->all();
    @endphp

    <div class="flex min-h-screen bg-gradient-to-br from-sand-50 to-sand-100/60 lg:h-screen lg:overflow-hidden">
        {{-- Panel informasi (kiri) --}}
        <div class="relative hidden w-1/2 shrink-0 flex-col overflow-hidden px-[clamp(2rem,5vw,4rem)] py-[clamp(1.25rem,4.5vh,3rem)] lg:flex">
            {{-- Foto latar (slideshow): tiap foto tampil utuh, tidak di-crop. Tepi atas (12%) dan kanan (15%)
                 dilembutkan supaya menyatu dengan latar. Pergantian foto: lihat skrip di bawah. --}}
            @if (count($fotoLatar) > 0)
                <div class="pointer-events-none absolute inset-x-0 bottom-0 grid">
                    @foreach ($fotoLatar as $i => $url)
                        <div data-slide
                             class="col-start-1 row-start-1 self-end transition-opacity duration-1000 ease-in-out motion-reduce:transition-none {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}"
                             style="-webkit-mask-image: linear-gradient(to bottom, transparent 0%, #000 12%); mask-image: linear-gradient(to bottom, transparent 0%, #000 12%);">
                            <img src="{{ $url }}" alt="" class="block h-auto max-h-screen w-full object-contain object-bottom"
                                 style="-webkit-mask-image: linear-gradient(to left, transparent 0%, #000 15%); mask-image: linear-gradient(to left, transparent 0%, #000 15%);">
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Ornamen sudut kanan atas --}}
            <svg class="pointer-events-none absolute right-0 top-0 hidden h-[36vh] w-auto text-gold-400 xl:block" viewBox="0 0 100 340" aria-hidden="true">
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

            {{-- Judul (tengah) --}}
            <div class="relative z-10 flex flex-1 flex-col items-center justify-center pb-[clamp(0rem,8vh,5rem)] text-center">
                <div class="flex items-center justify-center gap-4">
                    <span class="h-px w-10 bg-sand-400"></span>
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-sand-500">POS 020/POS/FASILKOM/2026</p>
                    <span class="h-px w-10 bg-sand-400"></span>
                </div>
                <h1 class="mt-[clamp(0.5rem,2vh,1.25rem)] max-w-[30rem] text-balance font-serif text-[length:clamp(1.75rem,min(5vh,3vw),3rem)] font-bold leading-[1.25] text-ink-900">
                    PENDAFTARAN TUGAS AKHIR MAHASISWA.
                </h1>
            </div>

            <div class="relative z-10 flex items-center gap-4">
                <span class="h-px w-10 bg-sand-400"></span>
                <p class="text-xs text-ink-700"></p>
            </div>
        </div>

        {{-- Form login (kanan). Kalau layar sangat pendek, hanya panel ini yang scroll, bukan seluruh halaman. --}}
        <div class="flex w-full flex-1 flex-col px-6 py-8 lg:overflow-y-auto lg:px-10 lg:py-6">
            <div class="m-auto w-full max-w-[34rem]">
                {{-- Merek ringkas untuk layar kecil --}}
                <div class="mb-5 flex items-center gap-3 lg:hidden">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gold-400 font-serif text-sm font-bold text-ink-900">UAP</div>
                    <p class="font-serif text-lg font-bold text-ink-900">Sistem Pendaftaran UAP</p>
                </div>

                <div class="rounded-xl border border-sand-200 bg-sand-50 px-[clamp(1.5rem,3.5vw,3rem)] py-[clamp(1.25rem,4vh,2.5rem)] shadow-[0_24px_60px_-24px_rgba(118,87,50,0.28)]">
                    <h2 class="font-serif text-[length:clamp(1.5rem,4vh,2.25rem)] font-bold leading-tight text-ink-900">Daftar</h2>
                    <p class="mt-1.5 text-pretty text-[length:clamp(0.8125rem,1.9vh,1rem)] text-ink-600">Buat akun mahasiswa untuk mengajukan sidang tugas akhir.</p>

                    @if ($errors->any())
                        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.process') }}" class="mt-[clamp(1rem,3vh,1.5rem)] flex flex-col gap-[clamp(0.75rem,2vh,1.25rem)]">
                        @csrf

                        <div class="grid grid-cols-1 gap-x-4 gap-y-[clamp(0.625rem,1.6vh,1rem)] sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="name" class="mhs-label font-serif font-semibold text-ink-900">Nama Lengkap</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="8.5" r="3.5" />
                                        <path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6" />
                                    </svg>
                                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="Nama sesuai KRS">
                                </div>
                            </div>
                            <div>
                                <label for="nim" class="mhs-label font-serif font-semibold text-ink-900">NIM</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
                                        <circle cx="9" cy="11" r="1.8" />
                                        <path d="M6.5 15.5c.5-1.4 1.5-2 2.5-2s2 .6 2.5 2M14 10h4M14 13.5h3" />
                                    </svg>
                                    <input id="nim" type="text" name="nim" value="{{ old('nim') }}" required autocomplete="off"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="09021182126001">
                                </div>
                            </div>
                            <div>
                                <label for="angkatan" class="mhs-label font-serif font-semibold text-ink-900">Angkatan</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3.5" y="5" width="17" height="15" rx="2.5" />
                                        <path d="M8 3v4M16 3v4M3.5 10h17" />
                                    </svg>
                                    <input id="angkatan" type="text" name="angkatan" value="{{ old('angkatan') }}" required autocomplete="off"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="2024">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="prodi" class="mhs-label font-serif font-semibold text-ink-900">Program Studi</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 6.5C6 5.5 9 5.5 12 7c3-1.5 6-1.5 8-.5v11c-2-1-5-1-8 .5-3-1.5-6-1.5-8-.5Z" />
                                        <path d="M12 7v11.5" />
                                    </svg>
                                    <input id="prodi" type="text" name="prodi" value="{{ old('prodi') }}" required autocomplete="off"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="Teknik Informatika">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="email" class="mhs-label font-serif font-semibold text-ink-900">Email</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
                                        <path d="m4.5 7.5 7.5 5.5 7.5-5.5" />
                                    </svg>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="nama@email.com">
                                </div>
                            </div>

                            <div>
                                <label for="password" class="mhs-label font-serif font-semibold text-ink-900">Kata Sandi</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
                                        <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
                                    </svg>
                                    <input id="password" type="password" name="password" required autocomplete="new-password"
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

                            <div>
                                <label for="password_confirmation" class="mhs-label font-serif font-semibold text-ink-900">Ulangi Kata Sandi</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
                                        <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
                                    </svg>
                                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                           class="mhs-input rounded-lg bg-sand-50 py-[clamp(0.5rem,1.6vh,0.75rem)] pl-11 pr-4 placeholder:font-mono placeholder:text-xs"
                                           placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="mhs-btn-primary w-full rounded-lg py-[clamp(0.5rem,1.6vh,0.75rem)] font-serif">Daftar</button>
                    </form>

                    <a href="{{ route('login') }}" class="mhs-btn-secondary mt-[clamp(0.5rem,1.5vh,0.75rem)] w-full rounded-lg py-[clamp(0.5rem,1.6vh,0.75rem)] font-serif">
                        Sudah punya akun? Masuk
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Ganti foto latar tiap 5 detik dengan transisi opacity.
        // Foto baru memudar masuk DI ATAS foto lama, baru foto lama disembunyikan (tidak ada kedipan).
        (function () {
            var slides = document.querySelectorAll('[data-slide]');
            if (slides.length < 2) return;
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            var i = 0;
            slides[0].style.zIndex = 2;
            setInterval(function () {
                var lama = slides[i];
                i = (i + 1) % slides.length;
                var baru = slides[i];

                baru.style.zIndex = 2;
                lama.style.zIndex = 1;
                baru.classList.replace('opacity-0', 'opacity-100');

                setTimeout(function () {
                    lama.classList.replace('opacity-100', 'opacity-0');
                }, 1000);
            }, 5000);
        })();
    </script>

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