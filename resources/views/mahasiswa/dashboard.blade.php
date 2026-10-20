@extends('layouts.mahasiswa')

@section('title', 'Dashboard Mahasiswa')
@section('page-title', 'Dashboard Mahasiswa')
@section('page-subtitle', 'Administrasi Tugas Akhir dan Kerja Praktik')

@section('content')
    @if (! $mahasiswa)
        <div class="mhs-card flex items-start gap-4 p-6">
            <x-mhs.icon name="alert" class="mt-0.5 h-6 w-6 shrink-0 text-sand-600" />
            <div>
                <p class="text-sm font-semibold text-ink-900">Akun belum tertaut</p>
                <p class="mt-1 text-sm text-ink-600">Akun Anda belum tertaut ke data mahasiswa. Hubungi Admin.</p>
            </div>
        </div>
    @else
        @php
            $bisaCatatTa = $ta && $ta->diajukan_pada && $ta->dospem_id;
            $bisaCatatKp = $kp && $kp->diajukan_pada && $kp->dospem_id;
            $suratMilik = $kp?->suratPermohonans ?? collect();
        @endphp

        {{-- Ringkasan --}}
        <div class="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
            @foreach ([['Tugas Akhir (TA)', $ta, 'pengajuan-ta', 'doc'], ['Kerja Praktik (KP)', $kp, 'pengajuan-kp', 'calendar']] as [$label, $p, $anchor, $ikon])
                <a href="{{ $p ? route('pengajuan.show', $p) : '#'.$anchor }}" class="mhs-card group relative flex items-start gap-4 p-6 transition hover:border-sand-300 hover:bg-white/90">
                    <span class="mhs-icon-bubble h-12 w-12"><x-mhs.icon :name="$ikon" class="h-6 w-6 text-sand-600" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="mhs-eyebrow">{{ $label }}</p>
                        @if ($p)
                            <p class="mt-1.5 truncate text-lg font-semibold text-ink-900">{{ $p->judulTampil() }}</p>
                            <div class="mt-2.5">@include('partials.mhs-status-pill', ['status' => $p->status])</div>
                            <p class="mt-2.5 text-sm text-ink-600">Pembimbing: {{ $p->dospem?->nama ?? 'belum ditetapkan' }}</p>
                        @else
                            <p class="mt-1.5 text-2xl font-semibold text-ink-900">Belum ada</p>
                            <p class="mt-2.5 text-sm text-ink-600">Belum ada pengajuan.</p>
                        @endif
                    </div>
                    <x-mhs.icon name="chevron-right" class="absolute right-5 top-6 h-4 w-4 text-ink-500 transition group-hover:translate-x-0.5" />
                </a>
            @endforeach

            <div class="mhs-card flex items-start gap-4 p-6">
                <span class="mhs-icon-bubble h-12 w-12"><x-mhs.icon name="history" class="h-6 w-6 text-sand-600" /></span>
                <div class="min-w-0 flex-1">
                    <p class="mhs-eyebrow">Data akademik</p>
                    <p class="mt-1.5 truncate text-lg font-semibold text-ink-900">{{ $mahasiswa->nama }}</p>
                    <p class="mt-1 text-sm text-ink-600">{{ $mahasiswa->nim }} &middot; Angkatan {{ $mahasiswa->angkatan }}</p>
                    <p class="mt-1 text-sm text-ink-600">{{ $mahasiswa->prodi?->nama ?? 'Program studi belum diatur — hubungi Admin' }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Pengajuan TA --}}
            <x-menu-proses id="pengajuan-ta" title="Pengajuan Tugas Akhir" subtitle="Ajukan judul, lengkapi dokumen, dan pantau verifikasi hingga SK terbit."
                           :badge="$ta?->status" :open="! $ta || $errors->hasAny(['judul'])" aksiTitle="Buat Pengajuan TA">
                <x-slot:syarat>
                    @forelse ($persyaratanTa as $s)
                        <li class="flex items-start gap-3 py-3 text-sm text-ink-800">
                            <x-mhs.icon name="doc" class="mt-0.5 h-5 w-5 shrink-0 text-ink-500" />
                            <span><span class="font-medium">{{ $s->nama }}</span> <span class="text-xs text-ink-500">({{ $s->wajib ? 'wajib' : 'opsional' }})</span>@if ($s->deskripsi)<br><span class="text-xs text-ink-600">{{ $s->deskripsi }}</span>@endif</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-ink-500">Belum ada persyaratan dokumen yang dikonfigurasi.</li>
                    @endforelse
                </x-slot:syarat>

                @if ($ta)
                    <x-slot:keterangan>
                        <div class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3 text-sm text-ink-800">
                            <p class="font-medium">{{ $ta->judul }}</p>
                            <p class="mt-1 text-xs text-ink-600">Administrasi: {{ $ta->status_administrasi->label() }} &middot; Akademik: {{ $ta->status_akademik->label() }}</p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                <a href="{{ route('pengajuan.show', $ta) }}" class="mhs-btn-secondary">Buka detail pengajuan</a>
                                @if ($ta->suratKeputusan)
                                    <a href="{{ route('pengajuan.sk.unduh', $ta) }}" class="mhs-btn-primary">Unduh SK TA ({{ $ta->suratKeputusan->nomor }})</a>
                                @endif
                            </div>
                        </div>
                    </x-slot:keterangan>
                @else
                    <form method="POST" action="{{ route('mahasiswa.pengajuan.store', 'ta') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="mhs-label">Judul Tugas Akhir</label>
                            <input type="text" name="judul" value="{{ old('judul') }}" maxlength="255" class="mhs-input" required>
                            @error('judul')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <p class="text-sm text-ink-600">Pengajuan disimpan sebagai draft. Dokumen diunggah di halaman detail, lalu pengajuan dikirim.</p>
                        <div class="flex justify-end"><button type="submit" class="mhs-btn-primary">Buat Draft Pengajuan TA</button></div>
                    </form>
                @endif
            </x-menu-proses>

            {{-- Pengajuan KP --}}
            <x-menu-proses id="pengajuan-kp" title="Pengajuan Kerja Praktik" subtitle="Data tempat KP dan dokumen pengajuan. Prosedur rinci masih menunggu ketetapan kampus."
                           :badge="$kp?->status" :open="! $kp || $errors->hasAny(['instansi_nama', 'instansi_alamat', 'tanggal_mulai', 'tanggal_selesai'])" aksiTitle="Buat Pengajuan KP">
                <x-slot:syarat>
                    @forelse ($persyaratanKp as $s)
                        <li class="flex items-start gap-3 py-3 text-sm text-ink-800">
                            <x-mhs.icon name="doc" class="mt-0.5 h-5 w-5 shrink-0 text-ink-500" />
                            <span><span class="font-medium">{{ $s->nama }}</span> <span class="text-xs text-ink-500">({{ $s->wajib ? 'wajib' : 'opsional' }})</span>@if ($s->deskripsi)<br><span class="text-xs text-ink-600">{{ $s->deskripsi }}</span>@endif</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-ink-500">Belum ada persyaratan dokumen KP yang dikonfigurasi.</li>
                    @endforelse
                </x-slot:syarat>

                @if ($kp)
                    <x-slot:keterangan>
                        <div class="rounded-xl border border-sand-200 bg-white/60 px-4 py-3 text-sm text-ink-800">
                            <p class="font-medium">{{ $kp->instansi_nama }}</p>
                            <p class="mt-1 text-xs text-ink-600">{{ $kp->tanggal_mulai?->translatedFormat('d M Y') }} &ndash; {{ $kp->tanggal_selesai?->translatedFormat('d M Y') }}</p>
                            <div class="mt-3"><a href="{{ route('pengajuan.show', $kp) }}" class="mhs-btn-secondary">Buka detail pengajuan</a></div>
                        </div>
                    </x-slot:keterangan>
                @else
                    <form method="POST" action="{{ route('mahasiswa.pengajuan.store', 'kp') }}" class="space-y-4">
                        @csrf
                        @include('pengajuan.partials.form-kp', ['nilai' => null])
                        <div class="flex justify-end"><button type="submit" class="mhs-btn-primary">Buat Draft Pengajuan KP</button></div>
                    </form>
                @endif
            </x-menu-proses>

            {{-- Bimbingan --}}
            <x-menu-proses id="bimbingan-ta" title="Bimbingan Tugas Akhir" subtitle="Catat setiap sesi bimbingan; dosen pembimbing memvalidasinya."
                           :open="$errors->hasAny(['tanggal', 'topik', 'bukti']) && old('_jenis') === 'ta'" aksiTitle="Catat Bimbingan TA">
                @include('partials.bimbingan-panel', ['jenis' => \App\Enums\JenisPengajuan::Ta, 'daftar' => $bimbingan['ta'], 'ringkasan' => $ringkasan['ta'], 'bisaCatat' => $bisaCatatTa])
            </x-menu-proses>

            <x-menu-proses id="bimbingan-kp" title="Bimbingan Kerja Praktik" subtitle="Catatan bimbingan KP dengan validasi dosen pembimbing." aksiTitle="Catat Bimbingan KP">
                @include('partials.bimbingan-panel', ['jenis' => \App\Enums\JenisPengajuan::Kp, 'daftar' => $bimbingan['kp'], 'ringkasan' => $ringkasan['kp'], 'bisaCatat' => $bisaCatatKp])
            </x-menu-proses>

            {{-- Surat KP --}}
            <x-menu-proses id="surat-kp" title="Surat Administrasi KP" subtitle="Ajukan surat (misalnya surat pengantar); Operator Akademik memprosesnya." aksiTitle="Ajukan Surat">
                <x-slot:syarat>
                    @forelse ($jenisSurats as $js)
                        <li class="py-3 text-sm text-ink-800">
                            <span class="font-medium">{{ $js->nama }}</span>
                            @if ($js->deskripsi)<span class="block text-xs text-ink-600">{{ $js->deskripsi }}</span>@endif
                            @if ($js->persyaratan)<span class="block text-xs text-ink-500">Persyaratan: {{ $js->persyaratan }}</span>@endif
                            @if ($js->template_path)<a class="text-xs font-medium underline" href="{{ route('surat.template', $js) }}">Unduh template</a>@endif
                        </li>
                    @empty
                        <li class="py-3 text-sm text-ink-500">Belum ada jenis surat yang dikonfigurasi Admin.</li>
                    @endforelse
                </x-slot:syarat>

                @if ($suratMilik->isNotEmpty())
                    <x-slot:keterangan>
                        <ul class="divide-y divide-sand-200/80 rounded-xl border border-sand-200 px-4">
                            @foreach ($suratMilik->sortByDesc('id') as $sm)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                    <div>
                                        <p class="font-medium text-ink-900">{{ $sm->jenisSurat->nama }}@if ($sm->nomor_surat) <span class="text-xs text-ink-500">({{ $sm->nomor_surat }})</span>@endif</p>
                                        @if ($sm->catatan_operator)<p class="text-xs text-ink-600">Catatan: {{ $sm->catatan_operator }}</p>@endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sm->status->badgeClasses() }}">{{ $sm->status->label() }}</span>
                                        @if ($sm->file_path)<a class="text-xs font-medium underline" href="{{ route('surat.unduh', $sm) }}">Unduh</a>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-slot:keterangan>
                @endif

                @if ($kp && $jenisSurats->isNotEmpty())
                    <form method="POST" action="{{ route('mahasiswa.surat.store', $kp) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mhs-label">Jenis surat</label>
                                <select name="jenis_surat_id" class="mhs-input" required>
                                    <option value="" disabled selected>Pilih jenis surat</option>
                                    @foreach ($jenisSurats as $js)<option value="{{ $js->id }}">{{ $js->nama }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mhs-label">Keterangan (opsional)</label>
                                <input type="text" name="catatan" maxlength="1000" class="mhs-input">
                            </div>
                        </div>
                        <div class="flex justify-end"><button type="submit" class="mhs-btn-primary">Ajukan Surat</button></div>
                    </form>
                @elseif (! $kp)
                    <p class="text-sm text-ink-600">Buat pengajuan KP terlebih dulu untuk mengajukan surat.</p>
                @endif
            </x-menu-proses>

            {{-- Panduan laporan & lampiran --}}
            <x-menu-proses id="struktur-laporan" title="Panduan Struktur Laporan KP" subtitle="Susunan laporan sesuai pedoman yang ditetapkan Admin.">
                <x-slot:syarat>
                    @forelse ($struktur as $bagian)
                        <li class="py-3 text-sm text-ink-800">
                            <span class="font-medium">{{ $bagian->judul }}</span>
                            @if ($bagian->deskripsi)<span class="block text-xs text-ink-600">{{ $bagian->deskripsi }}</span>@endif
                        </li>
                    @empty
                        <li class="py-3 text-sm text-ink-500">Struktur laporan belum dikonfigurasi.</li>
                    @endforelse
                </x-slot:syarat>
            </x-menu-proses>

            <x-menu-proses id="lampiran-kp" title="Lampiran Laporan KP" subtitle="Daftar lampiran, kelengkapan, dan pengumpulan (bila dibuka).">
                <x-slot:keterangan>
                    @if ($kp)
                        @include('partials.dokumen-persyaratan', ['pengajuan' => $kp, 'persyaratan' => $lampiranKp, 'bisaUnggah' => $lampiranAktif && in_array($kp->status, [\App\Enums\StatusPengajuan::Disetujui, \App\Enums\StatusPengajuan::Selesai], true)])
                        @unless ($lampiranAktif)<p class="mt-3 text-xs text-ink-500">Pengumpulan lampiran belum dibuka oleh Admin.</p>@endunless
                    @else
                        <ul class="divide-y divide-sand-200/80">
                            @forelse ($lampiranKp as $l)
                                <li class="py-3 text-sm text-ink-800"><span class="font-medium">{{ $l->nama }}</span> <span class="text-xs text-ink-500">({{ $l->wajib ? 'wajib' : 'opsional' }})</span>@if ($l->deskripsi)<span class="block text-xs text-ink-600">{{ $l->deskripsi }}</span>@endif</li>
                            @empty
                                <li class="py-3 text-sm text-ink-500">Daftar lampiran belum dikonfigurasi.</li>
                            @endforelse
                        </ul>
                    @endif
                </x-slot:keterangan>
            </x-menu-proses>
        </div>
    @endif
@endsection
