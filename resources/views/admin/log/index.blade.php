@extends('layouts.app')

@section('judul', 'Log Aktivitas')

@section('konten')
    <x-page-header judul="Log Aktivitas" sub="Jejak tindakan penting pengguna di dalam sistem." />

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label for="q" class="label">Cari</label>
            <input id="q" name="q" type="text" value="{{ $filter['q'] ?? '' }}" placeholder="Nama pengguna atau isi aktivitas" class="input">
        </div>
        <div>
            <label for="aksi" class="label">Kelompok aktivitas</label>
            <select id="aksi" name="aksi" class="input">
                <option value="">Semua</option>
                @foreach ($kelompok as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filter['aksi'] ?? '') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('admin.log.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <section class="card">
        @if ($daftar->isEmpty())
            <x-empty pesan="Tidak ada aktivitas yang cocok." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                                <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->aksi }}</td>
                                <td>{{ $log->deskripsi }}</td>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->ip_address ?? '-' }}</td>
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
