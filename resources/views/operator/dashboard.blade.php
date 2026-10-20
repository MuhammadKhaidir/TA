@extends('layouts.app')

@section('title', 'Operator Akademik')
@section('page-title', 'Operator Akademik')
@section('page-subtitle', 'Verifikasi pengajuan, penerbitan SK TA, dan pemrosesan surat KP')

@section('content')
<div class="space-y-6">
    @if ($cakupanKosong)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Akun Anda belum diberi cakupan program studi, sehingga belum ada pengajuan yang dapat diproses. Minta Admin menetapkan cakupan prodi Anda.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Menunggu verifikasi', $ringkasan['verifikasi'], ['status' => 'diajukan']],
            ['Siap diproses / SK', $ringkasan['siap_proses'], ['status' => 'disetujui']],
            ['Perlu revisi mahasiswa', $ringkasan['perlu_revisi'], ['status' => 'perlu_revisi']],
            ['Surat KP berjalan', $ringkasan['surat'], null],
        ] as [$label, $jumlah, $tautan])
            <a @if ($tautan) href="{{ route('operator.dashboard', $tautan) }}" @else href="#surat-kp" @endif class="mhs-card p-5 transition hover:bg-white/90">
                <p class="mhs-eyebrow">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold text-ink-900">{{ $jumlah }}</p>
            </a>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('operator.dashboard') }}" class="mhs-card grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-6">
        <input type="text" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Cari nama / NIM" class="mhs-input lg:col-span-2">
        <select name="jenis" class="mhs-input">
            <option value="">Semua jenis</option>
            <option value="ta" @selected(($filter['jenis'] ?? '') === 'ta')>TA</option>
            <option value="kp" @selected(($filter['jenis'] ?? '') === 'kp')>KP</option>
        </select>
        <select name="prodi_id" class="mhs-input">
            <option value="">Semua prodi</option>
            @foreach ($prodis as $pr)<option value="{{ $pr->id }}" @selected((string) ($filter['prodi_id'] ?? '') === (string) $pr->id)>{{ $pr->kode }} — {{ $pr->nama }}</option>@endforeach
        </select>
        <select name="periode_id" class="mhs-input">
            <option value="">Semua periode</option>
            @foreach ($periodes as $pe)<option value="{{ $pe->id }}" @selected((string) ($filter['periode_id'] ?? '') === (string) $pe->id)>{{ $pe->nama }}</option>@endforeach
        </select>
        <select name="status" class="mhs-input">
            <option value="">Berjalan (tanpa arsip)</option>
            @foreach ($statusOpsi as $nilai => $label)<option value="{{ $nilai }}" @selected(($filter['status'] ?? '') === $nilai)>{{ $label }}</option>@endforeach
        </select>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="mhs-btn-primary">Terapkan filter</button>
            <a href="{{ route('operator.dashboard') }}" class="mhs-btn-secondary">Reset</a>
        </div>
    </form>

    {{-- Daftar pengajuan --}}
    <section class="mhs-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-sand-200 text-sm">
                <thead class="bg-sand-100/70 text-left text-xs font-semibold uppercase tracking-wide text-ink-600">
                    <tr>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Judul / Instansi</th>
                        <th class="px-4 py-3">Pembimbing</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-200/80">
                    @forelse ($daftar as $p)
                        <tr>
                            <td class="px-4 py-3"><p class="font-medium text-ink-900">{{ $p->mahasiswa->nama }}</p><p class="text-xs text-ink-500">{{ $p->mahasiswa->nim }} &middot; {{ $p->prodi->kode }}</p></td>
                            <td class="px-4 py-3 text-ink-800">{{ $p->jenis->singkat() }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-ink-800" title="{{ $p->judulTampil() }}">{{ $p->judulTampil() }}</td>
                            <td class="px-4 py-3 text-ink-800">{{ $p->dospem?->nama ?? '-' }}</td>
                            <td class="px-4 py-3">@include('partials.status-badge', ['status' => $p->status])</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('pengajuan.show', $p) }}" class="text-sm font-medium text-ink-900 underline">Buka</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-ink-500">Tidak ada pengajuan yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($daftar->hasPages())<div class="border-t border-sand-200 px-4 py-3">{{ $daftar->links() }}</div>@endif
    </section>

    {{-- Surat KP --}}
    <section id="surat-kp" class="mhs-card p-6">
        <h3 class="text-base font-semibold text-ink-900">Permohonan surat KP</h3>
        <ul class="mt-3 divide-y divide-sand-200/80">
            @forelse ($surats as $s)
                <li class="py-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-ink-900">{{ $s->jenisSurat->nama }} &middot; {{ $s->pengajuan->mahasiswa->nama }} ({{ $s->pengajuan->mahasiswa->nim }})</p>
                            @if ($s->catatan_mahasiswa)<p class="text-xs text-ink-600">Keterangan mahasiswa: {{ $s->catatan_mahasiswa }}</p>@endif
                            <p class="text-xs text-ink-500">Diajukan {{ $s->created_at->translatedFormat('d M Y H:i') }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $s->status->badgeClasses() }}">{{ $s->status->label() }}</span>
                    </div>
                    <form method="POST" action="{{ route('operator.surat.proses', $s) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                        @csrf
                        <select name="tujuan" class="mhs-input">
                            <option value="diproses">Tandai diproses</option>
                            <option value="selesai">Selesai (unggah surat)</option>
                            <option value="ditolak">Tolak</option>
                        </select>
                        <input type="text" name="nomor_surat" placeholder="Nomor surat (opsional)" class="mhs-input" maxlength="255">
                        <input type="text" name="catatan" placeholder="Catatan" class="mhs-input" maxlength="2000">
                        <input type="file" name="berkas" class="mhs-input text-xs text-ink-600 file:mr-2 file:rounded-lg file:border-0 file:bg-sand-200 file:px-2 file:py-1 file:text-xs">
                        <div class="sm:col-span-4"><button type="submit" class="mhs-btn-secondary">Simpan</button></div>
                    </form>
                </li>
            @empty
                <li class="py-4 text-sm text-ink-500">Tidak ada permohonan surat yang menunggu.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
