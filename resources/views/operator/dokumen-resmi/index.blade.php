@extends('layouts.app')

@section('judul', 'Dokumen Resmi')

@section('konten')
    <x-page-header judul="Dokumen Resmi" sub="Surat pengantar dan surat balasan TA, SK pembimbing, dan dokumen resmi lain untuk pengajuan yang sudah disetujui, lalu arsipkan." />

    <section class="card mb-6">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Surat TA Perlu Diproses</h2></div>
        @if ($perluPengantar->isEmpty() && $perluPeriksaBalasan->isEmpty())
            <x-empty pesan="Tidak ada surat pengantar yang perlu diterbitkan atau surat balasan yang perlu diperiksa." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Langkah</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($perluPeriksaBalasan as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->judul }}</td>
                                <td><span class="badge bg-amber-50 text-amber-800 ring-amber-200">Periksa surat balasan</span></td>
                                <td class="text-right"><a href="{{ route('operator.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Periksa</a></td>
                            </tr>
                        @endforeach
                        @foreach ($perluPengantar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }}</div>
                                </td>
                                <td>{{ $p->judul }}</td>
                                <td><span class="badge bg-sky-50 text-sky-700 ring-sky-200">Terbitkan surat pengantar</span></td>
                                <td class="text-right"><a href="{{ route('operator.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Terbitkan</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card mb-6">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Perlu Diproses</h2></div>
        @if ($perluDiproses->isEmpty())
            <x-empty pesan="Tidak ada pengajuan yang menunggu dokumen resmi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Judul</th><th>Pembimbing</th><th>Keterangan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($perluDiproses as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                </td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td>{{ $p->ringkasanPembimbing() }}</td>
                                <td class="text-xs text-slate-500">{{ $p->jenis === \App\Enums\JenisPengajuan::Ta ? ($p->hambatanSk() ?? 'SK pembimbing siap diterbitkan.') : 'Siap diproses.' }}</td>
                                <td class="text-right"><a href="{{ route('operator.dokumen-resmi.show', $p) }}" class="btn btn-primary btn-sm">Proses</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-header"><h2 class="font-semibold text-slate-900">Dokumen Resmi yang Sudah Diunggah</h2></div>
        @if ($terbit->isEmpty())
            <x-empty pesan="Belum ada dokumen resmi." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Dokumen</th><th>Nomor</th><th>Tanggal</th><th>Mahasiswa</th><th>Diunggah oleh</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($terbit as $r)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $r->jenisLabel() }}</td>
                                <td>{{ $r->nomor }}</td>
                                <td class="whitespace-nowrap">{{ $r->tanggal->translatedFormat('d M Y') }}</td>
                                <td>{{ $r->pengajuan->mahasiswa->name }}</td>
                                <td>{{ $r->pengunggah->name }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                                    <a href="{{ route('berkas.resmi', ['dokumenResmi' => $r, 'unduh' => 1]) }}" class="tautan ml-3">Unduh</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($terbit->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $terbit->links() }}</div>
            @endif
        @endif
    </section>
@endsection
