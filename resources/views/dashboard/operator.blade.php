@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    @php
        $hitung = fn (string $status) => (int) ($jumlah[$status] ?? 0);
    @endphp

    <x-page-header judul="Dashboard" sub="Ringkasan proses administrasi KP dan TA." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat label="Perlu Pembimbing" :nilai="$jumlahPerluPembimbing" hint="Belum ada dosen pembimbing atau ditolak dosen" :href="route('operator.pengajuan.index', ['pembimbing' => 'perlu'])" />
        <x-stat label="Perlu Verifikasi" :nilai="$hitung('diajukan')" hint="Menunggu pemeriksaan administrasi" :href="route('operator.verifikasi.index')" />
        <x-stat label="Diperiksa Dosen" :nilai="$hitung('menunggu_dosen')" />
        <x-stat label="Dalam Revisi" :nilai="$hitung('revisi_administrasi') + $hitung('revisi_akademik')" hint="Menunggu perbaikan mahasiswa" />
        <x-stat label="Perlu Dokumen Resmi" :nilai="$hitung('disetujui')" hint="Sudah disetujui dosen" :href="route('operator.dokumen-resmi.index')" />
        <x-stat label="Diarsipkan" :nilai="$hitung('diarsipkan')" :href="route('operator.arsip.index')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Antrean Verifikasi Administrasi</h2>
                <a href="{{ route('operator.verifikasi.index') }}" class="tautan text-sm">Lihat semua</a>
            </div>
            @if ($antreanVerifikasi->isEmpty())
                <x-empty pesan="Tidak ada pengajuan yang menunggu verifikasi." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Diajukan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($antreanVerifikasi as $p)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                    </td>
                                    <td>{{ $p->jenis->singkat() }}</td>
                                    <td class="whitespace-nowrap">{{ $p->diajukan_at?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="text-right"><a href="{{ route('operator.verifikasi.show', $p) }}" class="btn btn-primary btn-sm">Verifikasi</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Menunggu Dokumen Resmi</h2>
                <a href="{{ route('operator.dokumen-resmi.index') }}" class="tautan text-sm">Lihat semua</a>
            </div>
            @if ($perluDokumenResmi->isEmpty())
                <x-empty pesan="Tidak ada pengajuan yang menunggu dokumen resmi." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Disetujui</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($perluDokumenResmi as $p)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                    </td>
                                    <td>{{ $p->jenis->singkat() }}</td>
                                    <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                    <td class="text-right"><a href="{{ route('operator.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Proses</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Perlu Penetapan Dosen Pembimbing</h2>
                <a href="{{ route('operator.pengajuan.index', ['pembimbing' => 'perlu']) }}" class="tautan text-sm">Lihat semua</a>
            </div>
            @if ($perluPembimbing->isEmpty())
                <x-empty pesan="Semua pengajuan yang sudah dikirim memiliki dosen pembimbing atau sedang menunggu konfirmasi dosen." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Keadaan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($perluPembimbing as $p)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                    </td>
                                    <td>{{ $p->jenis->singkat() }}</td>
                                    <td>
                                        <span class="badge {{ $p->status_pembimbing->badge() }}">{{ $p->status_pembimbing->label() }}</span>
                                        <div class="mt-1 text-xs text-slate-500">{{ $p->keteranganPembimbing() }}</div>
                                    </td>
                                    <td class="text-right"><a href="{{ route('operator.pengajuan.show', $p) }}" class="btn btn-primary btn-sm">Tetapkan</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
