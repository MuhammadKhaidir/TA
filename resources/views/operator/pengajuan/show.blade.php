@extends('layouts.app')

@section('judul', 'Kelola Pengajuan')

@section('konten')
    <x-page-header :judul="$pengajuan->jenis->label().': '.$pengajuan->judul"
                   :sub="$pengajuan->mahasiswa->name.' ('.($pengajuan->mahasiswa->nomor_induk ?? '-').')'">
        <x-slot:aksi>
            <a href="{{ route('operator.pengajuan.index') }}" class="btn btn-secondary">Kembali</a>
            @can('verifikasi', $pengajuan)
                <a href="{{ route('operator.verifikasi.show', $pengajuan) }}" class="btn btn-primary">Verifikasi</a>
            @endcan
            @can('prosesDokumenResmi', $pengajuan)
                <a href="{{ route('operator.dokumen-resmi.show', $pengajuan) }}" class="btn btn-primary">Proses Dokumen Resmi</a>
            @endcan
            @if ($pengajuan->tahapSuratAktif() && ! auth()->user()->can('prosesDokumenResmi', $pengajuan))
                <a href="{{ route('operator.dokumen-resmi.show', $pengajuan) }}" class="btn btn-primary">Kelola Surat TA</a>
            @endif
            @if ($pengajuan->status === \App\Enums\StatusPengajuan::Diarsipkan)
                <a href="{{ route('operator.arsip.show', $pengajuan) }}" class="btn btn-primary">Lihat di Arsip</a>
            @endif
        </x-slot:aksi>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @can('kelola', $pengajuan)
                @php
                    $menggantikan = in_array($pengajuan->status_pembimbing, [\App\Enums\StatusPembimbing::Menunggu, \App\Enums\StatusPembimbing::Diterima], true);
                    $rekomendasi = $dosen->first(fn ($d) => ! $d->menolak && (int) $d->id !== (int) $pengajuan->pembimbing_id)?->id;
                @endphp
                <section class="card">
                    <div class="card-header">
                        <h2 class="font-semibold text-slate-900">Dosen Pembimbing</h2>
                        <span class="badge {{ $pengajuan->status_pembimbing->badge() }}">{{ $pengajuan->status_pembimbing->label() }}</span>
                    </div>
                    <div class="border-b border-slate-100 px-5 py-3 text-sm">
                        <div class="font-medium text-slate-900">{{ $pengajuan->ringkasanPembimbing() }}</div>
                        <div class="text-xs text-slate-500">{{ $pengajuan->keteranganPembimbing() }}</div>
                    </div>
                    <form method="POST" action="{{ route('operator.pengajuan.pembimbing', $pengajuan) }}" class="card-body space-y-4">
                        @csrf
                        @method('PATCH')

                        <p class="text-sm text-slate-600">
                            @if ($pengajuan->status_pembimbing === \App\Enums\StatusPembimbing::Ditolak)
                                Dosen pilihan mahasiswa menolak. Pilih pengganti dengan beban bimbingan paling sedikit.
                            @elseif ($menggantikan)
                                Menetapkan dosen lain akan menggantikan dosen yang dipilih mahasiswa. Catatan wajib diisi.
                            @else
                                Pilih dosen pembimbing. Dosen dengan bimbingan aktif paling sedikit berada di urutan teratas.
                            @endif
                        </p>

                        @error('pembimbing_id')<p class="galat">{{ $message }}</p>@enderror

                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="tabel">
                                <thead>
                                    <tr><th class="w-10"></th><th>Dosen</th><th>Program Studi</th><th>Bimbingan aktif</th><th>Permintaan menunggu</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($dosen as $d)
                                        @php($tidakBisa = $d->menolak || (int) $d->id === (int) $pengajuan->pembimbing_id)
                                        <tr @class(['bg-slate-50 text-slate-500' => $tidakBisa])>
                                            <td>
                                                <input type="radio" name="pembimbing_id" id="dosen-{{ $d->id }}" value="{{ $d->id }}"
                                                       @disabled($tidakBisa) @checked((int) old('pembimbing_id', $rekomendasi) === (int) $d->id)
                                                       class="h-4 w-4 accent-navy-700">
                                            </td>
                                            <td>
                                                <label for="dosen-{{ $d->id }}" class="font-medium text-slate-900">{{ $d->name }}</label>
                                                @if ($d->id === $rekomendasi)
                                                    <span class="badge ml-2 bg-emerald-50 text-emerald-700 ring-emerald-200">Bimbingan paling sedikit</span>
                                                @endif
                                                @if ($d->menolak)
                                                    <span class="badge ml-2 bg-red-50 text-red-700 ring-red-200">Menolak pengajuan ini</span>
                                                @elseif ((int) $d->id === (int) $pengajuan->pembimbing_id)
                                                    <span class="badge ml-2 bg-navy-50 text-navy-800 ring-navy-200">Pembimbing saat ini</span>
                                                @endif
                                            </td>
                                            <td>{{ $d->programStudi?->nama ?? '-' }}</td>
                                            <td>{{ $d->beban_aktif }}</td>
                                            <td>{{ $d->beban_menunggu }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <label for="catatan" class="label">Catatan{{ $menggantikan ? '' : ' (opsional)' }}</label>
                            <textarea id="catatan" name="catatan" rows="2" @required($menggantikan) class="input">{{ old('catatan') }}</textarea>
                            @error('catatan')<p class="galat">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary" @disabled($rekomendasi === null)>Tetapkan sebagai pembimbing</button>
                    </form>
                </section>
            @endcan

            @include('partials.pengajuan.surat-ta')

            @include('partials.pengajuan.dokumen')

            @if ($pengajuan->dokumenResmi->isNotEmpty())
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
