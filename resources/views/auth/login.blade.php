<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &mdash; Sistem Pendaftaran Ujian Akhir Program</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-950 font-sans antialiased">
    <div class="flex min-h-screen">
        {{-- Panel informasi --}}
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-brand-950 p-12 text-white lg:flex">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(215,159,30,0.25),transparent_45%)]"></div>
            <div class="relative flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gold-500 text-base font-bold text-brand-950">UAP</div>
                <div class="leading-tight">
                    <p class="font-semibold">Sistem Pendaftaran</p>
                    <p class="text-sm text-brand-200/70">Ujian Akhir Program</p>
                </div>
            </div>

            <div class="relative max-w-md">
                <p class="text-sm font-medium uppercase tracking-wider text-gold-400">POS 020/POS/FASILKOM/2026</p>
                <h1 class="mt-3 text-3xl font-semibold leading-snug">Mendigitalkan alur pendaftaran sidang, dari pengajuan mahasiswa hingga nilai lengkap di SIMAK.</h1>
                <p class="mt-4 text-sm text-brand-200/70">16 langkah pada Bagan Alir POS &mdash; Mahasiswa, SekDep/Koor. Prodi, Penata, Pengelola Layanan, Pengadministrasi Perkantoran, dan Dosen Penguji &mdash; tercatat dan dapat ditelusuri di satu tempat.</p>
            </div>

            <p class="relative text-xs text-brand-200/50">Fakultas Ilmu Komputer &middot; Universitas Sriwijaya</p>
        </div>

        {{-- Form login --}}
        <div class="flex w-full flex-1 items-center justify-center bg-slate-50 px-6 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-900 text-sm font-bold text-gold-400">UAP</div>
                        <p class="font-semibold text-slate-900">Sistem Pendaftaran UAP</p>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold text-slate-900">Masuk</h2>
                <p class="mt-1 text-sm text-slate-500">Gunakan akun yang diberikan sesuai peran Anda pada alur POS.</p>

                @if ($errors->any())
                    <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.process') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="field-label">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="field-input" placeholder="nama@ta.test">
                    </div>
                    <div>
                        <label for="password" class="field-label">Kata Sandi</label>
                        <input id="password" type="password" name="password" required class="field-input" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                        Ingat saya
                    </label>
                    <button type="submit" class="btn-primary w-full">Masuk</button>
                </form>

                <div class="mt-8 rounded-lg border border-slate-200 bg-white p-4 text-xs text-slate-500">
                    <p class="mb-1.5 font-medium text-slate-600">Akun demo (prototipe) &mdash; kata sandi: <code class="rounded bg-slate-100 px-1 py-0.5">password</code></p>
                    <ul class="grid grid-cols-2 gap-x-3 gap-y-1">
                        <li>mahasiswa@ta.test</li>
                        <li>sekdep@ta.test</li>
                        <li>penata@ta.test</li>
                        <li>pengelola@ta.test</li>
                        <li>administrasi@ta.test</li>
                        <li>dosen@ta.test</li>
                        <li>admin@ta.test</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
