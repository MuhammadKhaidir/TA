@extends(auth()->user()->hasRole('mahasiswa') ? 'layouts.mahasiswa' : 'layouts.app')

@php
    use App\Enums\StatusSidangTa;

    /**
     * Halaman pengajuan sidang TA. Panel aksi muncul sesuai hak akses pengguna (policy -> HakAkses).
     * Variabel: $sidang, $pengajuan, $persyaratan, $tautanTersedia, $kartuTerkunci, $bimbingan,
     *           $bisaUbah, $milik, $bisaPeriksa, $bisaSetujui, $bisaJadwalkan, $bisaSelesai, $bisaBatalkan, $calonPenguji.
     */
    $s = $sidang;
    $p = $pengajuan;
    $mhs = $p->mahasiswa;
    $tombolKecil = 'mhs-btn-secondary';
    $revisiTerakhir = $s->status === StatusSidangTa::PerluRevisi
        ? $s->riwayats->whereIn('aksi', ['sidang_dikembalikan', 'sidang_revisi_dospem'])->last()
        : null;
    $dosenLogin = auth()->user()->dosen;
    $posisiSaya = $bisaSetujui ? $p->posisiPembimbing($dosenLogin) : null;
    $sudahSetujuSaya = $posisiSaya === 1 ? $s->setuju_dospem1_pada : ($posisiSaya === 2 ? $s->setuju_dospem2_pada : null);
    $bisaDibatalkan = in_array($s->status, [StatusSidangTa::Diajukan, StatusSidangTa::PerluRevisi, StatusSidangTa::Terverifikasi, StatusSidangTa::Disetujui, StatusSidangTa::Dijadwalkan], true);
@endphp

@section('title', 'Sidang TA — '.$mhs->nama)
@section('page-title', 'Sidang Tugas Akhir')
@section('page-subtitle', $mhs->nama.' · '.$mhs->nim.' · '.$p->prodi->nama)

