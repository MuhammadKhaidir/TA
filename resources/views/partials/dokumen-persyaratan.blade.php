@php
    /**
     * Daftar persyaratan beserta status dokumennya.
     *
     * @var \App\Models\Pengajuan $pengajuan
     * @var \Illuminate\Support\Collection $persyaratan
     * @var bool $bisaUnggah  mahasiswa pemilik boleh mengunggah/mengganti sekarang
     * @var bool $tampilkanHapus
     */
    $bisaUnggah = $bisaUnggah ?? false;
@endphp
<ul class="divide-y divide-sand-200/80">
    @forelse ($persyaratan as $syarat)
        @php $dokumen = $pengajuan->dokumenPersyaratan($syarat->id); @endphp
        <li class="py-4">
            <div class="flex items-start gap-3">
                @if ($dokumen)
                    <x-mhs.icon name="check-circle" class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" />
                @else
                    <x-mhs.icon name="x-circle" class="mt-0.5 h-6 w-6 shrink-0 {{ $syarat->wajib ? 'text-rose-500' : 'text-ink-400' }}" />
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink-900">
                        {{ $syarat->nama }}
                        <span class="ml-1 text-xs font-medium {{ $syarat->wajib ? 'text-rose-600' : 'text-ink-500' }}">{{ $syarat->wajib ? 'Wajib' : 'Opsional' }}</span>
                    </p>
                    @if ($syarat->deskripsi)
                        <p class="mt-0.5 text-xs text-ink-600">{{ $syarat->deskripsi }}</p>
                    @endif
                    @if ($syarat->waktu_penyerahan)
                        <p class="mt-0.5 text-xs text-ink-500">Diserahkan: {{ $syarat->waktu_penyerahan }}</p>
                    @endif
                    @if ($dokumen)
                        <p class="mt-1 text-xs text-ink-600">
                            <a class="font-medium text-ink-800 underline" href="{{ route('pengajuan.dokumen.unduh', $dokumen) }}">{{ $dokumen->nama_file_asli ?? 'Unduh berkas' }}</a>
                            &middot; diunggah {{ $dokumen->updated_at->translatedFormat('d M Y H:i') }}
                        </p>
                    @endif

                    @if ($bisaUnggah)
                        <form method="POST" action="{{ route('mahasiswa.pengajuan.dokumen.unggah', [$pengajuan, $syarat]) }}" enctype="multipart/form-data"
                              class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="file" name="berkas" required
                                   class="mhs-input max-w-xs text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sand-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink-800 hover:file:bg-sand-300">
                            <button type="submit" class="mhs-btn-secondary">{{ $dokumen ? 'Ganti berkas' : 'Unggah' }}</button>
                        </form>
                        @if ($dokumen && ($tampilkanHapus ?? true))
                            <form method="POST" action="{{ route('mahasiswa.pengajuan.dokumen.hapus', $dokumen) }}" class="mt-1"
                                  onsubmit="return confirm('Hapus dokumen ini?')">
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
        <li class="py-4 text-sm text-ink-500">Belum ada persyaratan yang dikonfigurasi. Admin dapat menambahkannya di panel.</li>
    @endforelse
</ul>
