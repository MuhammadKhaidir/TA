@extends(auth()->user()->hasRole('mahasiswa') ? 'layouts.mahasiswa' : 'layouts.app')

@section('title', 'Antrean sidang TA')
@section('page-title', 'Sidang Tugas Akhir')
@section('page-subtitle', 'Pengajuan sidang yang dapat Anda lihat')

@section('content')
<section class="mhs-card p-6">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mhs-label" for="status">Status</label>
            <select id="status" name="status" class="mhs-input">
                <option value="">Yang masih berjalan</option>
                @foreach ($statusOpsi as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filter['status'] ?? null) === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="mhs-btn-secondary">Terapkan</button>
    </form>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-xs uppercase tracking-wide text-ink-500">
                    <th class="py-2 pr-4">Mahasiswa</th>
                    <th class="py-2 pr-4">Judul</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Jadwal</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-200/80">
                @forelse ($daftar as $row)
                    <tr>
                        <td class="py-3 pr-4"><p class="font-medium text-ink-900">{{ $row->pengajuan->mahasiswa->nama }}</p><p class="text-xs text-ink-500">{{ $row->pengajuan->mahasiswa->nim }} &middot; {{ $row->pengajuan->prodi->kode }}</p></td>
                        <td class="py-3 pr-4 text-ink-700">{{ $row->pengajuan->judulTampil() }}</td>
                        <td class="py-3 pr-4"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $row->status->badgeClasses() }}">{{ $row->status->label() }}</span></td>
                        <td class="py-3 pr-4 text-ink-700">{{ $row->jadwal_mulai?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                        <td class="py-3 text-right"><a href="{{ route('sidang.show', $row) }}" class="mhs-btn-secondary">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-500">Tidak ada pengajuan sidang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $daftar->links() }}</div>
</section>
@endsection
