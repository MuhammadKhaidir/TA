@extends('layouts.app')

@section('title', 'Dashboard Mahasiswa')
@section('page-title', 'Dashboard Mahasiswa')
@section('page-subtitle', $mahasiswa ? $mahasiswa->nim . ' &middot; ' . $mahasiswa->prodi : null)

@section('content')
    @php
        $sidangAktif = $sidangs->first(fn ($s) => $s->status !== \App\Enums\SidangStatus::Selesai);
        $memenuhiSyarat = $mahasiswa?->memenuhiSyaratKonsultasi() ?? false;
    @endphp

    @if (! $mahasiswa)
        <div class="card p-6 text-sm text-slate-500">
            Akun Anda belum tertaut ke data mahasiswa. Hubungi Administrator.
        </div>
    @else
        {{--
            Menu proses mahasiswa. Setiap menu = 1 kartu yang bisa diklik
            (accordion): syarat, status pengajuan yang sudah berjalan (bila
            ada), dan tombol/form aksi untuk menjalankannya baru muncul
            setelah kartu dibuka. Badge status tetap terlihat walau kartu
            tertutup. Kalau nanti ada menu proses lain, tinggal tambah
            <x-menu-proses> baru di bawah ini dengan pola yang sama.
        --}}
        <div class="space-y-4">
            <x-menu-proses
                title="Pengajuan Sidang Tugas Akhir"
                subtitle="Ujian Komprehensif / Sidang Skripsi &middot; Langkah 1 Bagan Alir POS 020/POS/FASILKOM/2026"
                :badge="$sidangAktif?->status"
            >
                <x-slot:syarat>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 {{ $memenuhiSyarat ? 'text-emerald-600' : 'text-rose-500' }}">
                            {{ $memenuhiSyarat ? '✓' : '✗' }}
                        </span>
                        <span>
                            Minimal {{ \App\Models\Mahasiswa::MINIMAL_KONSULTASI }} kali konsultasi pembimbingan
                            &mdash; tercatat <strong>{{ $mahasiswa->jumlah_konsultasi }} kali</strong>.
                            @unless ($memenuhiSyarat)
                                Belum terpenuhi, sehingga pengajuan akan otomatis berstatus
                                <em>Ditunda</em> sampai SekDep/Koor. Prodi memperbarui data konsultasi Anda.
                            @endunless
                        </span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-slate-400">&bull;</span>
                        <span>DKN (Daftar Kumpulan Nilai) &mdash; berkas PDF. Boleh disusulkan setelah pengajuan bila belum siap.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-slate-400">&bull;</span>
                        <span>Bukti kelulusan USEP &mdash; berkas PDF. Boleh disusulkan setelah pengajuan bila belum siap.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-slate-400">&bull;</span>
                        <span>SK Pembimbing TA &mdash; berkas PDF. Boleh disusulkan setelah pengajuan bila belum siap.</span>
                    </li>
                </x-slot:syarat>

                @if ($sidangAktif)
                    <x-slot:keterangan>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-slate-800">
                                    {{ $sidangAktif->judul_ta ?? 'Sidang ' . $sidangAktif->jenis->label() }}
                                </p>
                                <span class="text-xs text-slate-400">
                                    Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}
                                </span>
                            </div>

                            @if ($sidangAktif->status === \App\Enums\SidangStatus::Ditunda)
                                <p class="text-sm text-rose-600">
                                    Pengajuan ditunda: baru {{ $mahasiswa->jumlah_konsultasi }} dari minimal
                                    {{ \App\Models\Mahasiswa::MINIMAL_KONSULTASI }} kali konsultasi.
                                </p>
                            @elseif ($sidangAktif->tanggal_sidang)
                                <p class="text-sm text-slate-600">
                                    Jadwal sidang:
                                    <span class="font-medium text-slate-900">
                                        {{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }}
                                        pukul {{ \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') }}
                                    </span>
                                    di {{ $sidangAktif->tempat ?? '-' }}.
                                </p>
                            @else
                                <p class="text-sm text-slate-500">
                                    Menunggu proses lebih lanjut oleh {{ $sidangAktif->status->labelPerananBerikutnya() }}.
                                </p>
                            @endif

                            <a href="{{ route('sidang.show', $sidangAktif) }}" class="btn-secondary mt-3 !py-1.5 text-xs">
                                Lihat Detail &amp; Riwayat Proses
                            </a>
                        </div>
                    </x-slot:keterangan>
                @else
                    <form method="POST" action="{{ route('mahasiswa.sidang.ajukan') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Jenis Ujian</label>
                                <select name="jenis" class="field-input" required>
                                    <option value="komprehensif">Ujian Komprehensif</option>
                                    <option value="skripsi">Sidang Skripsi</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Judul Tugas Akhir</label>
                                <input type="text" name="judul_ta" class="field-input" placeholder="Judul TA Anda">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="field-label">DKN (PDF)</label>
                                <input type="file" name="dkn_file" accept="application/pdf" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Bukti Kelulusan USEP (PDF)</label>
                                <input type="file" name="usep_file" accept="application/pdf" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">SK Pembimbing TA (PDF)</label>
                                <input type="file" name="sk_pembimbing_file" accept="application/pdf" class="field-input">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Ajukan Sidang</button>
                    </form>
                @endif
            </x-menu-proses>
        </div>

        @if ($sidangs->isNotEmpty())
            <div class="card mt-6 p-5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat Pengajuan</h3>
                <ul class="divide-y divide-slate-100">
                    @foreach ($sidangs as $s)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $s->judul_ta ?? $s->jenis->label() }}</p>
                                <p class="text-xs text-slate-400">Diajukan {{ $s->tanggal_pengajuan->translatedFormat('d M Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                @include('partials.status-badge', ['status' => $s->status])
                                <a href="{{ route('sidang.show', $s) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
@endsection