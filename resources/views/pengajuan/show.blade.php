@extends(auth()->user()->hasRole('mahasiswa') ? 'layouts.mahasiswa' : 'layouts.app')

@php
    use App\Enums\JenisPengajuan;
    use App\Enums\StatusAdministrasi;
    use App\Enums\StatusAkademik;
    use App\Enums\StatusPengajuan;

    $p = $pengajuan;
    $ta = $p->jenis === JenisPengajuan::Ta;
    $revisiTerakhir = $p->status === StatusPengajuan::PerluRevisi
        ? $p->riwayats->whereIn('aksi', ['revisi_administrasi', 'revisi_akademik'])->last()
        : null;
    $lampiranBisaUnggah = $milik && $lampiranAktif && in_array($p->status, [StatusPengajuan::Disetujui, StatusPengajuan::Selesai], true);
    $tombolKecil = 'mhs-btn-secondary';
@endphp

@section('title', $p->jenis->singkat().' — '.$p->judulTampil())
@section('page-title', 'Pengajuan '.$p->jenis->label())
@section('page-subtitle', $p->mahasiswa->nama.' · '.$p->mahasiswa->nim.' · '.$p->prodi->nama)

@section('content')
<div class="space-y-6">
    {{-- Ringkasan --}}
    <section class="mhs-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="mhs-eyebrow">{{ $ta ? 'Judul Tugas Akhir' : 'Tempat Kerja Praktik' }}</p>
                <h2 class="mt-1 text-xl font-semibold text-ink-900">{{ $p->judulTampil() }}</h2>
            </div>
            @include('partials.mhs-status-pill', ['status' => $p->status])
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="mhs-eyebrow">Verifikasi administrasi</dt><dd class="mt-1 text-ink-900">{{ $p->status_administrasi->label() }}</dd></div>
            <div><dt class="mhs-eyebrow">Pemeriksaan akademik</dt><dd class="mt-1 text-ink-900">{{ $p->status_akademik->label() }}</dd></div>
            <div><dt class="mhs-eyebrow">Dosen pembimbing</dt><dd class="mt-1 text-ink-900">{{ $p->dospem?->nama ?? 'Belum ditetapkan' }}</dd></div>
            <div><dt class="mhs-eyebrow">Periode akademik</dt><dd class="mt-1 text-ink-900">{{ $p->periode?->nama ?? '-' }}</dd></div>
            @unless ($ta)
                <div class="sm:col-span-2"><dt class="mhs-eyebrow">Alamat</dt><dd class="mt-1 text-ink-900">{{ $p->instansi_alamat }}</dd></div>
                <div><dt class="mhs-eyebrow">Pelaksanaan</dt><dd class="mt-1 text-ink-900">{{ $p->tanggal_mulai?->translatedFormat('d M Y') }} &ndash; {{ $p->tanggal_selesai?->translatedFormat('d M Y') }}</dd></div>
                <div><dt class="mhs-eyebrow">Bidang kegiatan</dt><dd class="mt-1 text-ink-900">{{ $p->bidang_kegiatan ?: '-' }}</dd></div>
                <div class="sm:col-span-2"><dt class="mhs-eyebrow">Pembimbing lapangan (kontak)</dt><dd class="mt-1 text-ink-900">{{ $p->pembimbing_lapangan_nama ?: '-' }}@if ($p->pembimbing_lapangan_kontak) &middot; {{ $p->pembimbing_lapangan_kontak }}@endif</dd></div>
            @endunless
        </dl>

        @if ($revisiTerakhir)
            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="text-xs font-semibold uppercase tracking-wide">{{ $revisiTerakhir->aksi === 'revisi_akademik' ? 'Catatan revisi dari Dosen Pembimbing' : 'Catatan perbaikan dari Operator Akademik' }}</p>
                <p class="mt-1">{{ $revisiTerakhir->keterangan }}</p>
            </div>
        @endif

        @if ($p->suratKeputusan)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <p>SK TA terbit &middot; Nomor <span class="font-semibold">{{ $p->suratKeputusan->nomor }}</span> &middot; {{ $p->suratKeputusan->tanggal->translatedFormat('d F Y') }}</p>
                <a href="{{ route('pengajuan.sk.unduh', $p) }}" class="mhs-btn-primary">Unduh SK (PDF)</a>
            </div>
        @endif
    </section>

    {{-- Ubah data (mahasiswa pemilik, saat draft / perlu revisi) --}}
    @if ($bisaUbah)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Data pengajuan</h3>
            <form method="POST" action="{{ route('mahasiswa.pengajuan.update', $p) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                @if ($ta)
                    <div>
                        <label class="mhs-label">Judul Tugas Akhir</label>
                        <input type="text" name="judul" value="{{ old('judul', $p->judul) }}" maxlength="255" class="mhs-input" required>
                        @error('judul')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @else
                    @include('pengajuan.partials.form-kp', ['nilai' => $p])
                @endif
                <div class="flex justify-end"><button type="submit" class="mhs-btn-secondary">Simpan perubahan</button></div>
            </form>
        </section>
    @endif

    {{-- Dokumen persyaratan --}}
    <section class="mhs-card p-6">
        <h3 class="text-base font-semibold text-ink-900">Dokumen persyaratan</h3>
        <div class="mt-2">
            @include('partials.dokumen-persyaratan', ['pengajuan' => $p, 'persyaratan' => $persyaratan, 'bisaUnggah' => $bisaUbah])
        </div>

        @if ($bisaUbah)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-sand-200 pt-5">
                @if ($p->status === StatusPengajuan::Draft && $p->diajukan_pada === null)
                    <form method="POST" action="{{ route('mahasiswa.pengajuan.destroy', $p) }}" onsubmit="return confirm('Hapus draft pengajuan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus draft</button>
                    </form>
                @else
                    <span></span>
                @endif
                <form method="POST" action="{{ route('mahasiswa.pengajuan.kirim', $p) }}">
                    @csrf
                    <button type="submit" class="mhs-btn-primary">{{ $p->diajukan_pada ? 'Ajukan ulang setelah perbaikan' : 'Kirim pengajuan' }}</button>
                </form>
            </div>
        @endif
    </section>

    @if (! $ta && $lampiran->isNotEmpty())
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Lampiran laporan KP</h3>
            @unless ($lampiranAktif)<p class="mt-1 text-xs text-ink-500">Pengumpulan lampiran belum dibuka oleh Admin.</p>@endunless
            @include('partials.dokumen-persyaratan', ['pengajuan' => $p, 'persyaratan' => $lampiran, 'bisaUnggah' => $lampiranBisaUnggah])
        </section>
    @endif

    {{-- Panel Operator Akademik --}}
    @if ($bisaKelola)
        <section class="mhs-card space-y-6 p-6">
            <h3 class="text-base font-semibold text-ink-900">Tindakan Operator Akademik</h3>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-3">
                    <p class="mhs-eyebrow">Verifikasi administrasi</p>
                    @if ($p->status === StatusPengajuan::Diajukan && $p->status_administrasi === StatusAdministrasi::Menunggu)
                        <form method="POST" action="{{ route('operator.pengajuan.verifikasi', $p) }}">
                            @csrf
                            <button type="submit" class="mhs-btn-primary">Verifikasi kelengkapan administrasi</button>
                        </form>
                    @else
                        <p class="text-sm text-ink-600">Status: {{ $p->status_administrasi->label() }}.</p>
                    @endif

                    @if (in_array($p->status, [StatusPengajuan::Diajukan, StatusPengajuan::Disetujui], true) && in_array($p->status_administrasi, [StatusAdministrasi::Menunggu, StatusAdministrasi::Terverifikasi], true))
                        <form method="POST" action="{{ route('operator.pengajuan.kembalikan', $p) }}" class="space-y-2">
                            @csrf
                            <label class="mhs-label">Kembalikan ke mahasiswa untuk diperbaiki</label>
                            <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Jelaskan bagian yang perlu diperbaiki" required></textarea>
                            <button type="submit" class="{{ $tombolKecil }}">Kembalikan dengan catatan</button>
                        </form>
                    @endif
                </div>

                <div class="space-y-3">
                    <p class="mhs-eyebrow">Penetapan dosen pembimbing</p>
                    @if (in_array($p->status, [StatusPengajuan::Diajukan, StatusPengajuan::PerluRevisi, StatusPengajuan::Disetujui], true))
                        <form method="POST" action="{{ route('operator.pengajuan.dospem', $p) }}" class="space-y-2">
                            @csrf
                            <select name="dosen_id" class="mhs-input" required>
                                <option value="" disabled @selected(! $p->dospem_id)>Pilih dosen pembimbing</option>
                                @foreach ($dosens as $d)
                                    <option value="{{ $d->id }}" @selected($p->dospem_id === $d->id)>{{ $d->nama }} ({{ $d->nip }}){{ $d->prodi ? ' — '.$d->prodi->kode : '' }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="{{ $tombolKecil }}">Tetapkan pembimbing</button>
                        </form>
                    @else
                        <p class="text-sm text-ink-600">Pembimbing: {{ $p->dospem?->nama ?? '-' }}.</p>
                    @endif
                </div>
            </div>

            @if ($ta && ($p->status === StatusPengajuan::Disetujui || $p->suratKeputusan))
                <div class="border-t border-sand-200 pt-5">
                    <p class="mhs-eyebrow">Surat Keputusan (SK) TA</p>
                    @if ($p->suratKeputusan)
                        <p class="mt-2 text-sm text-ink-700">SK sudah diterbitkan dengan nomor {{ $p->suratKeputusan->nomor }}.</p>
                    @elseif ($p->siapTerbitSk())
                        @if ($skKurang)
                            <div class="mt-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                SK belum dapat diterbitkan. Pihak kampus/Admin perlu melengkapi: <span class="font-medium">{{ implode(', ', $skKurang) }}</span>.
                            </div>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if (! in_array('Template SK aktif', $skKurang, true))
                                <a href="{{ route('operator.sk.pratinjau', $p) }}" target="_blank" rel="noopener" class="{{ $tombolKecil }}">Pratinjau SK</a>
                            @endif
                            <form method="POST" action="{{ route('operator.sk.terbitkan', $p) }}" onsubmit="return confirm('Terbitkan SK? Nomor SK akan dikunci dan tidak dapat diterbitkan ulang.')">
                                @csrf
                                <button type="submit" class="mhs-btn-primary" @disabled($skKurang)>Terbitkan SK dan buat PDF</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endif

            @if (! $ta && $p->status === StatusPengajuan::Disetujui)
                <div class="border-t border-sand-200 pt-5">
                    <form method="POST" action="{{ route('operator.pengajuan.selesaikan-kp', $p) }}" onsubmit="return confirm('Selesaikan dan arsipkan administrasi KP ini?')">
                        @csrf
                        <button type="submit" class="mhs-btn-primary">Selesaikan dan arsipkan administrasi KP</button>
                    </form>
                </div>
            @endif
        </section>
    @endif

    {{-- Panel Dosen Pembimbing --}}
    @if ($bisaPeriksa)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Pemeriksaan akademik</h3>
            @if ($p->status === StatusPengajuan::Diajukan && $p->status_akademik === StatusAkademik::Menunggu)
                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('dospem.pengajuan.setujui', $p) }}" class="space-y-2">
                        @csrf
                        <p class="text-sm text-ink-600">Setujui bila substansi pengajuan sudah layak.</p>
                        <button type="submit" class="mhs-btn-primary">Setujui secara akademik</button>
                    </form>
                    <form method="POST" action="{{ route('dospem.pengajuan.revisi', $p) }}" class="space-y-2">
                        @csrf
                        <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Catatan revisi untuk mahasiswa" required></textarea>
                        <button type="submit" class="{{ $tombolKecil }}">Minta revisi</button>
                    </form>
                </div>
            @elseif ($p->status === StatusPengajuan::Disetujui)
                <p class="mt-2 text-sm text-ink-600">Pengajuan sudah disetujui. Anda masih dapat meminta revisi sebelum SK diterbitkan.</p>
                <form method="POST" action="{{ route('dospem.pengajuan.revisi', $p) }}" class="mt-3 space-y-2">
                    @csrf
                    <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Catatan revisi untuk mahasiswa" required></textarea>
                    <button type="submit" class="{{ $tombolKecil }}">Minta revisi</button>
                </form>
            @else
                <p class="mt-2 text-sm text-ink-600">Status akademik: {{ $p->status_akademik->label() }}. Belum ada tindakan yang tersedia pada status "{{ $p->status->label() }}".</p>
            @endif
        </section>
    @endif

    <section class="mhs-card p-6">
        <h3 class="mb-4 text-base font-semibold text-ink-900">Riwayat dan catatan</h3>
        @include('partials.riwayat', ['riwayats' => $p->riwayats])
    </section>
</div>
@endsection
