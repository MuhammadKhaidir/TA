@php
    /** @var \App\Models\Sidang $sidang */
    $peringatan = [];

    if ($sidang->status === \App\Enums\SidangStatus::Ditunda) {
        $peringatan[] = "Peringatan #1 &mdash; mahasiswa baru melakukan {$sidang->mahasiswa->jumlah_konsultasi} dari minimal ".\App\Models\Mahasiswa::MINIMAL_KONSULTASI.' kali konsultasi. Pengajuan sidang ditunda sampai persyaratan konsultasi terpenuhi.';
    }

    if ($sidang->risikoSkBelumTerbit()) {
        $peringatan[] = 'Peringatan #2 &mdash; jadwal sidang sudah dekat namun SK Penguji belum diterbitkan. Sidang berpotensi ditunda atau dijadwalkan ulang jika SK tidak segera terbit.';
    }

    if ($sidang->status === \App\Enums\SidangStatus::MenungguPenjadwalanUlang) {
        $peringatan[] = 'Peringatan #3 &mdash; terdapat dosen penguji yang berhalangan hadir. SekDep/Koor. Prodi perlu segera mengambil langkah penjadwalan ulang.';
    }

    if ($sidang->terlambatUnggahDokumenTa()) {
        $peringatan[] = 'Peringatan #4 &mdash; batas waktu pengiriman file Tugas Akhir (H-1 sebelum sidang) telah terlewat dan berkas belum diunggah.';
    }

    $batasDurasi = $sidang->batasSelesaiSidang();
    if ($batasDurasi && in_array($sidang->status, [\App\Enums\SidangStatus::SiapUjian, \App\Enums\SidangStatus::PelaksanaanUjian], true)) {
        $peringatan[] = "Peringatan #5 &mdash; ".$sidang->jenis->label()." memiliki batas maksimal durasi ".$sidang->jenis->batasDurasiMenit()." menit. Perkiraan selesai paling lambat pukul {$batasDurasi->format('H:i')}.";
    }

    if ($sidang->aksiTerlambat()) {
        $peringatan[] = 'Mutu Baku &mdash; batas waktu 1 hari untuk tindakan pada langkah saat ini ('.$sidang->status->labelPerananBerikutnya().') telah terlewat.';
    }
@endphp
@if (count($peringatan))
    <div class="mb-6 space-y-2">
        @foreach ($peringatan as $p)
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0">
                    <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                </svg>
                <span>{!! $p !!}</span>
            </div>
        @endforeach
    </div>
@endif
