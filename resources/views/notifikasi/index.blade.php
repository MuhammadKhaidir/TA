@extends(auth()->user()->hasRole('mahasiswa') ? 'layouts.mahasiswa' : 'layouts.app')

@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')
@section('page-subtitle', $belumDibaca ? $belumDibaca.' belum dibaca' : 'Semua sudah dibaca')

@section('content')
<section class="mhs-card p-6">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-ink-900">Pemberitahuan terbaru</h2>
        @if ($belumDibaca)
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                <button type="submit" class="mhs-btn-secondary">Tandai semua dibaca</button>
            </form>
        @endif
    </div>

    <ul class="mt-4 divide-y divide-sand-200">
        @forelse ($notifikasis as $n)
            <li class="py-3">
                <form method="POST" action="{{ route('notifikasi.baca', $n) }}">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-3 text-left">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->sudahDibaca() ? 'bg-sand-300' : 'bg-rose-500' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $n->sudahDibaca() ? 'text-ink-700' : 'font-semibold text-ink-900' }}">{{ $n->judul }}</span>
                            @if ($n->isi)<span class="mt-0.5 block text-sm text-ink-600">{{ $n->isi }}</span>@endif
                            <span class="mt-0.5 block text-xs text-ink-500">{{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            </li>
        @empty
            <li class="py-8 text-center text-sm text-ink-500">Belum ada notifikasi.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $notifikasis->links() }}</div>
</section>
@endsection
