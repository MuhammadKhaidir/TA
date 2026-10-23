{{--
    Membutuhkan: $pengajuan, $persyaratan, $dokumenTerbaru (keyBy persyaratan_id)
    Opsional: $unggah (true hanya di halaman mahasiswa pemilik pengajuan)
--}}
@php
    $unggah = $unggah ?? false;
    $statusDraft = \App\Enums\StatusPengajuan::Draft;
    $statusRevisi = \App\Enums\StatusDokumen::Revisi;
@endphp

<section class="card">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Dokumen Persyaratan</h2>
    </div>

    @if ($persyaratan->isEmpty())
        <x-empty pesan="Belum ada persyaratan dokumen untuk jenis pengajuan ini." />
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($persyaratan as $syarat)
                @php
                    $dok = $dokumenTerbaru->get($syarat->id);
                    $catatan = $dok?->pemeriksaan->sortByDesc('id')->first();
                    $boleh = $unggah && $syarat->aktif && ($pengajuan->status === $statusDraft || ($dok && $dok->status === $statusRevisi));
                @endphp
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900">
                                {{ $syarat->nama }}
                                @unless ($syarat->wajib)
                                    <span class="text-xs font-normal text-slate-500">(opsional)</span>
                                @endunless
                            </div>
                            <div class="text-xs text-slate-500">
                                Diperiksa {{ $syarat->pemeriksa === \App\Enums\Role::Dosen ? 'dosen pembimbing' : 'operator akademik' }}
                                &middot; Format {{ strtoupper(implode(', ', $syarat->ekstensiList())) }}
                                &middot; Maks. {{ $syarat->ukuranMaksManusia() }}
                            </div>
                            @if ($syarat->deskripsi)
                                <div class="mt-1 text-xs text-slate-500">{{ $syarat->deskripsi }}</div>
                            @endif
                        </div>
                        @if ($dok)
                            <x-status-badge :status="$dok->status" />
                        @else
                            <span class="badge bg-slate-100 text-slate-600 ring-slate-300">Belum diunggah</span>
                        @endif
                    </div>

                    @if ($dok)
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="text-slate-700">{{ $dok->nama_asli }} <span class="text-slate-500">({{ $dok->ukuranManusia() }}, versi {{ $dok->versi }})</span></span>
                            <a href="{{ route('berkas.dokumen', ['dokumen' => $dok]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                            <a href="{{ route('berkas.dokumen', ['dokumen' => $dok, 'unduh' => 1]) }}" class="tautan">Unduh</a>
                        </div>

                        @if ($catatan && filled($catatan->catatan))
                            <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                <span class="font-medium">Catatan {{ $catatan->peran->label() }} ({{ $catatan->pemeriksa->name }}):</span>
                                {{ $catatan->catatan }}
                            </div>
                        @endif
                    @endif

                    @if ($boleh)
                        <form method="POST" action="{{ route('mahasiswa.dokumen.store', $pengajuan) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="persyaratan_id" value="{{ $syarat->id }}">
                            <input type="file" name="berkas" required accept=".{{ implode(',.', $syarat->ekstensiList()) }}"
                                   class="block w-full max-w-sm text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-navy-700 hover:file:bg-navy-100">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <x-icon name="upload" class="h-4 w-4" />
                                {{ $dok ? 'Unggah ulang' : 'Unggah' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
