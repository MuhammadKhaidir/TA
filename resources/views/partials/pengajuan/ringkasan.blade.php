{{-- Membutuhkan: $pengajuan (relasi mahasiswa.programStudi dan pembimbing sudah dimuat) --}}
<section class="card">
    <div class="card-header">
        <h2 class="font-semibold text-slate-900">Informasi Pengajuan</h2>
        <x-status-badge :status="$pengajuan->status" />
    </div>
    <dl class="divide-y divide-slate-100 px-5 py-2">
        <div class="dl-baris"><dt>Jenis</dt><dd>{{ $pengajuan->jenis->label() }}</dd></div>
        <div class="dl-baris"><dt>Judul</dt><dd>{{ $pengajuan->judul }}</dd></div>
        @if ($pengajuan->instansi)
            <div class="dl-baris"><dt>Instansi</dt><dd>{{ $pengajuan->instansi }}</dd></div>
        @endif
        @if ($pengajuan->deskripsi)
            <div class="dl-baris"><dt>Deskripsi</dt><dd class="whitespace-pre-line">{{ $pengajuan->deskripsi }}</dd></div>
        @endif
        <div class="dl-baris">
            <dt>Mahasiswa</dt>
            <dd>{{ $pengajuan->mahasiswa->name }} <span class="text-slate-500">({{ $pengajuan->mahasiswa->nomor_induk ?? '-' }})</span></dd>
        </div>
        <div class="dl-baris">
            <dt>Program Studi</dt>
            <dd>{{ $pengajuan->mahasiswa->programStudi?->namaLengkap() ?? '-' }}@if ($pengajuan->mahasiswa->angkatan), angkatan {{ $pengajuan->mahasiswa->angkatan }}@endif</dd>
        </div>
        <div class="dl-baris">
            <dt>Dosen Pembimbing</dt>
            <dd>
                {{ $pengajuan->ringkasanPembimbing() }}
                <div class="mt-1">
                    <span class="badge {{ $pengajuan->status_pembimbing->badge() }}">{{ $pengajuan->status_pembimbing->label() }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ $pengajuan->keteranganPembimbing() }}</div>
            </dd>
        </div>
        <div class="dl-baris"><dt>Dikirim</dt><dd>{{ $pengajuan->diajukan_at?->translatedFormat('d M Y H:i') ?? 'Belum dikirim' }}</dd></div>
        <div class="dl-baris"><dt>Keterangan</dt><dd class="text-slate-600">{{ $pengajuan->status->keterangan() }}</dd></div>
    </dl>
</section>
