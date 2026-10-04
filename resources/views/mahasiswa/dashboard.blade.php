@extends('layouts.mahasiswa')

@section('title', 'Dashboard Mahasiswa')
@section('page-title', 'Dashboard Mahasiswa')
@section('page-subtitle', 'Sistem Informasi Pengumpulan Tugas Akhir')
@section('data-ta-url', $sidangs->isNotEmpty() ? route('sidang.show', $sidangs->first()) : '')

@section('content')
    @php
        $sidangAktif = $sidangs->first(fn ($s) => $s->status !== \App\Enums\SidangStatus::Selesai);
        $memenuhiSyarat = $mahasiswa?->memenuhiSyaratKonsultasi() ?? false;
        $minimalKonsultasi = \App\Models\Mahasiswa::MINIMAL_KONSULTASI;
        $persenKonsultasi = $mahasiswa
            ? min(100, (int) round(($mahasiswa->jumlah_konsultasi / max(1, $minimalKonsultasi)) * 100))
            : 0;
        $berkasSyarat = [
            'DKN (Daftar Kumpulan Nilai)',
            'Bukti kelulusan USEP',
            'SK Pembimbing TA',
        ];
        $tautanAktif = $sidangAktif ? route('sidang.show', $sidangAktif) : '#pengajuan-sidang';
    @endphp

    @if (! $mahasiswa)
        <div class="mhs-card flex items-start gap-4 p-6">
            <x-mhs.icon name="alert" class="mt-0.5 h-6 w-6 shrink-0 text-sand-600" />
            <div>
                <p class="text-sm font-semibold text-ink-900">Akun belum tertaut</p>
                <p class="mt-1 text-sm text-ink-600">
                    Akun Anda belum tertaut ke data mahasiswa. Hubungi Administrator.
                </p>
            </div>
        </div>
    @else
        {{-- Ringkasan --}}
        <div class="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- Konsultasi --}}
            <a href="#pengajuan-sidang" class="mhs-card group relative flex items-start gap-4 p-6 transition hover:border-sand-300 hover:bg-white/90">
                <span class="mhs-icon-bubble h-12 w-12">
                    <x-mhs.icon name="doc" class="h-6 w-6 text-sand-600" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="mhs-eyebrow">Konsultasi Pembimbingan</p>
                    <p class="mt-1.5 text-3xl font-semibold text-ink-900">
                        {{ $mahasiswa->jumlah_konsultasi }}
                        <span class="text-lg font-normal text-ink-500">/ {{ $minimalKonsultasi }} kali</span>
                    </p>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-sand-200">
                        <div class="h-full rounded-full {{ $memenuhiSyarat ? 'bg-sand-500' : 'bg-amber-500' }}"
                             style="width: {{ $persenKonsultasi }}%"></div>
                    </div>
                    <p class="mt-2.5 text-sm {{ $memenuhiSyarat ? 'text-ink-600' : 'text-amber-700' }}">
                        {{ $memenuhiSyarat ? 'Syarat minimum terpenuhi' : 'Belum memenuhi syarat minimum' }}
                    </p>
                </div>
                <x-mhs.icon name="chevron-right" class="absolute right-5 top-6 h-4 w-4 text-ink-500 transition group-hover:translate-x-0.5" />
            </a>

            {{-- Pengajuan aktif --}}
            <a href="{{ $tautanAktif }}" class="mhs-card group relative flex items-start gap-4 p-6 transition hover:border-sand-300 hover:bg-white/90">
                <span class="mhs-icon-bubble h-12 w-12">
                    <x-mhs.icon name="calendar" class="h-6 w-6 text-sand-600" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="mhs-eyebrow">Pengajuan Aktif</p>
                    @if ($sidangAktif)
                        <p class="mt-1.5 text-2xl font-semibold text-ink-900">{{ $sidangAktif->jenis->label() }}</p>
                        <div class="mt-2.5">
                            @include('partials.mhs-status-pill', ['status' => $sidangAktif->status])
                        </div>
                        <p class="mt-2.5 text-sm text-ink-600">
                            Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}
                        </p>
                    @else
                        <p class="mt-1.5 text-2xl font-semibold text-ink-900">Belum ada</p>
                        <p class="mt-2.5 text-sm text-ink-600">Belum ada pengajuan yang sedang berjalan.</p>
                    @endif
                </div>
                <x-mhs.icon name="chevron-right" class="absolute right-5 top-6 h-4 w-4 text-ink-500 transition group-hover:translate-x-0.5" />
            </a>

            {{-- Jadwal sidang --}}
            <a href="{{ $tautanAktif }}" class="mhs-card group relative flex items-start gap-4 p-6 transition hover:border-sand-300 hover:bg-white/90">
                <span class="mhs-icon-bubble h-12 w-12">
                    <x-mhs.icon name="clock" class="h-6 w-6 text-sand-600" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="mhs-eyebrow">Jadwal Sidang</p>
                    @if ($sidangAktif?->tanggal_sidang)
                        <p class="mt-1.5 text-2xl font-semibold text-ink-900">
                            {{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }}
                        </p>
                        <p class="mt-2.5 text-sm text-ink-600">
                            @if ($sidangAktif->jam_sidang)
                                Pukul {{ \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') }} &middot;
                            @endif
                            {{ $sidangAktif->tempat ?? '-' }}
                        </p>
                    @else
                        <p class="mt-1.5 text-2xl font-semibold text-ink-900">Belum dijadwalkan</p>
                        <p class="mt-2.5 text-sm text-ink-600">Jadwal akan tampil di sini setelah ditetapkan.</p>
                    @endif
                </div>
                <x-mhs.icon name="chevron-right" class="absolute right-5 top-6 h-4 w-4 text-ink-500 transition group-hover:translate-x-0.5" />
            </a>
        </div>

        {{--
            Menu proses mahasiswa. Setiap menu = 1 kartu accordion: syarat,
            status pengajuan yang sudah berjalan (bila ada), dan form aksi.
            Menu proses lain cukup ditambah dengan pola yang sama di bawah ini.
        --}}
        <div class="space-y-5">
            <x-menu-proses
                id="pengajuan-sidang"
                title="Pengajuan Sidang Tugas Akhir"
                subtitle="Ujian Komprehensif / Sidang Skripsi - Langkah 1 Bagan Alir POS 020/POS/FASILKOM/2026"
                :badge="$sidangAktif?->status"
                :open="true"
            >
                <x-slot:syarat>
                    <li class="flex items-start gap-4 py-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center">
                            @if ($memenuhiSyarat)
                                <x-mhs.icon name="check-circle" class="h-7 w-7 text-emerald-600" />
                            @else
                                <x-mhs.icon name="x-circle" class="h-7 w-7 text-rose-500" />
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold text-ink-900">Konsultasi pembimbingan</p>
                            <p class="mt-0.5 text-sm text-ink-600">
                                Minimal {{ $minimalKonsultasi }} kali &mdash; tercatat
                                <strong class="font-semibold text-ink-900">{{ $mahasiswa->jumlah_konsultasi }} kali</strong>.
                                @unless ($memenuhiSyarat)
                                    Belum terpenuhi, sehingga pengajuan akan otomatis berstatus
                                    <em>Ditunda</em> sampai SekDep/Koor. Prodi memperbarui data konsultasi Anda.
                                @endunless
                            </p>
                        </div>
                    </li>

                    @foreach ($berkasSyarat as $berkas)
                        <li class="flex items-start gap-4 py-4">
                            <span class="mhs-icon-bubble h-11 w-11">
                                <x-mhs.icon name="doc" class="h-5 w-5 text-ink-600" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold text-ink-900">{{ $berkas }}</p>
                                <p class="mt-0.5 text-sm text-ink-600">
                                    Berkas PDF. Boleh disusulkan setelah pengajuan bila belum siap.
                                </p>
                            </div>
                        </li>
                    @endforeach
                </x-slot:syarat>

                @if ($sidangAktif)
                    <x-slot:keterangan>
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-xl border border-sand-200 bg-sand-100/80 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="mhs-eyebrow">Status Pengajuan</p>
                                    <p class="mt-1 text-[15px] text-ink-800">
                                        {{ $sidangAktif->judul_ta ?? 'Sidang ' . $sidangAktif->jenis->label() }}
                                    </p>
                                </div>
                                <p class="text-sm text-ink-600">
                                    Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}
                                </p>
                            </div>

                            @if ($sidangAktif->status === \App\Enums\SidangStatus::Ditunda)
                                <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                    <x-mhs.icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
                                    <p>
                                        Pengajuan ditunda: baru {{ $mahasiswa->jumlah_konsultasi }} dari minimal
                                        {{ $minimalKonsultasi }} kali konsultasi.
                                    </p>
                                </div>
                            @elseif ($sidangAktif->tanggal_sidang)
                                <dl class="grid grid-cols-1 gap-4 rounded-xl border border-sand-200 px-5 py-4 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt class="mhs-eyebrow">Tanggal Sidang</dt>
                                        <dd class="mt-1 font-medium text-ink-900">
                                            {{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mhs-eyebrow">Waktu</dt>
                                        <dd class="mt-1 font-medium text-ink-900">
                                            {{ $sidangAktif->jam_sidang ? \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') : '-' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mhs-eyebrow">Tempat</dt>
                                        <dd class="mt-1 font-medium text-ink-900">{{ $sidangAktif->tempat ?? '-' }}</dd>
                                    </div>
                                </dl>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 px-1 text-sm">
                                @if ($sidangAktif->status !== \App\Enums\SidangStatus::Ditunda && ! $sidangAktif->tanggal_sidang)
                                    <p class="text-ink-500">
                                        Menunggu proses lebih lanjut oleh {{ $sidangAktif->status->labelPerananBerikutnya() }}.
                                    </p>
                                @else
                                    <span></span>
                                @endif
                                <a href="{{ route('sidang.show', $sidangAktif) }}" class="inline-flex items-center gap-1 font-semibold text-ink-900 hover:underline">
                                    Lihat detail &amp; riwayat proses
                                    <x-mhs.icon name="chevron-right" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </x-slot:keterangan>
                @else
                    <form method="POST" action="{{ route('mahasiswa.sidang.ajukan') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div>
                            <h5 class="mb-3 text-sm font-semibold text-ink-900">Data Pengajuan</h5>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mhs-label">Jenis Ujian</label>
                                    <select name="jenis" class="mhs-input" required>
                                        <option value="komprehensif" @selected(old('jenis') === 'komprehensif')>Ujian Komprehensif</option>
                                        <option value="skripsi" @selected(old('jenis') === 'skripsi')>Sidang Skripsi</option>
                                    </select>
                                    @error('jenis')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mhs-label">Judul Tugas Akhir</label>
                                    <input type="text" name="judul_ta" value="{{ old('judul_ta') }}" class="mhs-input" placeholder="Judul TA Anda">
                                    @error('judul_ta')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div>
                            <h5 class="text-sm font-semibold text-ink-900">Berkas Pendukung</h5>
                            <p class="mb-3 mt-0.5 text-xs text-ink-500">
                                Berkas yang belum siap dapat disusulkan setelah pengajuan.
                            </p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                @foreach ([
                                    ['dkn_file', 'DKN (PDF)'],
                                    ['usep_file', 'Bukti Kelulusan USEP (PDF)'],
                                    ['sk_pembimbing_file', 'SK Pembimbing TA (PDF)'],
                                ] as [$nama, $label])
                                    <div>
                                        <label class="mhs-label">{{ $label }}</label>
                                        <input type="file" name="{{ $nama }}" accept="application/pdf"
                                               class="mhs-input text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                                        @error($nama)
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-sand-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-ink-600">
                                @if ($memenuhiSyarat)
                                    Data konsultasi Anda memenuhi syarat minimum.
                                @else
                                    Konsultasi belum memenuhi minimum, pengajuan akan berstatus Ditunda.
                                @endif
                            </p>
                            <button type="submit" class="mhs-btn-primary">Ajukan Sidang</button>
                        </div>
                    </form>
                @endif
            </x-menu-proses>
        </div>

        {{-- Riwayat --}}
        @if ($sidangs->isNotEmpty())
            <div id="riwayat-pengajuan" class="mhs-card mt-6 overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="mhs-icon-bubble h-10 w-10">
                            <x-mhs.icon name="history" class="h-5 w-5 text-ink-700" />
                        </span>
                        <h3 class="text-base font-semibold text-ink-900">Riwayat Pengajuan</h3>
                    </div>
                    <span class="text-sm text-ink-500">{{ $sidangs->count() }} pengajuan</span>
                </div>
                <div class="overflow-x-auto px-3 pb-3">
                    <table class="min-w-[36rem] w-full overflow-hidden rounded-xl text-left text-sm">
                        <thead class="bg-sand-200/70 text-xs font-semibold uppercase tracking-wider text-ink-600">
                            <tr>
                                <th class="px-4 py-3">Pengajuan</th>
                                <th class="px-4 py-3">Tanggal Diajukan</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-200/80">
                            @foreach ($sidangs as $s)
                                <tr class="transition hover:bg-sand-100/60">
                                    <td class="px-4 py-4">
                                        <p class="font-medium text-ink-900">{{ $s->judul_ta ?? $s->jenis->label() }}</p>
                                        @if ($s->judul_ta)
                                            <p class="text-xs text-ink-500">{{ $s->jenis->label() }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-ink-600">
                                        {{ $s->tanggal_pengajuan->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @include('partials.mhs-status-pill', ['status' => $s->status])
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('sidang.show', $s) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-900 hover:underline">
                                            Detail
                                            <x-mhs.icon name="chevron-right" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection