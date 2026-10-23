{{--
    Form pemeriksaan dokumen oleh operator (administrasi) atau dosen (akademik).
    Membutuhkan: $pengajuan, $persyaratan, $dokumenTerbaru, $peran (App\Enums\Role), $aksi (URL tujuan)
--}}
@php
    $menunggu = $persyaratan->filter(
        fn ($syarat) => $syarat->pemeriksa === $peran
            && $dokumenTerbaru->get($syarat->id)?->status === \App\Enums\StatusDokumen::Menunggu
    );
    $sebagaiOperator = $peran === \App\Enums\Role::Operator;
@endphp

<section class="card">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Pemeriksaan {{ $sebagaiOperator ? 'Administrasi' : 'Akademik' }}</h2>
    </div>

    <form method="POST" action="{{ $aksi }}" class="card-body space-y-5">
        @csrf

        @forelse ($menunggu as $syarat)
            @php($dok = $dokumenTerbaru->get($syarat->id))
            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-sm font-medium text-slate-900">{{ $syarat->nama }}</legend>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span class="text-slate-700">{{ $dok->nama_asli }} <span class="text-slate-500">(versi {{ $dok->versi }})</span></span>
                    <a href="{{ route('berkas.dokumen', ['dokumen' => $dok]) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                    <a href="{{ route('berkas.dokumen', ['dokumen' => $dok, 'unduh' => 1]) }}" class="tautan">Unduh</a>
                </div>

                <div class="mt-3 flex flex-wrap gap-6 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="keputusan[{{ $dok->id }}]" value="diterima" class="text-navy-700 focus:ring-navy-300" @checked(old("keputusan.{$dok->id}") === 'diterima')>
                        Terima
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="keputusan[{{ $dok->id }}]" value="revisi" class="text-navy-700 focus:ring-navy-300" @checked(old("keputusan.{$dok->id}") === 'revisi')>
                        Perlu revisi
                    </label>
                </div>
                @error("keputusan.{$dok->id}")
                    <p class="galat">{{ $message }}</p>
                @enderror

                <div class="mt-3">
                    <label for="catatan-{{ $dok->id }}" class="label">Catatan <span class="font-normal text-slate-500">(wajib bila perlu revisi)</span></label>
                    <textarea id="catatan-{{ $dok->id }}" name="catatan[{{ $dok->id }}]" rows="2" class="input">{{ old("catatan.{$dok->id}") }}</textarea>
                    @error("catatan.{$dok->id}")
                        <p class="galat">{{ $message }}</p>
                    @enderror
                </div>
            </fieldset>
        @empty
            <p class="text-sm text-slate-600">Tidak ada dokumen yang perlu diperiksa pada tahap ini. Anda dapat melanjutkan proses.</p>
        @endforelse

        <div>
            <label for="catatan_umum" class="label">Catatan umum <span class="font-normal text-slate-500">(opsional, tampil di riwayat status)</span></label>
            <textarea id="catatan_umum" name="catatan_umum" rows="2" class="input">{{ old('catatan_umum') }}</textarea>
        </div>

        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Jika ada dokumen yang perlu revisi, pengajuan dikembalikan kepada mahasiswa.
            Jika semua diterima, pengajuan
            {{ $sebagaiOperator ? 'diteruskan ke dosen pembimbing.' : 'dinyatakan disetujui dan diteruskan ke operator untuk dokumen resmi.' }}
        </div>

        <button type="submit" class="btn btn-primary">Simpan hasil pemeriksaan</button>
    </form>
</section>
