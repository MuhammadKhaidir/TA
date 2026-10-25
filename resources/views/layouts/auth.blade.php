{{--
    Kerangka halaman masuk (login), tema pasir.
    - Layar lebar (lg ke atas): dua panel, tinggi persis satu layar tanpa scroll halaman. Ukuran teks dan jarak
      mengikuti tinggi layar (clamp + vh). Panel kiri berisi foto latar dan identitas sistem, panel kanan berisi form.
    - Layar kecil: panel foto disembunyikan, halaman boleh scroll biasa.
    Foto latar: slideshow public/images/UnsriBG.png dan FasilkomUnsri.png (berganti tiap 5 detik, lihat resources/js/app.js).
    Logo: public/images/UnsriLogo.png. Semua gambar bersifat opsional: bila berkasnya tidak ada, bagian itu dilewati.
    Isi form disediakan halaman yang memakai layout ini lewat @section('konten').
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Masuk') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tema-pasir bg-sand-50 font-sans text-ink-900 antialiased">
    @php
        // Foto latar panel kiri. Tambah nama berkas di sini kalau ingin lebih banyak foto.
        $fotoLatar = collect(['UnsriBG.png', 'FasilkomUnsri.png'])
            ->filter(fn ($berkas) => file_exists(public_path('images/'.$berkas)))
            ->map(fn ($berkas) => asset('images/'.$berkas))
            ->values()
            ->all();

        $logo = file_exists(public_path('images/UnsriLogo.png')) ? asset('images/UnsriLogo.png') : null;
    @endphp

    <div class="flex min-h-screen bg-gradient-to-br from-sand-50 to-sand-100/60 lg:h-screen lg:overflow-hidden">
        {{-- Panel identitas (kiri) --}}
        <div class="relative hidden w-1/2 shrink-0 flex-col overflow-hidden px-[clamp(2rem,5vw,4rem)] py-[clamp(1.25rem,4.5vh,3rem)] lg:flex">
            {{-- Foto latar (slideshow): tiap foto tampil utuh, tidak di-crop. Tepi atas dan kanan dilembutkan
                 supaya menyatu dengan latar. --}}
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

            {{-- Motif pojok kiri bawah: gelombang + garis emas + bunga line-art --}}
            <svg class="pointer-events-none absolute bottom-0 left-0 z-[3] h-[clamp(7rem,22vh,13rem)] w-auto max-w-full" viewBox="0 0 640 260" preserveAspectRatio="xMinYMax meet" aria-hidden="true">
                <defs>
                    <linearGradient id="motifFadeGrad" gradientUnits="userSpaceOnUse" x1="360" y1="0" x2="640" y2="0">
                        <stop offset="0" stop-color="#fff" />
                        <stop offset="1" stop-color="#000" />
                    </linearGradient>
                    <mask id="motifFade" maskUnits="userSpaceOnUse" x="0" y="0" width="640" height="260">
                        <rect width="640" height="260" fill="url(#motifFadeGrad)" />
                    </mask>
                    <path id="motifPetal" d="M0 -8C-9 -20 -9 -34 0 -44C9 -34 9 -20 0 -8Z" />
                    <path id="motifPetalSm" d="M0 -8C-5 -14 -5 -20 0 -27C5 -20 5 -14 0 -8Z" />
                </defs>

                {{-- Gelombang --}}
                <path class="text-ink-900" fill="currentColor" opacity=".94"
                      d="M0 260V90C70 66 150 74 235 118C320 162 410 214 520 238C570 249 610 256 640 260Z" />

                {{-- Garis emas di atas gelombang, memudar ke kanan --}}
                <g class="text-gold-400" mask="url(#motifFade)" fill="none" stroke="currentColor" stroke-linecap="round">
                    <path stroke-width="6" d="M0 76C70 52 150 60 235 104C320 148 410 200 520 224C570 235 610 242 640 246" />
                    <path stroke-width="1.5" opacity=".7" d="M0 64C70 40 150 48 235 92C320 136 410 188 520 212C570 223 610 230 640 234" />
                </g>

                {{-- Bunga + sulur, gaya gambar garis --}}
                <g class="text-gold-400" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity=".85">
                    <g transform="translate(96 188)">
                        <circle r="5" />
                        <circle r="50" stroke-dasharray="1 6" opacity=".7" />
                        <use href="#motifPetal" />
                        <use href="#motifPetal" transform="rotate(45)" />
                        <use href="#motifPetal" transform="rotate(90)" />
                        <use href="#motifPetal" transform="rotate(135)" />
                        <use href="#motifPetal" transform="rotate(180)" />
                        <use href="#motifPetal" transform="rotate(225)" />
                        <use href="#motifPetal" transform="rotate(270)" />
                        <use href="#motifPetal" transform="rotate(315)" />
                        <use href="#motifPetalSm" transform="rotate(22.5)" />
                        <use href="#motifPetalSm" transform="rotate(67.5)" />
                        <use href="#motifPetalSm" transform="rotate(112.5)" />
                        <use href="#motifPetalSm" transform="rotate(157.5)" />
                        <use href="#motifPetalSm" transform="rotate(202.5)" />
                        <use href="#motifPetalSm" transform="rotate(247.5)" />
                        <use href="#motifPetalSm" transform="rotate(292.5)" />
                        <use href="#motifPetalSm" transform="rotate(337.5)" />
                    </g>
                    <path d="M148 188C176 176 202 188 216 206C228 221 248 222 256 210C263 199 254 190 245 193" />
                    <path d="M178 183C183 171 193 168 202 170C200 180 192 187 178 183Z" />
                    <path d="M214 205C224 198 234 199 240 205C233 212 223 213 214 205Z" />
                    <circle cx="272" cy="197" r="1.6" fill="currentColor" />
                    <circle cx="288" cy="206" r="1.2" fill="currentColor" />
                </g>
            </svg>

            {{-- Merek --}}
            <div class="relative z-10 flex items-center gap-4">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Logo Universitas Sriwijaya" class="h-[clamp(2.75rem,6.5vh,3.75rem)] w-auto shrink-0 object-contain">
                @else
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-ink-800 text-base font-bold text-white">FK</div>
                @endif
                <div class="h-10 w-px bg-ink-800/50"></div>
                <div class="leading-tight">
                    <p class="font-serif text-lg font-bold text-ink-900">Arsip Berkas KP dan TA</p>
                    <p class="text-base text-ink-700">Fasilkom Universitas Sriwijaya</p>
                </div>
            </div>

            {{-- Judul (tengah) --}}
            <div class="relative z-10 flex flex-1 flex-col items-center justify-center pb-[clamp(0rem,8vh,5rem)] text-center">
                <div class="flex items-center justify-center gap-4">
                    <span class="h-px w-10 bg-sand-400"></span>
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-sand-500">FAKULTAS ILMU KOMPUTER</p>
                    <span class="h-px w-10 bg-sand-400"></span>
                </div>
                <h1 class="mt-[clamp(0.5rem,2vh,1.25rem)] max-w-[30rem] text-balance font-serif text-[length:clamp(1.5rem,min(4.2vh,2.6vw),2.5rem)] font-bold leading-[1.25] text-ink-900">
                    Sistem Pengumpulan dan Arsip Digital Berkas KP dan TA
                </h1>
            </div>

            <div class="relative z-10 flex items-center gap-4">
                <span class="h-px w-10 bg-sand-400"></span>
            </div>
        </div>

        {{-- Form (kanan). Kalau layar sangat pendek, hanya panel ini yang scroll, bukan seluruh halaman.
             Padding vertikal dilonggarkan supaya bayangan kartu tidak terpotong oleh overflow-y-auto. --}}
        <div class="flex w-full flex-1 flex-col px-6 py-8 lg:overflow-y-auto lg:px-10 lg:py-[clamp(1.5rem,4vh,3rem)]">
            <div class="m-auto w-full max-w-[34rem]">
                {{-- Merek ringkas untuk layar kecil --}}
                <div class="mb-5 flex items-center gap-3 lg:hidden">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="Logo Universitas Sriwijaya" class="h-10 w-auto shrink-0 object-contain">
                    @else
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ink-800 text-sm font-bold text-white">FK</div>
                    @endif
                    <p class="font-serif text-lg font-bold text-ink-900">Arsip Berkas KP dan TA</p>
                </div>

                <div class="rounded-xl border border-sand-200 bg-white px-[clamp(1.5rem,3.5vw,3rem)] py-[clamp(1.25rem,4vh,2.5rem)] shadow-[0_28px_56px_-14px_rgba(70,50,25,0.30),0_10px_22px_-8px_rgba(70,50,25,0.16),0_0_0_1px_rgba(118,87,50,0.06)]">
                    @yield('konten')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
