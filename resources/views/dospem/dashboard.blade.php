@extends('layouts.app')

@section('title', 'Dosen Pembimbing')
@section('page-title', 'Dosen Pembimbing')
@section('page-subtitle', 'Mahasiswa bimbingan, pemeriksaan pengajuan, dan validasi bimbingan')

@section('content')
<div class="space-y-6">
    @if (! $dosen)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Akun Anda belum tertaut ke data dosen, sehingga belum ada mahasiswa bimbingan. Hubungi Admin.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="mhs-card p-5"><p class="mhs-eyebrow">Mahasiswa bimbingan</p><p class="mt-2 text-3xl font-semibold text-ink-900">{{ $pengajuans->pluck('mahasiswa_id')->unique()->count() }}</p></div>
        <div class="mhs-card p-5"><p class="mhs-eyebrow">Perlu diperiksa</p><p class="mt-2 text-3xl font-semibold text-ink-900">{{ $perluDiperiksa->count() }}</p></div>
        <div class="mhs-card p-5"><p class="mhs-eyebrow">Bimbingan menunggu validasi</p><p class="mt-2 text-3xl font-semibold text-ink-900">{{ $bimbinganMenunggu->count() }}</p></div>
    </div>

    <section class="mhs-card overflow-hidden">
        <div class="border-b border-sand-200 px-5 py-4"><h3 class="text-base font-semibold text-ink-900">Pengajuan mahasiswa bimbingan</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-sand-200 text-sm">
                <thead class="bg-sand-100/70 text-left text-xs font-semibold uppercase tracking-wide text-ink-600">
                    <tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Judul / Instansi</th><th class="px-4 py-3">Pemeriksaan akademik</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-sand-200/80">
                    @forelse ($pengajuans as $p)
                        <tr>
                            <td class="px-4 py-3"><p class="font-medium text-ink-900">{{ $p->mahasiswa->nama }}</p><p class="text-xs text-ink-500">{{ $p->mahasiswa->nim }} &middot; {{ $p->prodi->kode }}</p></td>
                            <td class="px-4 py-3">{{ $p->jenis->singkat() }}</td>
                            <td class="max-w-xs truncate px-4 py-3" title="{{ $p->judulTampil() }}">{{ $p->judulTampil() }}</td>
                            <td class="px-4 py-3">{{ $p->status_akademik->label() }}</td>
                            <td class="px-4 py-3">@include('partials.status-badge', ['status' => $p->status])</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('pengajuan.show', $p) }}" class="text-sm font-medium text-ink-900 underline">Buka</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-ink-500">Belum ada pengajuan yang ditugaskan kepada Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mhs-card p-6">
        <h3 class="text-base font-semibold text-ink-900">Bimbingan menunggu validasi</h3>
        <ul class="mt-3 divide-y divide-sand-200/80">
            @forelse ($bimbinganMenunggu as $b)
                <li class="py-4">
                    <p class="text-sm font-medium text-ink-900">{{ $b->mahasiswa->nama }} ({{ $b->mahasiswa->nim }}) &middot; {{ $b->jenis->singkat() }} &middot; {{ $b->tanggal->translatedFormat('d M Y') }}</p>
                    <p class="text-sm text-ink-700">{{ $b->topik }}</p>
                    @if ($b->catatan)<p class="text-xs text-ink-600">Catatan: {{ $b->catatan }}</p>@endif
                    @if ($b->tindak_lanjut)<p class="text-xs text-ink-600">Tindak lanjut: {{ $b->tindak_lanjut }}</p>@endif
                    @if ($b->bukti_path)<a class="text-xs font-medium underline" href="{{ route('bimbingan.bukti', $b) }}" target="_blank" rel="noopener">Lihat bukti</a>@endif
                    <form method="POST" action="{{ route('dospem.bimbingan.validasi', $b) }}" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-4">
                        @csrf
                        <select name="keputusan" class="mhs-input">
                            <option value="validasi">Validasi</option>
                            <option value="perbaiki">Minta diperbaiki</option>
                        </select>
                        <input type="text" name="catatan" maxlength="1000" placeholder="Catatan (wajib bila diminta perbaiki)" class="mhs-input sm:col-span-2">
                        <button type="submit" class="mhs-btn-secondary">Simpan</button>
                    </form>
                </li>
            @empty
                <li class="py-4 text-sm text-ink-500">Tidak ada bimbingan yang menunggu validasi.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
