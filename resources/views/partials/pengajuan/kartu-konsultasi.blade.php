{{--
    Unduh kartu konsultasi bimbingan (PDF). Hanya untuk mahasiswa pemilik pengajuan.
    Membutuhkan: $pengajuan
    Tombol aktif setelah dosen pembimbing sah; sebelum itu tampil alasannya (Pengajuan::hambatanKartuKonsultasi()).
--}}
@php
    $bolehUnduh = auth()->user()->can('unduhKartuKonsultasi', $pengajuan);
@endphp

<section class="card" id="kartu-konsultasi">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Kartu Konsultasi</h2>
    </div>
    <div class="card-body space-y-3">
        @if ($bolehUnduh)
            <p class="text-sm text-slate-600">
                Identitas, judul, dan dosen pembimbing sudah terisi. Cetak kartu, lalu catat setiap konsultasi
                dan mintakan paraf dosen pembimbing.
            </p>
            <a href="{{ route('mahasiswa.kartu-konsultasi.unduh', $pengajuan) }}" class="btn btn-primary w-full">
                <x-icon name="download" class="h-4 w-4" />Unduh Kartu Konsultasi (PDF)
            </a>
        @else
            <p class="text-sm text-slate-600">{{ $pengajuan->hambatanKartuKonsultasi() }}</p>
        @endif
    </div>
</section>
