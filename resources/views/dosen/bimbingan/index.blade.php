@extends('layouts.app')

@section('judul', 'Mahasiswa Bimbingan')

@section('konten')
    <x-page-header judul="Mahasiswa Bimbingan" sub="Mahasiswa yang Anda bimbing, dan permintaan pembimbing TA yang menunggu jawaban Anda." />

    @if ($permintaan->isNotEmpty())
        <section class="card mb-6">
            <div class="card-header">
                <div>
                    <h2 class="font-semibold text-slate-900">Permintaan Pembimbing TA</h2>
                    <p class="text-sm text-slate-500">Mahasiswa memilih Anda sebagai calon pembimbing. Alasan wajib diisi bila Anda menolak.</p>
                </div>
                <span class="badge bg-blue-50 text-blue-700 ring-blue-200">{{ $permintaan->count() }} menunggu</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($permintaan as $p)
                    @php($baris = (int) old('baris') === $p->id)
                    <li class="px-5 py-4">
                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <div class="font-medium text-slate-900">{{ $p->judul }}</div>
                                <div class="mt-0.5 text-sm text-slate-600">
                                    {{ $p->mahasiswa->name }} ({{ $p->mahasiswa->nomor_induk ?? '-' }})
                                    &middot; {{ $p->mahasiswa->programStudi?->namaLengkap() ?? '-' }}@if ($p->mahasiswa->angkatan), angkatan {{ $p->mahasiswa->angkatan }}@endif
                                </div>
                                @if ($p->instansi)
                                    <div class="text-sm text-slate-600">Lokasi penelitian: {{ $p->instansi }}</div>
                                @endif
                                @if ($p->deskripsi)
                                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $p->deskripsi }}</p>
                                @endif
                                <div class="mt-2 text-xs text-slate-500">Dikirim {{ $p->diajukan_at?->translatedFormat('d M Y') ?? '-' }}</div>
                            </div>

                            <form method="POST" action="{{ route('dosen.bimbingan.konfirmasi', $p) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="baris" value="{{ $p->id }}">
                                <label for="catatan-{{ $p->id }}" class="label">Alasan (wajib jika menolak)</label>
                                <textarea id="catatan-{{ $p->id }}" name="catatan" rows="2" class="input">{{ $baris ? old('catatan') : '' }}</textarea>
                                @if ($baris)
                                    @error('catatan')<p class="galat">{{ $message }}</p>@enderror
                                    @error('keputusan')<p class="galat">{{ $message }}</p>@enderror
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="keputusan" value="terima" class="btn btn-primary btn-sm">Terima</button>
                                    <button type="submit" name="keputusan" value="tolak" class="btn btn-secondary btn-sm">Tolak</button>
                                </div>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[16rem] flex-1">
            <label for="q" class="label">Cari mahasiswa</label>
            <input id="q" name="q" type="text" value="{{ $filter['q'] ?? '' }}" placeholder="Nama atau NIM" class="input">
        </div>
        <button type="submit" class="btn btn-primary">Cari</button>
        <a href="{{ route('dosen.bimbingan.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada mahasiswa bimbingan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Angkatan</th><th>Pengajuan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->mahasiswa->programStudi?->namaLengkap() ?? '-' }}</td>
                                <td>{{ $p->mahasiswa->angkatan ?? '-' }}</td>
                                <td>{{ $p->jenis->singkat() }}: {{ $p->judul }}</td>
                                <td><x-status-badge :status="$p->status" /></td>
                                <td class="text-right"><a href="{{ route('dosen.pemeriksaan.show', $p) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($daftar->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $daftar->links() }}</div>
            @endif
        @endif
    </section>
@endsection
