@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    <x-page-header judul="Dashboard" sub="Ringkasan pengajuan Kerja Praktik dan Tugas Akhir Anda.">
        <x-slot:aksi>
            <a href="{{ route('mahasiswa.pengajuan.create', 'kp') }}" class="btn btn-secondary"><x-icon name="plus" class="h-4 w-4" />Ajukan KP</a>
            <a href="{{ route('mahasiswa.pengajuan.create', 'ta') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" />Ajukan TA</a>
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total Pengajuan" :nilai="$pengajuan->count()" />
        <x-stat label="Dalam Proses" :nilai="$dalamProses" hint="Diperiksa operator atau dosen" />
        <x-stat label="Perlu Revisi" :nilai="$perluRevisi->count()" hint="Menunggu perbaikan dari Anda" />
        <x-stat label="Selesai" :nilai="$selesai" hint="Sudah diarsipkan" />
    </div>

    @if ($perluRevisi->isNotEmpty())
        <div class="alert alert-peringatan mt-6">
            <p class="font-medium">Ada pengajuan yang perlu diperbaiki.</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($perluRevisi as $p)
                    <li>
                        <a href="{{ route('mahasiswa.pengajuan.show', $p) }}" class="underline">{{ $p->jenis->singkat() }}: {{ $p->judul }}</a>
                        ({{ $p->status->label() }})
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Pengajuan Terbaru</h2>
                <a href="{{ route('mahasiswa.status.index') }}" class="tautan text-sm">Lihat semua</a>
            </div>
            @if ($pengajuan->isEmpty())
                <x-empty pesan="Belum ada pengajuan. Mulai dengan mengajukan KP atau TA." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead>
                            <tr><th>Jenis</th><th>Judul</th><th>Status</th><th>Diperbarui</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($pengajuan->take(6) as $p)
                                <tr>
                                    <td class="font-medium">{{ $p->jenis->singkat() }}</td>
                                    <td><a href="{{ route('mahasiswa.pengajuan.show', $p) }}" class="tautan">{{ $p->judul }}</a></td>
                                    <td><x-status-badge :status="$p->status" /></td>
                                    <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="font-semibold text-slate-900">Dosen Pembimbing</h2></div>
                <dl class="divide-y divide-slate-100 px-5 py-2">
                    @foreach ([['Pembimbing KP', $pengajuanKp, 'KP'], ['Pembimbing TA', $pengajuanTa, 'TA']] as [$label, $p, $singkat])
                        <div class="dl-baris">
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if ($p === null)
                                    <span class="text-slate-500">Belum ada pengajuan {{ $singkat }}</span>
                                @else
                                    <span class="{{ $p->pembimbing_id ? 'font-medium' : '' }}">{{ $p->ringkasanPembimbing() }}</span>
                                    <div class="mt-1">
                                        <span class="badge {{ $p->status_pembimbing->badge() }}">{{ $p->status_pembimbing->label() }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $p->keteranganPembimbing() }}</div>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="font-semibold text-slate-900">Informasi Akademik</h2></div>
                <dl class="divide-y divide-slate-100 px-5 py-2">
                    <div class="dl-baris"><dt>Nama</dt><dd>{{ $user->name }}</dd></div>
                    <div class="dl-baris"><dt>NIM</dt><dd>{{ $user->nomor_induk ?? '-' }}</dd></div>
                    <div class="dl-baris"><dt>Prodi</dt><dd>{{ $user->programStudi?->namaLengkap() ?? '-' }}</dd></div>
                    <div class="dl-baris"><dt>Angkatan</dt><dd>{{ $user->angkatan ?? '-' }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
@endsection
