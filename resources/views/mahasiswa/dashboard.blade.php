@extends('layouts.app')

@section('title', 'Dashboard Mahasiswa')
@section('page-title', 'Dashboard Mahasiswa')
@section('page-subtitle', $mahasiswa ? $mahasiswa->nim . ' &middot; ' . $mahasiswa->prodi : null)

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
    @endphp

    @if (! $mahasiswa)
        <div class="card flex items-start gap-3 p-6">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="text-sm font-medium text-slate-900">Akun belum tertaut</p>
                <p class="mt-1 text-sm text-slate-500">
                    Akun Anda belum tertaut ke data mahasiswa. Hubungi Administrator.
                </p>
            </div>
        </div>
    @else
        {{-- Ringkasan --}}
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Konsultasi Pembimbingan</p>
                <p class="mt-2 text-xl font-semibold text-slate-900">
                    {{ $mahasiswa->jumlah_konsultasi }}
                    <span class="text-sm font-normal text-slate-400">/ {{ $minimalKonsultasi }} kali</span>
                </p>
                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $memenuhiSyarat ? 'bg-emerald-500' : 'bg-amber-500' }}"
                         style="width: {{ $persenKonsultasi }}%"></div>
                </div>
                <p class="mt-2 text-xs {{ $memenuhiSyarat ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $memenuhiSyarat ? 'Syarat minimum terpenuhi' : 'Belum memenuhi syarat minimum' }}
                </p>
            </div>

            <div class="card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pengajuan Aktif</p>
                @if ($sidangAktif)
                    <p class="mt-2 text-xl font-semibold text-slate-900">{{ $sidangAktif->jenis->label() }}</p>
                    <div class="mt-2">
                        @include('partials.status-badge', ['status' => $sidangAktif->status])
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}
                    </p>
                @else
                    <p class="mt-2 text-xl font-semibold text-slate-900">Belum ada</p>
                    <p class="mt-2 text-xs text-slate-500">Belum ada pengajuan yang sedang berjalan.</p>
                @endif
            </div>

            <div class="card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Jadwal Sidang</p>
                @if ($sidangAktif?->tanggal_sidang)
                    <p class="mt-2 text-xl font-semibold text-slate-900">
                        {{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        @if ($sidangAktif->jam_sidang)
                            Pukul {{ \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') }} &middot;
                        @endif
                        {{ $sidangAktif->tempat ?? '-' }}
                    </p>
                @else
                    <p class="mt-2 text-xl font-semibold text-slate-900">Belum dijadwalkan</p>
                    <p class="mt-2 text-xs text-slate-500">Jadwal akan tampil di sini setelah ditetapkan.</p>
                @endif
            </div>
        </div>

        {{--
            Menu proses mahasiswa. Setiap menu = 1 kartu accordion: syarat,
            status pengajuan yang sudah berjalan (bila ada), dan form aksi.
            Menu proses lain cukup ditambah dengan pola yang sama di bawah ini.
        --}}
        <div class="space-y-4">
            <x-menu-proses
                title="Pengajuan Sidang Tugas Akhir"
                subtitle="Ujian Komprehensif / Sidang Skripsi · Langkah 1 Bagan Alir POS 020/POS/FASILKOM/2026"
                :badge="$sidangAktif?->status"
                :open="true"
            >
                <x-slot:syarat>
                    <li class="flex items-start gap-3">
                        @if ($memenuhiSyarat)
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0 text-rose-500">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                            </svg>
                        @endif
                        <div>
                            <p class="font-medium text-slate-900">Konsultasi pembimbingan</p>
                            <p class="mt-0.5 text-slate-600">
                                Minimal {{ $minimalKonsultasi }} kali &mdash; tercatat
                                <strong class="font-semibold text-slate-900">{{ $mahasiswa->jumlah_konsultasi }} kali</strong>.
                                @unless ($memenuhiSyarat)
                                    Belum terpenuhi, sehingga pengajuan akan otomatis berstatus
                                    <em>Ditunda</em> sampai SekDep/Koor. Prodi memperbarui data konsultasi Anda.
                                @endunless
                            </p>
                        </div>
                    </li>

                    @foreach ($berkasSyarat as $berkas)
                        <li class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0 text-slate-400">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1Zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium text-slate-900">{{ $berkas }}</p>
                                <p class="mt-0.5 text-slate-600">
                                    Berkas PDF. Boleh disusulkan setelah pengajuan bila belum siap.
                                </p>
                            </div>
                        </li>
                    @endforeach
                </x-slot:syarat>

                @if ($sidangAktif)
                    <x-slot:keterangan>
                        <div class="rounded-lg border border-slate-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $sidangAktif->judul_ta ?? 'Sidang ' . $sidangAktif->jenis->label() }}
                                </p>
                                <span class="text-xs text-slate-500">
                                    Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}
                                </span>
                            </div>

                            <div class="space-y-4 px-4 py-4">
                                @if ($sidangAktif->status === \App\Enums\SidangStatus::Ditunda)
                                    <div class="flex items-start gap-3 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                        </svg>
                                        <p>
                                            Pengajuan ditunda: baru {{ $mahasiswa->jumlah_konsultasi }} dari minimal
                                            {{ $minimalKonsultasi }} kali konsultasi.
                                        </p>
                                    </div>
                                @elseif ($sidangAktif->tanggal_sidang)
                                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs text-slate-500">Tanggal Sidang</dt>
                                            <dd class="mt-0.5 font-medium text-slate-900">
                                                {{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500">Waktu</dt>
                                            <dd class="mt-0.5 font-medium text-slate-900">
                                                {{ $sidangAktif->jam_sidang ? \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') : '-' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500">Tempat</dt>
                                            <dd class="mt-0.5 font-medium text-slate-900">{{ $sidangAktif->tempat ?? '-' }}</dd>
                                        </div>
                                    </dl>
                                @else
                                    <div class="flex items-start gap-3 text-sm text-slate-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 shrink-0 text-slate-400">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                                        </svg>
                                        <p>Menunggu proses lebih lanjut oleh {{ $sidangAktif->status->labelPerananBerikutnya() }}.</p>
                                    </div>
                                @endif

                                <div>
                                    <a href="{{ route('sidang.show', $sidangAktif) }}" class="btn-secondary !py-1.5 text-xs">
                                        Lihat Detail &amp; Riwayat Proses
                                    </a>
                                </div>
                            </div>
                        </div>
                    </x-slot:keterangan>
                @else
                    <form method="POST" action="{{ route('mahasiswa.sidang.ajukan') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div>
                            <h5 class="mb-3 text-sm font-semibold text-slate-900">Data Pengajuan</h5>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label">Jenis Ujian</label>
                                    <select name="jenis" class="field-input" required>
                                        <option value="komprehensif" @selected(old('jenis') === 'komprehensif')>Ujian Komprehensif</option>
                                        <option value="skripsi" @selected(old('jenis') === 'skripsi')>Sidang Skripsi</option>
                                    </select>
                                    @error('jenis')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="field-label">Judul Tugas Akhir</label>
                                    <input type="text" name="judul_ta" value="{{ old('judul_ta') }}" class="field-input" placeholder="Judul TA Anda">
                                    @error('judul_ta')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div>
                            <h5 class="text-sm font-semibold text-slate-900">Berkas Pendukung</h5>
                            <p class="mb-3 mt-0.5 text-xs text-slate-500">
                                Berkas yang belum siap dapat disusulkan setelah pengajuan.
                            </p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                @foreach ([
                                    ['dkn_file', 'DKN (PDF)'],
                                    ['usep_file', 'Bukti Kelulusan USEP (PDF)'],
                                    ['sk_pembimbing_file', 'SK Pembimbing TA (PDF)'],
                                ] as [$nama, $label])
                                    <div>
                                        <label class="field-label">{{ $label }}</label>
                                        <input type="file" name="{{ $nama }}" accept="application/pdf"
                                               class="field-input text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                                        @error($nama)
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500">
                                @if ($memenuhiSyarat)
                                    Data konsultasi Anda memenuhi syarat minimum.
                                @else
                                    Konsultasi belum memenuhi minimum, pengajuan akan berstatus Ditunda.
                                @endif
                            </p>
                            <button type="submit" class="btn-primary">Ajukan Sidang</button>
                        </div>
                    </form>
                @endif
            </x-menu-proses>
        </div>

        {{-- Riwayat --}}
        @if ($sidangs->isNotEmpty())
            <div class="card mt-6 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-900">Riwayat Pengajuan</h3>
                    <span class="text-xs text-slate-500">{{ $sidangs->count() }} pengajuan</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Pengajuan</th>
                                <th class="px-6 py-3">Tanggal Diajukan</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sidangs as $s)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $s->judul_ta ?? $s->jenis->label() }}</p>
                                        @if ($s->judul_ta)
                                            <p class="text-xs text-slate-500">{{ $s->jenis->label() }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                        {{ $s->tanggal_pengajuan->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-6 py-3.5">
                                        @include('partials.status-badge', ['status' => $s->status])
                                    </td>
                                    <td class="px-6 py-3.5 text-right">
                                        <a href="{{ route('sidang.show', $s) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a>
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