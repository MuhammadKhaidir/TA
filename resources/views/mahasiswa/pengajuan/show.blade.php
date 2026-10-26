@extends('layouts.app')

@section('judul', 'Detail Pengajuan '.$pengajuan->jenis->singkat())

@section('konten')
    @php
        $bolehUbah = auth()->user()->can('update', $pengajuan);
        $bolehHapus = auth()->user()->can('delete', $pengajuan);
        $draft = $pengajuan->status === \App\Enums\StatusPengajuan::Draft;
    @endphp

    <x-page-header :judul="$pengajuan->jenis->label().': '.$pengajuan->judul" :sub="$pengajuan->status->keterangan()">
        <x-slot:aksi>
            <a href="{{ route('mahasiswa.pengajuan.index', $pengajuan->jenis->value) }}" class="btn btn-secondary">Kembali</a>
            @if ($bolehUbah)
                <a href="{{ route('mahasiswa.pengajuan.edit', $pengajuan) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" />Ubah Data</a>
            @endif
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- TA: surat pengantar, surat balasan, dan SK pembimbing (kosong untuk KP dan untuk TA yang belum diverifikasi) --}}
            @include('partials.pengajuan.surat-ta')

            @include('partials.pengajuan.dokumen', ['unggah' => $bolehUbah])

            @if ($bolehUbah)
                <section class="card">
                    <div class="card-body flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xl text-sm text-slate-600">
                            @if ($draft)
                                Pastikan semua dokumen wajib sudah diunggah, lalu kirim pengajuan agar diperiksa operator akademik.
                            @else
                                Setelah semua dokumen yang ditandai revisi diunggah ulang, kirim kembali perbaikan Anda.
                            @endif
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($bolehHapus)
                                <form method="POST" action="{{ route('mahasiswa.pengajuan.destroy', $pengajuan) }}" data-konfirmasi="Hapus draft pengajuan ini beserta dokumen yang sudah diunggah?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary">Hapus draft</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('mahasiswa.pengajuan.ajukan', $pengajuan) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ $draft ? 'Kirim Pengajuan' : 'Kirim Ulang Perbaikan' }}</button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif

            @if ($pengajuan->dokumenResmi->isNotEmpty())
                {{-- Surat pengantar dan SK TA sudah tampil di kartu "Surat Tugas Akhir" di atas. --}}
                @include('partials.pengajuan.dokumen-resmi', [
                    'kecuali' => $pengajuan->tampilkanSuratTa() ? [\App\Enums\JenisPengajuan::SURAT_PENGANTAR_TA, \App\Enums\JenisPengajuan::SK_TA] : [],
                    'sembunyikanBilaKosong' => true,
                ])
            @endif
        </div>

        <div class="space-y-6">
            @include('partials.pengajuan.ringkasan')
            @include('partials.pengajuan.riwayat')
            @include('partials.pengajuan.riwayat-pembimbing')
        </div>
    </div>
@endsection
