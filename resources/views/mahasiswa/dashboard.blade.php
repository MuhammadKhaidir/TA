@extends('layouts.app')

@section('title', 'Dashboard Mahasiswa')
@section('page-title', 'Dashboard Mahasiswa')
@section('page-subtitle', $mahasiswa ? $mahasiswa->nim . ' &middot; ' . $mahasiswa->prodi : null)

@section('content')
    @php
        $sidangAktif = $sidangs->first(fn ($s) => $s->status !== \App\Enums\SidangStatus::Selesai);
    @endphp

    @if (! $mahasiswa)
        <div class="card p-6 text-sm text-slate-500">
            Akun Anda belum tertaut ke data mahasiswa. Hubungi Administrator.
        </div>
    @else
        @if ($sidangAktif)
            <div class="card mb-6 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $sidangAktif->judul_ta ?? 'Sidang ' . $sidangAktif->jenis->label() }}</h3>
                        <p class="text-sm text-slate-500">{{ $sidangAktif->jenis->label() }} &middot; Diajukan {{ $sidangAktif->tanggal_pengajuan->translatedFormat('d M Y') }}</p>
                    </div>
                    @include('partials.status-badge', ['status' => $sidangAktif->status])
                </div>

                @if ($sidangAktif->status === \App\Enums\SidangStatus::Ditunda)
                    <p class="mb-4 text-sm text-rose-600">
                        Pengajuan ditunda: Anda baru melakukan {{ $mahasiswa->jumlah_konsultasi }} dari minimal {{ \App\Models\Mahasiswa::MINIMAL_KONSULTASI }} kali konsultasi. SekDep/Koor. Prodi akan memperbarui data ini setelah syarat terpenuhi.
                    </p>
                @elseif ($sidangAktif->tanggal_sidang)
                    <p class="mb-4 text-sm text-slate-600">
                        Jadwal sidang: <span class="font-medium text-slate-900">{{ $sidangAktif->tanggal_sidang->translatedFormat('d M Y') }} pukul {{ \Illuminate\Support\Carbon::parse($sidangAktif->jam_sidang)->format('H:i') }}</span>
                        di {{ $sidangAktif->tempat ?? '-' }}.
                    </p>
                @else
                    <p class="mb-4 text-sm text-slate-500">Menunggu proses lebih lanjut oleh {{ $sidangAktif->status->labelPerananBerikutnya() }}.</p>
                @endif

                <a href="{{ route('sidang.show', $sidangAktif) }}" class="btn-primary">Lihat Detail &amp; Riwayat Proses</a>
            </div>
        @else
            <div class="card mb-6 p-5">
                <h3 class="mb-1 text-lg font-semibold text-slate-900">Ajukan Sidang Tugas Akhir</h3>
                <p class="mb-4 text-sm text-slate-500">Langkah 1 &mdash; lengkapi berkas persyaratan sebelum diserahkan ke SekDep/Koor. Prodi.</p>

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
                    <p class="text-xs text-slate-400">Syarat: minimal {{ \App\Models\Mahasiswa::MINIMAL_KONSULTASI }} kali konsultasi (tercatat: {{ $mahasiswa->jumlah_konsultasi }} kali). Jika belum terpenuhi, pengajuan akan ditunda otomatis.</p>
                    <button type="submit" class="btn-primary">Ajukan Sidang</button>
                </form>
            </div>
        @endif

        @if ($sidangs->isNotEmpty())
            <div class="card p-5">
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
