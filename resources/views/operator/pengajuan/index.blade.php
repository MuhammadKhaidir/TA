@extends('layouts.app')

@section('judul', 'Pengelolaan KP/TA')

@section('konten')
    <x-page-header judul="Pengelolaan KP/TA" sub="Seluruh pengajuan Kerja Praktik dan Tugas Akhir." />

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
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
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="input">
                <option value="">Semua</option>
                @foreach (\App\Enums\StatusPengajuan::pilihan() as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filter['status'] ?? '') === $nilai)>{{ $label }}</option>
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
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('operator.pengajuan.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Tidak ada pengajuan yang cocok dengan filter." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Mahasiswa</th><th>Jenis</th><th>Judul</th><th>Pembimbing</th><th>Status</th><th>Diperbarui</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $p->mahasiswa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->mahasiswa->nomor_induk }} &middot; {{ $p->mahasiswa->programStudi?->nama ?? '-' }}</div>
                                </td>
                                <td>{{ $p->jenis->singkat() }}</td>
                                <td>{{ $p->judul }}</td>
                                <td>{{ $p->pembimbing?->name ?? '-' }}</td>
                                <td><x-status-badge :status="$p->status" /></td>
                                <td class="whitespace-nowrap">{{ $p->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="text-right"><a href="{{ route('operator.pengajuan.show', $p) }}" class="btn btn-secondary btn-sm">Kelola</a></td>
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
