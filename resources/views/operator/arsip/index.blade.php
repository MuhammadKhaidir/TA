@extends('layouts.app')

@section('judul', 'Arsip Berkas')

@section('konten')
    <x-page-header judul="Arsip Berkas" sub="Berkas final KP dan TA yang sudah selesai diproses." />

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="q" class="label">Cari</label>
            <input id="q" name="q" type="text" value="{{ $filter['q'] ?? '' }}" placeholder="Judul, nama, atau NIM" class="input">
        </div>
        <div>
            <label for="jenis" class="label">Jenis</label>
            <select id="jenis" name="jenis" class="input">
                <option value="">Semua</option>
                @foreach (\App\Enums\JenisPengajuan::pilihan() as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filter['jenis'] ?? '') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="prodi" class="label">Program Studi</label>
            <select id="prodi" name="prodi" class="input">
                <option value="">Semua</option>
                @foreach ($prodi as $ps)
                    <option value="{{ $ps->id }}" @selected((string) ($filter['prodi'] ?? '') === (string) $ps->id)>{{ $ps->namaLengkap() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('operator.arsip.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Belum ada berkas yang diarsipkan." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Judul</th><th>Dokumen Resmi</th><th>Diarsipkan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                </td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td>{{ $p->dokumenResmi->count() }} berkas</td>
                                <td class="whitespace-nowrap">{{ $p->arsip?->diarsipkan_at?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="text-right"><a href="{{ route('operator.arsip.show', $p) }}" class="btn btn-secondary btn-sm">Buka</a></td>
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