@section('content')
<div class="space-y-6">
    <section class="mhs-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="mhs-eyebrow">Judul Tugas Akhir</p>
                <h2 class="mt-1 text-xl font-semibold text-ink-900">{{ $p->judulTampil() }}</h2>
                <p class="mt-1 text-sm"><a class="font-medium text-ink-700 underline" href="{{ route('pengajuan.show', $p) }}">Lihat pengajuan TA</a></p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $s->status->badgeClasses() }}">{{ $s->status->label() }}</span>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="mhs-eyebrow">Pembimbing</dt>
                <dd class="mt-1 text-ink-900">
                    <span class="block">1. {{ $p->dospem?->nama ?? '-' }}@if ($s->setuju_dospem1_pada) <span class="text-xs text-emerald-700">(setuju)</span>@endif</span>
                    @if ($p->dospem2)<span class="block">2. {{ $p->dospem2->nama }}@if ($s->setuju_dospem2_pada) <span class="text-xs text-emerald-700">(setuju)</span>@endif</span>@endif
                </dd>
            </div>
            <div><dt class="mhs-eyebrow">Nomor SK TA</dt><dd class="mt-1 text-ink-900">{{ $p->suratKeputusan?->nomor ?? '-' }}</dd></div>
            <div>
                <dt class="mhs-eyebrow">Bimbingan TA tervalidasi</dt>
                <dd class="mt-1 text-ink-900">{{ $bimbingan['divalidasi'] }}@if ($bimbingan['minimal']) dari minimal {{ $bimbingan['minimal'] }}@endif</dd>
            </div>
            <div>
                <dt class="mhs-eyebrow">Jadwal</dt>
                <dd class="mt-1 text-ink-900">
                    @if ($s->jadwal_mulai)
                        {{ $s->jadwal_mulai->translatedFormat('d M Y H:i') }}&ndash;{{ $s->jadwalSelesai()->format('H:i') }}@if ($s->ruang), {{ $s->ruang }}@endif
                    @else
                        Belum dijadwalkan
                    @endif
                </dd>
            </div>
            @if ($s->penguji->isNotEmpty())
                <div class="sm:col-span-2 lg:col-span-4">
                    <dt class="mhs-eyebrow">Penguji</dt>
                    <dd class="mt-1 text-ink-900">{{ $s->penguji->map(fn ($x) => $x->urutan.'. '.$x->dosen->nama)->implode('; ') }}</dd>
                </div>
            @endif
        </dl>

        @if ($revisiTerakhir)
            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="text-xs font-semibold uppercase tracking-wide">{{ $revisiTerakhir->aksi === 'sidang_revisi_dospem' ? 'Catatan revisi dari Dosen Pembimbing' : 'Catatan perbaikan dari pemeriksa kelengkapan' }}</p>
                <p class="mt-1">{{ $revisiTerakhir->keterangan }}</p>
            </div>
        @endif

        @if (in_array($s->status, [StatusSidangTa::Dibatalkan, StatusSidangTa::Digantikan], true))
            <div class="mt-5 rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <p class="text-xs font-semibold uppercase tracking-wide">{{ $s->status === StatusSidangTa::Dibatalkan ? 'Pengajuan sidang dibatalkan' : 'Pengajuan sidang digantikan' }}</p>
                @if ($s->alasan_batal)<p class="mt-1">Alasan: {{ $s->alasan_batal }}</p>@endif
                @if ($s->digantikanOleh)
                    <p class="mt-1">Digantikan oleh <a class="font-medium underline" href="{{ route('sidang.show', $s->digantikanOleh) }}">pengajuan sidang #{{ $s->digantikanOleh->id }}</a>.</p>
                @elseif ($s->status === StatusSidangTa::Dibatalkan && $milik)
                    <form method="POST" action="{{ route('mahasiswa.sidang.ajukan-ulang', $s) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="mhs-btn-primary">Ajukan ulang sidang</button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    {{-- Dokumen sidang --}}
    <section class="mhs-card p-6">
        <h3 class="text-base font-semibold text-ink-900">Dokumen sidang</h3>
        <p class="mt-1 text-xs text-ink-600">Dokumen bertanda "otomatis" ditautkan oleh sistem dari data SK dan bimbingan Anda; selebihnya diunggah mahasiswa.</p>
        <ul class="mt-2 divide-y divide-sand-200/80">
            @forelse ($persyaratan as $syarat)
                @php
                    $dokumen = $s->dokumenPersyaratan($syarat->id);
                    $otomatis = $syarat->tautan_otomatis !== null;
                    $ada = $otomatis ? (bool) $tautanTersedia[$syarat->id] : (bool) $dokumen;
                @endphp
                <li class="py-4">
                    <div class="flex items-start gap-3">
                        <x-mhs.icon :name="$ada ? 'check-circle' : 'x-circle'" class="mt-0.5 h-6 w-6 shrink-0 {{ $ada ? 'text-emerald-600' : ($syarat->wajib ? 'text-rose-500' : 'text-ink-400') }}" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-ink-900">
                                {{ $syarat->nama }}
                                <span class="ml-1 text-xs font-medium {{ $syarat->wajib ? 'text-rose-600' : 'text-ink-500' }}">{{ $syarat->wajib ? 'Wajib' : 'Opsional' }}</span>
                                @if ($otomatis)<span class="ml-1 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">otomatis</span>@endif
                            </p>
                            @if ($syarat->deskripsi)<p class="mt-0.5 text-xs text-ink-600">{{ $syarat->deskripsi }}</p>@endif

                            @if ($otomatis)
                                @if ($syarat->tautan_otomatis === \App\Models\Persyaratan::TAUTAN_SK_TA)
                                    @if ($p->suratKeputusan)
                                        <p class="mt-1 text-xs"><a class="font-medium text-ink-800 underline" href="{{ route('pengajuan.sk.unduh', $p) }}">Unduh SK TA {{ $p->suratKeputusan->nomor }}</a></p>
                                    @endif
                                @else
                                    @if ($kartuTerkunci)
                                        <p class="mt-1 text-xs text-ink-600">Kartu konsultasi terkunci sampai SK TA terbit.</p>
                                    @else
                                        <p class="mt-1 text-xs"><a class="font-medium text-ink-800 underline" href="{{ route('pengajuan.kartu.unduh', $p) }}">Unduh kartu konsultasi (PDF)</a></p>
                                    @endif
                                @endif
                            @elseif ($dokumen)
                                <p class="mt-1 text-xs text-ink-600">
                                    <a class="font-medium text-ink-800 underline" href="{{ route('sidang.dokumen.unduh', $dokumen) }}">{{ $dokumen->nama_file_asli ?? 'Unduh berkas' }}</a>
                                    &middot; diunggah {{ $dokumen->updated_at->translatedFormat('d M Y H:i') }}
                                </p>
                            @endif

                            @if ($bisaUbah && ! $otomatis)
                                <form method="POST" action="{{ route('mahasiswa.sidang.unggah', [$s, $syarat]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="file" name="berkas" required
                                           class="mhs-input max-w-xs text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                                    <button type="submit" class="mhs-btn-secondary">{{ $dokumen ? 'Ganti berkas' : 'Unggah' }}</button>
                                </form>
                                @if ($dokumen)
                                    <form method="POST" action="{{ route('mahasiswa.sidang.dokumen.hapus', $dokumen) }}" class="mt-1" onsubmit="return confirm('Hapus dokumen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-rose-600 hover:underline">Hapus dokumen</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-ink-500">Belum ada persyaratan sidang yang dikonfigurasi. Admin dapat menambahkannya di panel.</li>
            @endforelse
        </ul>

        @if ($bisaUbah)
            <div class="mt-5 flex justify-end border-t border-sand-200 pt-5">
                <form method="POST" action="{{ route('mahasiswa.sidang.kirim', $s) }}">
                    @csrf
                    <button type="submit" class="mhs-btn-primary">{{ $s->diajukan_pada ? 'Kirim ulang setelah perbaikan' : 'Kirim pengajuan sidang' }}</button>
                </form>
            </div>
        @endif
    </section>

    {{-- Pemeriksa kelengkapan --}}
    @if ($bisaPeriksa && in_array($s->status, [StatusSidangTa::Diajukan, StatusSidangTa::Terverifikasi], true))
        <section class="mhs-card space-y-4 p-6">
            <h3 class="text-base font-semibold text-ink-900">Pemeriksaan kelengkapan</h3>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @if ($s->status === StatusSidangTa::Diajukan)
                    <form method="POST" action="{{ route('operator.sidang.verifikasi', $s) }}" class="space-y-2">
                        @csrf
                        <p class="text-sm text-ink-600">Seluruh dokumen sudah diperiksa dan lengkap. Setelah ini pembimbing diminta menyetujui.</p>
                        <button type="submit" class="mhs-btn-primary">Verifikasi dokumen sidang</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('operator.sidang.kembalikan', $s) }}" class="space-y-2">
                    @csrf
                    <label class="mhs-label">Kembalikan ke mahasiswa untuk diperbaiki</label>
                    <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Jelaskan bagian yang perlu diperbaiki" required></textarea>
                    <button type="submit" class="{{ $tombolKecil }}">Kembalikan dengan catatan</button>
                </form>
            </div>
        </section>
    @endif

    {{-- Pembimbing 1 / 2 --}}
    @if ($bisaSetujui && $s->status === StatusSidangTa::Terverifikasi)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Persetujuan pembimbing</h3>
            <p class="mt-1 text-xs text-ink-600">Anda Pembimbing {{ $posisiSaya }}. Sidang disetujui setelah semua pembimbing menyetujui.@if ($bimbingan['minimal']) Minimal bimbingan tervalidasi: {{ $bimbingan['minimal'] }} (saat ini {{ $bimbingan['divalidasi'] }}).@endif</p>
            @if ($sudahSetujuSaya)
                <p class="mt-3 text-sm text-ink-700">Anda sudah menyetujui. Menunggu pembimbing lainnya.</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('dospem.sidang.setujui', $s) }}" class="space-y-2">
                        @csrf
                        <button type="submit" class="mhs-btn-primary">Setujui pengajuan sidang</button>
                    </form>
                    <form method="POST" action="{{ route('dospem.sidang.revisi', $s) }}" class="space-y-2">
                        @csrf
                        <textarea name="catatan" rows="2" maxlength="2000" class="mhs-input" placeholder="Catatan revisi untuk mahasiswa" required></textarea>
                        <button type="submit" class="{{ $tombolKecil }}">Minta revisi</button>
                    </form>
                </div>
            @endif
        </section>
    @endif

    {{-- Penjadwalan (bawaan: Admin) --}}
    @if ($bisaJadwalkan)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">{{ $s->status === StatusSidangTa::Dijadwalkan ? 'Ubah jadwal sidang' : 'Jadwalkan sidang' }}</h3>
            <form method="POST" action="{{ route('operator.sidang.jadwalkan', $s) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @csrf
                <div>
                    <label class="mhs-label" for="jadwal">Tanggal dan jam</label>
                    <input id="jadwal" type="datetime-local" name="jadwal_mulai" value="{{ old('jadwal_mulai', $s->jadwal_mulai?->format('Y-m-d\TH:i')) }}" class="mhs-input" required>
                </div>
                <div>
                    <label class="mhs-label" for="durasi">Durasi (menit)</label>
                    <input id="durasi" type="number" name="durasi_menit" min="15" max="480" value="{{ old('durasi_menit', $s->durasi_menit ?? \App\Models\Pengaturan::durasiSidangMenit()) }}" class="mhs-input">
                </div>
                <div>
                    <label class="mhs-label" for="ruang">Ruang</label>
                    <input id="ruang" type="text" name="ruang" maxlength="100" value="{{ old('ruang', $s->ruang) }}" class="mhs-input">
                </div>
                <div class="sm:col-span-3">
                    <p class="mhs-label">Penguji (minimal {{ \App\Models\Pengaturan::minimalPenguji() }}; pembimbing tidak dapat menjadi penguji)</p>
                    @php $terpilih = collect(old('penguji', $s->penguji->pluck('dosen_id')->all())); @endphp
                    <div class="grid max-h-56 grid-cols-1 gap-1 overflow-y-auto rounded-xl border border-sand-300 bg-white/80 p-3 sm:grid-cols-2">
                        @foreach ($calonPenguji as $d)
                            <label class="flex items-center gap-2 text-sm text-ink-800">
                                <input type="checkbox" name="penguji[]" value="{{ $d->id }}" class="rounded border-sand-400" @checked($terpilih->contains($d->id))>
                                {{ $d->nama }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-ink-500">Urutan penguji mengikuti urutan daftar. Sistem menolak jadwal yang bentrok dengan sidang lain (dosen atau ruang yang sama).</p>
                </div>
                <div class="sm:col-span-3"><button type="submit" class="mhs-btn-primary">Simpan jadwal</button></div>
            </form>
        </section>
    @endif

    @if ($bisaSelesai && $s->status === StatusSidangTa::Dijadwalkan)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Konfirmasi sidang selesai</h3>
            <form method="POST" action="{{ route('operator.sidang.selesai', $s) }}" class="mt-3" onsubmit="return confirm('Konfirmasi bahwa sidang ini sudah selesai dilaksanakan?')">
                @csrf
                <button type="submit" class="mhs-btn-primary">Sidang selesai</button>
            </form>
        </section>
    @endif

    @if ($bisaBatalkan && $bisaDibatalkan)
        <section class="mhs-card p-6">
            <h3 class="text-base font-semibold text-ink-900">Batalkan pengajuan sidang</h3>
            <form method="POST" action="{{ route('sidang.batalkan', $s) }}" class="mt-3 space-y-2" onsubmit="return confirm('Batalkan pengajuan sidang ini?')">
                @csrf
                <textarea name="alasan" rows="2" maxlength="2000" class="mhs-input" placeholder="Alasan pembatalan" required></textarea>
                <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Batalkan pengajuan sidang</button>
            </form>
        </section>
    @endif

    <section class="mhs-card p-6">
        <h3 class="mb-4 text-base font-semibold text-ink-900">Riwayat dan catatan</h3>
        @include('partials.riwayat', ['riwayats' => $s->riwayats])
    </section>
</div>
@endsection
