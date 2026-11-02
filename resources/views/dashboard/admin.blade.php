@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    @php
        $hitungStatus = fn (string $status) => (int) ($jumlah[$status] ?? 0);
        $hitungRole = fn (string $role) => (int) ($perRole[$role] ?? 0);
    @endphp

    <x-page-header judul="Dashboard" sub="Ringkasan proses administrasi KP dan TA, data pengguna, dan pengaturan sistem." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Perlu Pembimbing" :nilai="$jumlahPerluPembimbing" hint="Belum ada dosen pembimbing atau ditolak dosen" :href="route('admin.pengajuan.index', ['pembimbing' => 'perlu'])" />
        <x-stat label="Perlu Verifikasi" :nilai="$hitungStatus('diajukan')" hint="Menunggu pemeriksaan administrasi" :href="route('admin.verifikasi.index')" />
        <x-stat label="Surat Pengantar TA" :nilai="$jumlahPerluPengantar" hint="TA terverifikasi yang belum diterbitkan suratnya" :href="route('admin.dokumen-resmi.index')" />
        <x-stat label="Surat Balasan TA" :nilai="$jumlahBalasanMenunggu" hint="Diunggah mahasiswa, menunggu diperiksa" :href="route('admin.dokumen-resmi.index')" />
        <x-stat label="Diperiksa Dosen" :nilai="$hitungStatus('menunggu_dosen')" />
        <x-stat label="Dalam Revisi" :nilai="$hitungStatus('revisi_administrasi') + $hitungStatus('revisi_akademik')" hint="Menunggu perbaikan mahasiswa" />
        <x-stat label="Perlu Dokumen Resmi" :nilai="$hitungStatus('disetujui')" hint="Sudah disetujui dosen" :href="route('admin.dokumen-resmi.index')" />
        <x-stat label="Diarsipkan" :nilai="$hitungStatus('diarsipkan')" :href="route('admin.arsip.index')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Antrean Verifikasi Administrasi</h2>
                <a href="{{ route('admin.verifikasi.index') }}" class="tautan text-sm">Lihat semua</a>
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
                                    <td class="text-right"><a href="{{ route('admin.verifikasi.show', $p) }}" class="btn btn-primary btn-sm">Verifikasi</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Surat TA Perlu Diproses</h2>
                <a href="{{ route('admin.dokumen-resmi.index') }}" class="tautan text-sm">Lihat semua</a>
            </div>
            @if ($antreanSurat->isEmpty())
                <x-empty pesan="Tidak ada surat pengantar atau surat balasan TA yang menunggu." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead><tr><th>Mahasiswa</th><th>Langkah</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($antreanSurat as $butir)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $butir['pengajuan']->mahasiswa->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $butir['pengajuan']->mahasiswa->nomor_induk }} &middot; {{ $butir['pengajuan']->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                    </td>
                                    <td>{{ $butir['langkah'] }}</td>
                                    <td class="text-right"><a href="{{ route('admin.dokumen-resmi.show', $butir['pengajuan']) }}" class="btn btn-primary btn-sm">Buka</a></td>
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
                <a href="{{ route('admin.dokumen-resmi.index') }}" class="tautan text-sm">Lihat semua</a>
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
                                    <td class="text-right"><a href="{{ route('admin.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Proses</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Perlu Penetapan Dosen Pembimbing</h2>
                <a href="{{ route('admin.pengajuan.index', ['pembimbing' => 'perlu']) }}" class="tautan text-sm">Lihat semua</a>
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
                                    <td class="text-right"><a href="{{ route('admin.pengajuan.show', $p) }}" class="btn btn-primary btn-sm">Tetapkan</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Mahasiswa" :nilai="$hitungRole('mahasiswa')" :href="route('admin.users.index', ['role' => 'mahasiswa'])" />
        <x-stat label="Dosen" :nilai="$hitungRole('dosen')" :href="route('admin.users.index', ['role' => 'dosen'])" />
        <x-stat label="Admin" :nilai="$hitungRole('admin')" :href="route('admin.users.index', ['role' => 'admin'])" />
        <x-stat label="Program Studi" :nilai="$jumlahProdi" :href="route('admin.program-studi.index')" />
        <x-stat label="Persyaratan Aktif" :nilai="$jumlahPersyaratan" hint="KP dan TA" :href="route('admin.persyaratan.index')" />
    </div>

    <section class="card mt-6">
        <div class="card-header">
            <h2 class="font-semibold text-slate-900">Aktivitas Terbaru</h2>
            <a href="{{ route('admin.log.index') }}" class="tautan text-sm">Lihat semua</a>
        </div>
        @if ($logTerbaru->isEmpty())
            <x-empty pesan="Belum ada aktivitas tercatat." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th></tr></thead>
                    <tbody>
                        @foreach ($logTerbaru as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td>{{ $log->deskripsi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
