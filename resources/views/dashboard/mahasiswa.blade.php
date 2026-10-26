@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    <x-page-header judul="Dashboard" sub="Ringkasan pengajuan Kerja Praktik dan Tugas Akhir Anda.">
        <x-slot:aksi>
            <a href="{{ route('mahasiswa.pengajuan.create', 'kp') }}" class="btn btn-secondary"><x-pasir.icon name="plus" class="h-4 w-4" />Ajukan KP</a>
            <a href="{{ route('mahasiswa.pengajuan.create', 'ta') }}" class="btn btn-primary"><x-pasir.icon name="plus" class="h-4 w-4" />Ajukan TA</a>
        </x-slot:aksi>
    </x-page-header>

    {{-- Ringkasan: pengajuan KP, pengajuan TA (beserta dosen pembimbingnya), dan data akademik --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        @foreach ([['Kerja Praktik (KP)', 'Pembimbing KP', $pengajuanKp, 'KP', 'briefcase'], ['Tugas Akhir (TA)', 'Pembimbing TA', $pengajuanTa, 'TA', 'academic']] as [$judulKartu, $labelPembimbing, $p, $singkat, $ikon])
            @if ($p)
                <a href="{{ route('mahasiswa.pengajuan.show', $p) }}" class="card group relative flex items-start gap-4 p-6 transition hover:border-sand-300 hover:bg-white/90">
            @else
                <div class="card relative flex items-start gap-4 p-6">
            @endif
                <span class="ikon-gelembung h-12 w-12"><x-pasir.icon :name="$ikon" class="h-6 w-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="sub-label">{{ $judulKartu }}</p>
                    @if ($p)
                        <p class="mt-1.5 line-clamp-2 break-words text-lg font-semibold leading-snug text-ink-900" title="{{ $p->judul }}">{{ $p->judul }}</p>
                        <div class="mt-2.5"><x-status-badge :status="$p->status" /></div>
                    @else
                        <p class="mt-1.5 text-lg font-semibold text-ink-500">Belum diajukan</p>
                    @endif

                    <div class="mt-4 border-t border-sand-200 pt-3.5">
                        <p class="sub-label">{{ $labelPembimbing }}</p>
                        @if ($p === null)
                            <p class="mt-1.5 text-sm text-ink-500">Belum ada pengajuan {{ $singkat }}</p>
                        @else
                            <p class="mt-1.5 text-sm text-ink-900 {{ $p->pembimbing_id ? 'font-medium' : '' }}">{{ $p->ringkasanPembimbing() }}</p>
                            <div class="mt-1.5">
                                <span class="badge {{ $p->status_pembimbing->badge() }}">{{ $p->status_pembimbing->label() }}</span>
                            </div>
                            <p class="mt-1.5 text-xs text-ink-600">{{ $p->keteranganPembimbing() }}</p>
                        @endif
                    </div>

                    {{-- TA: posisi surat pengantar, surat balasan, dan SK pembimbing --}}
                    @if ($p && $p->tampilkanSuratTa())
                        <div class="mt-4 border-t border-sand-200 pt-3.5">
                            <p class="sub-label">Surat TA</p>
                            <ul class="mt-2.5 space-y-2.5">
                                @foreach ($p->tahapSurat() as $t)
                                    <li class="flex items-start gap-2.5 text-sm">
                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $t['status']->titik() }}" aria-hidden="true"></span>
                                        <span class="min-w-0">
                                            <span class="block font-medium leading-snug text-ink-900">{{ $t['judul'] }}</span>
                                            <span class="block text-xs text-ink-600">{{ $t['label'] }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                @if ($p)
                    <x-pasir.icon name="chevron-right" class="absolute right-5 top-6 h-4 w-4 text-ink-500 transition group-hover:translate-x-0.5" />
                @endif
            @if ($p)
                </a>
            @else
                </div>
            @endif
        @endforeach

        <div class="card flex items-start gap-4 p-6">
            <span class="ikon-gelembung h-12 w-12"><x-pasir.icon name="history" class="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="sub-label">Informasi Akademik</p>
                <p class="mt-1.5 truncate text-lg font-semibold text-ink-900">{{ $user->name }}</p>
                <dl class="mt-2.5 space-y-1 text-sm text-ink-600">
                    <div class="flex gap-2"><dt class="w-16 shrink-0 text-ink-500">NIM</dt><dd class="text-ink-900">{{ $user->nomor_induk ?? '-' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-16 shrink-0 text-ink-500">Prodi</dt><dd class="text-ink-900">{{ $user->programStudi?->namaLengkap() ?? '-' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-16 shrink-0 text-ink-500">Angkatan</dt><dd class="text-ink-900">{{ $user->angkatan ?? '-' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total Pengajuan" :nilai="$pengajuan->count()" />
        <x-stat label="Dalam Proses" :nilai="$dalamProses" hint="Diperiksa operator atau dosen" />
        <x-stat label="Perlu Revisi" :nilai="$perluRevisi->count()" hint="Menunggu perbaikan dari Anda" />
        <x-stat label="Selesai" :nilai="$selesai" hint="Sudah diarsipkan" />
    </div>

    @if ($suratPerluTindakan->isNotEmpty())
        <div class="alert alert-info mt-6">
            <p class="font-medium">Ada surat Tugas Akhir yang menunggu Anda.</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($suratPerluTindakan as $p)
                    <li>
                        <a href="{{ route('mahasiswa.pengajuan.show', $p) }}#surat-ta" class="underline">{{ $p->judul }}</a>:
                        {{ $p->balasanTerbaru() ? 'surat balasan perlu direvisi dan diunggah ulang' : 'unduh surat pengantar lalu unggah surat balasan' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

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

    <section class="card mt-6 overflow-hidden">
        <div class="card-header">
            <h2 class="font-semibold text-ink-900">Pengajuan Terbaru</h2>
            <a href="{{ route('mahasiswa.status.index') }}" class="tautan text-sm">Lihat semua</a>
        </div>
        @if ($pengajuan->isEmpty())
            <x-empty pesan="Belum ada pengajuan. Mulai dengan mengajukan KP atau TA." />
        @else
            <div class="overflow-x-auto">
                <table class="tabel min-w-[34rem]">
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
@endsection
