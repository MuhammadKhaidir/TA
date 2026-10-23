@extends('layouts.app')

@section('judul', 'Status Pengajuan')

@section('konten')
    <x-page-header judul="Status Pengajuan" sub="Posisi terkini dan riwayat perubahan status seluruh pengajuan Anda." />

    @forelse ($daftar as $p)
        <div class="mb-8 grid gap-6 lg:grid-cols-2">
            <section class="card self-start">
                <div class="card-header">
                    <h2 class="font-semibold text-slate-900">{{ $p->jenis->label() }}</h2>
                    <x-status-badge :status="$p->status" />
                </div>
                <dl class="divide-y divide-slate-100 px-5 py-2">
                    <div class="dl-baris"><dt>Judul</dt><dd>{{ $p->judul }}</dd></div>
                    <div class="dl-baris"><dt>Dosen Pembimbing</dt><dd>{{ $p->pembimbing?->name ?? '-' }}</dd></div>
                    <div class="dl-baris"><dt>Langkah berikutnya</dt><dd class="text-slate-600">{{ $p->status->keterangan() }}</dd></div>
                </dl>
                <div class="border-t border-slate-200 px-5 py-3">
                    <a href="{{ route('mahasiswa.pengajuan.show', $p) }}" class="tautan text-sm">Buka detail dan dokumen</a>
                </div>
            </section>

            @include('partials.pengajuan.riwayat', ['pengajuan' => $p])
        </div>
    @empty
        <section class="card">
            <x-empty pesan="Belum ada pengajuan. Buat pengajuan KP atau TA dari menu di sebelah kiri." />
        </section>
    @endforelse
@endsection
