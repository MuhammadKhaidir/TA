@extends('layouts.app')

@section('title', 'Detail Sidang &mdash; ' . $sidang->mahasiswa->nama)
@section('page-title', 'Detail Sidang Tugas Akhir')
@section('page-subtitle', $sidang->mahasiswa->nama . ' &middot; ' . $sidang->mahasiswa->nim)

@section('content')
    @php
        $user = auth()->user();
        $mahasiswaSaya = $user->mahasiswa;
        $dosenSaya = $user->dosen;
        $rendered = false;
    @endphp

    {{-- Header ringkas --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">{{ $sidang->judul_ta ?? 'Tugas Akhir Belum Diberi Judul' }}</h2>
            <p class="text-sm text-slate-500">{{ $sidang->jenis->label() }} &middot; Diajukan {{ $sidang->tanggal_pengajuan->translatedFormat('d M Y') }}</p>
        </div>
        @include('partials.status-badge', ['status' => $sidang->status])
    </div>

    @include('partials.peringatan', ['sidang' => $sidang])

    {{-- Panel Tindakan --}}
    <div class="card mb-6 p-5">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Tindakan</h3>

        {{-- Mahasiswa: unggah dokumen --}}
        @if ($mahasiswaSaya && $mahasiswaSaya->id === $sidang->mahasiswa_id && $sidang->status !== \App\Enums\SidangStatus::Selesai)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Unggah / Lengkapi Dokumen</p>
                <form method="POST" action="{{ route('mahasiswa.sidang.dokumen.unggah', $sidang) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="field-label">Jenis Dokumen</label>
                        <select name="jenis_dokumen" class="field-input" required>
                            <option value="dkn">DKN (Daftar Kumpulan Nilai)</option>
                            <option value="bukti_kelulusan_usep">Bukti Kelulusan USEP</option>
                            <option value="sk_pembimbing_ta">SK Pembimbing TA</option>
                            <option value="dokumen_ta">Dokumen Tugas Akhir (PDF)</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Berkas (PDF)</label>
                        <input type="file" name="file" accept="application/pdf" required class="field-input">
                    </div>
                    <button type="submit" class="btn-primary">Unggah</button>
                </form>
                @if ($sidang->batasUnggahDokumenTa())
                    <p class="mt-2 text-xs text-slate-400">Batas pengiriman Dokumen Tugas Akhir: {{ $sidang->batasUnggahDokumenTa()->translatedFormat('d M Y, H:i') }} (H-1 sebelum sidang).</p>
                @endif
            </div>
        @endif

        {{-- SekDep: perbarui konsultasi (status Ditunda) --}}
        @if ($user->hasRole('sekdep_koor_prodi') && $sidang->status === \App\Enums\SidangStatus::Ditunda)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-1 text-sm font-medium text-slate-800">Langkah 1 &mdash; Perbarui Jumlah Konsultasi</p>
                <p class="mb-3 text-xs text-slate-500">Mahasiswa baru melakukan {{ $sidang->mahasiswa->jumlah_konsultasi }} dari minimal {{ \App\Models\Mahasiswa::MINIMAL_KONSULTASI }} kali konsultasi.</p>
                <form method="POST" action="{{ route('sekdep.sidang.konsultasi', $sidang) }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label class="field-label">Jumlah Konsultasi Saat Ini</label>
                        <input type="number" min="0" max="100" name="jumlah_konsultasi" value="{{ $sidang->mahasiswa->jumlah_konsultasi }}" class="field-input w-32" required>
                    </div>
                    <button type="submit" class="btn-primary">Perbarui</button>
                </form>
            </div>
        @endif

        {{-- SekDep: usulkan jadwal (Langkah 2) --}}
        @if ($user->hasRole('sekdep_koor_prodi') && $sidang->status === \App\Enums\SidangStatus::Diajukan)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Langkah 2 &mdash; Usulkan Jadwal Ujian &amp; Tim Penguji</p>
                <form method="POST" action="{{ route('sekdep.sidang.usulkan-jadwal', $sidang) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label">Tanggal Sidang</label>
                            <input type="date" name="tanggal_sidang" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Jam</label>
                            <input type="time" name="jam_sidang" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Media</label>
                            <select name="media" class="field-input" required>
                                <option value="luring">Luring</option>
                                <option value="daring">Daring</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Tempat / Tautan</label>
                        <input type="text" name="tempat" class="field-input" placeholder="Ruang Sidang 1 atau tautan daring">
                    </div>
                    <div>
                        <label class="field-label">Tim Penguji</label>
                        <div class="space-y-2">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="flex gap-2">
                                    <select name="pengujis[{{ $i }}][dosen_id]" class="field-input" {{ $i < 2 ? 'required' : '' }}>
                                        <option value="">{{ $i < 2 ? 'Pilih dosen...' : '(opsional) Pilih dosen...' }}</option>
                                        @foreach ($dosens as $d)
                                            <option value="{{ $d->id }}">{{ $d->nama }}</option>
                                        @endforeach
                                    </select>
                                    <select name="pengujis[{{ $i }}][peran]" class="field-input w-40">
                                        <option value="ketua">Ketua Sidang</option>
                                        <option value="anggota" selected>Anggota Penguji</option>
                                    </select>
                                </div>
                            @endfor
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Usulkan Jadwal</button>
                </form>
            </div>
        @endif

        {{-- SekDep: distribusi SK ke dosen (Langkah 6b) --}}
        @if ($user->hasRole('sekdep_koor_prodi') && $sidang->status === \App\Enums\SidangStatus::SkDiteruskanSekdep)
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                <div>
                    <p class="text-sm font-medium text-slate-800">Langkah 6 &mdash; Sampaikan SK Penguji ke Dosen Penguji</p>
                    <p class="text-xs text-slate-500">SK Nomor {{ $sidang->nomor_sk_penguji }} telah diterima dari Penata.</p>
                </div>
                <form method="POST" action="{{ route('sekdep.sidang.distribusi-sk', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Sampaikan ke Dosen</button>
                </form>
            </div>
        @endif

        {{-- SekDep: jadwalkan ulang (Langkah 10b) --}}
        @if ($user->hasRole('sekdep_koor_prodi') && $sidang->status === \App\Enums\SidangStatus::MenungguPenjadwalanUlang)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Langkah 10 &mdash; Jadwalkan Ulang Sidang</p>
                <form method="POST" action="{{ route('sekdep.sidang.jadwal-ulang', $sidang) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Tanggal Sidang Baru</label>
                            <input type="date" name="tanggal_sidang" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Jam Baru</label>
                            <input type="time" name="jam_sidang" class="field-input" required>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Alasan Penjadwalan Ulang</label>
                        <textarea name="alasan" rows="2" class="field-input"></textarea>
                    </div>
                    @php $dosenBerhalangan = $sidang->pengujis->first(fn ($d) => $d->pivot->konfirmasi === \App\Enums\KonfirmasiKehadiran::Berhalangan); @endphp
                    @if ($dosenBerhalangan)
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Dosen yang Diganti</label>
                                <select name="dosen_diganti_id" class="field-input">
                                    <option value="">Tidak mengganti dosen</option>
                                    <option value="{{ $dosenBerhalangan->id }}" selected>{{ $dosenBerhalangan->nama }} (berhalangan)</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Dosen Pengganti</label>
                                <select name="dosen_pengganti_id" class="field-input">
                                    <option value="">&mdash; Pilih dosen pengganti &mdash;</option>
                                    @foreach ($dosens as $d)
                                        @if (! $sidang->pengujis->pluck('id')->contains($d->id))
                                            <option value="{{ $d->id }}">{{ $d->nama }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                    <button type="submit" class="btn-primary">Simpan Jadwal Baru</button>
                </form>
            </div>
        @endif

        {{-- SekDep: hubungi dosen (Langkah 16, eskalasi) --}}
        @if ($user->hasRole('sekdep_koor_prodi') && $sidang->status === \App\Enums\SidangStatus::PelaksanaanUjian && ! $sidang->semuaNilaiSudahDiinput())
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-rose-200 bg-rose-50/40 p-4">
                <div>
                    <p class="text-sm font-medium text-slate-800">Langkah 16 &mdash; Hubungi Dosen</p>
                    <p class="text-xs text-slate-500">Belum input: {{ $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ') }}</p>
                </div>
                <form method="POST" action="{{ route('sekdep.sidang.hubungi-dosen', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Hubungi Dosen</button>
                </form>
            </div>
        @endif

        {{-- Penata: verifikasi & teruskan (Langkah 3 & 4) --}}
        @if ($user->hasRole('penata') && $sidang->status === \App\Enums\SidangStatus::MenungguVerifikasiPenata)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Langkah 3 &amp; 4 &mdash; Verifikasi Kelulusan &amp; Teruskan ke Pengelola Layanan</p>
                <form method="POST" action="{{ route('penata.sidang.verifikasi', $sidang) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Nilai USEP</label>
                            <input type="number" min="0" max="100" name="nilai_usep" class="field-input" required>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="dkn_terverifikasi" value="1" required class="rounded border-slate-300 text-brand-700">
                                DKN dinyatakan lengkap &amp; valid
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Catatan (opsional)</label>
                        <textarea name="catatan" rows="2" class="field-input"></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Verifikasi &amp; Teruskan</button>
                </form>
            </div>
        @endif

        {{-- Penata: teruskan SK ke SekDep (Langkah 6a) --}}
        @if ($user->hasRole('penata') && $sidang->status === \App\Enums\SidangStatus::SkTerbit)
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                <div>
                    <p class="text-sm font-medium text-slate-800">Langkah 6 &mdash; Teruskan SK Penguji ke SekDep/Koor. Prodi</p>
                    <p class="text-xs text-slate-500">SK Nomor {{ $sidang->nomor_sk_penguji }} telah disahkan Pengelola Layanan.</p>
                </div>
                <form method="POST" action="{{ route('penata.sidang.teruskan-sk', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Teruskan ke SekDep</button>
                </form>
            </div>
        @endif

        {{-- Penata: lapor ke SekDep (Langkah 15, eskalasi) --}}
        @if ($user->hasRole('penata') && $sidang->status === \App\Enums\SidangStatus::PelaksanaanUjian && ! $sidang->semuaNilaiSudahDiinput())
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-rose-200 bg-rose-50/40 p-4">
                <div>
                    <p class="text-sm font-medium text-slate-800">Langkah 15 &mdash; Lapor ke SekDep</p>
                    <p class="text-xs text-slate-500">Belum input: {{ $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ') }}</p>
                </div>
                <form method="POST" action="{{ route('penata.sidang.lapor-sekdep', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-secondary">Lapor ke SekDep</button>
                </form>
            </div>
        @endif

        {{-- Pengelola Layanan: sahkan SK (Langkah 5) --}}
        @if ($user->hasRole('pengelola_layanan') && $sidang->status === \App\Enums\SidangStatus::MenungguProsesSk)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Langkah 5 &mdash; Sahkan &amp; Terbitkan SK Penguji</p>
                <form method="POST" action="{{ route('pengelola.sidang.sahkan-sk', $sidang) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="field-label">Nomor SK</label>
                        <input type="text" name="nomor_sk" class="field-input" placeholder="020/SK/FASILKOM/2026" required>
                    </div>
                    <div>
                        <label class="field-label">Berkas SK (opsional)</label>
                        <input type="file" name="sk_file" accept="application/pdf" class="field-input">
                    </div>
                    <button type="submit" class="btn-primary">Sahkan SK</button>
                </form>
            </div>
        @endif

        {{-- Pengelola Layanan: cek nilai & lapor (Langkah 13 & 14) --}}
        @if ($user->hasRole('pengelola_layanan') && $sidang->status === \App\Enums\SidangStatus::PelaksanaanUjian)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-1 text-sm font-medium text-slate-800">Langkah 13 &mdash; Periksa Nilai di SIMAK</p>
                <p class="mb-3 text-xs text-slate-500">
                    @if ($sidang->semuaNilaiSudahDiinput())
                        Seluruh nilai sudah lengkap. Klik untuk menyatakan sidang selesai.
                    @else
                        Belum input: {{ $sidang->pengujiBelumInputNilai()->pluck('nama')->implode(', ') }}
                    @endif
                </p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('pengelola.sidang.cek-nilai', $sidang) }}">
                        @csrf
                        <button type="submit" class="btn-primary">Cek Nilai di SIMAK</button>
                    </form>
                    @if (! $sidang->semuaNilaiSudahDiinput())
                        <form method="POST" action="{{ route('pengelola.sidang.lapor-penata', $sidang) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Lapor ke Penata (Langkah 14)</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Pengadministrasi: siapkan administrasi (Langkah 7) --}}
        @if ($user->hasRole('pengadministrasi_perkantoran') && $sidang->status === \App\Enums\SidangStatus::MenyiapkanAdministrasi)
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-3 text-sm font-medium text-slate-800">Langkah 7 &mdash; Siapkan Dokumen Administrasi Ujian</p>
                <form method="POST" action="{{ route('administrasi.sidang.siapkan', $sidang) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="field-label">Berkas Administrasi (opsional)</label>
                        <input type="file" name="dokumen_file" accept="application/pdf" class="field-input">
                    </div>
                    <button type="submit" class="btn-primary">Tandai Siap</button>
                </form>
            </div>
        @endif

        {{-- Pengadministrasi: info jadwal (Langkah 8) --}}
        @if ($user->hasRole('pengadministrasi_perkantoran') && $sidang->status === \App\Enums\SidangStatus::MenungguInfoJadwal)
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                <p class="text-sm font-medium text-slate-800">Langkah 8 &mdash; Sampaikan Info Jadwal ke Mahasiswa</p>
                <form method="POST" action="{{ route('administrasi.sidang.info-jadwal', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Sampaikan</button>
                </form>
            </div>
        @endif

        {{-- Pengadministrasi: cek kelengkapan berkas (Langkah 9) --}}
        @if ($user->hasRole('pengadministrasi_perkantoran') && $sidang->status === \App\Enums\SidangStatus::MenungguPengecekanBerkas)
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                <p class="text-sm font-medium text-slate-800">Langkah 9 &mdash; Cek Kelengkapan Berkas Sebelum Ujian</p>
                <form method="POST" action="{{ route('administrasi.sidang.cek-berkas', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Konfirmasi Lengkap</button>
                </form>
            </div>
        @endif

        {{-- Pengadministrasi: serahkan berkas ujian (Langkah 11) --}}
        @if ($user->hasRole('pengadministrasi_perkantoran') && $sidang->status === \App\Enums\SidangStatus::SiapUjian)
            @php $rendered = true; @endphp
            <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                <div>
                    <p class="text-sm font-medium text-slate-800">Langkah 11 &mdash; Serahkan Berkas Ujian ke Dosen Penguji</p>
                    <p class="text-xs text-slate-500">Dilakukan pada hari pelaksanaan ujian.</p>
                </div>
                <form method="POST" action="{{ route('administrasi.sidang.serahkan-berkas', $sidang) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Serahkan Berkas</button>
                </form>
            </div>
        @endif

        {{-- Dosen: konfirmasi berhalangan (Langkah 10a) --}}
        @if ($dosenSaya && $sidang->status === \App\Enums\SidangStatus::SiapUjian && $sidang->pengujis->pluck('id')->contains($dosenSaya->id))
            @php $rendered = true; @endphp
            <div class="mb-5 rounded-lg border border-slate-200 p-4">
                <p class="mb-1 text-sm font-medium text-slate-800">Langkah 10 &mdash; Konfirmasi Kehadiran</p>
                <p class="mb-3 text-xs text-slate-500">Apabila Anda berhalangan hadir pada jadwal yang telah ditetapkan, sampaikan konfirmasi berikut.</p>
                <form method="POST" action="{{ route('dosen.sidang.konfirmasi-berhalangan', $sidang) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="field-label">Alasan Berhalangan</label>
                        <input type="text" name="alasan" class="field-input" placeholder="Sedang bertugas luar kota" required>
                    </div>
                    <button type="submit" class="btn-danger">Konfirmasi Berhalangan</button>
                </form>
            </div>
        @endif

        {{-- Dosen: input nilai (Langkah 12) --}}
        @if ($dosenSaya && $sidang->status === \App\Enums\SidangStatus::PelaksanaanUjian)
            @php
                $pivotSaya = $sidang->pengujis->firstWhere('id', $dosenSaya->id);
            @endphp
            @if ($pivotSaya)
                @php $rendered = true; @endphp
                <div class="mb-5 flex items-center justify-between rounded-lg border border-slate-200 p-4">
                    <div>
                        <p class="text-sm font-medium text-slate-800">Langkah 12 &mdash; Input Nilai Ujian TA</p>
                        <p class="text-xs text-slate-500">
                            @if ($pivotSaya->pivot->nilai_diinput)
                                Anda sudah menginput nilai untuk sidang ini pada {{ $pivotSaya->pivot->waktu_input_nilai?->translatedFormat('d M Y, H:i') }}.
                            @else
                                Tandai setelah Anda menginput nilai pada SIMAK V3.
                            @endif
                        </p>
                    </div>
                    @unless ($pivotSaya->pivot->nilai_diinput)
                        <form method="POST" action="{{ route('dosen.sidang.input-nilai', $sidang) }}">
                            @csrf
                            <button type="submit" class="btn-primary">Tandai Sudah Input Nilai</button>
                        </form>
                    @endunless
                </div>
            @endif
        @endif

        @if (! $rendered)
            <p class="text-sm text-slate-400">Tidak ada tindakan yang perlu Anda lakukan untuk sidang ini saat ini.</p>
        @endif
    </div>

    {{-- Konten utama: timeline + info --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat Proses &mdash; 16 Langkah Bagan Alir POS</h3>
            @include('partials.timeline', ['sidang' => $sidang, 'langkahMaster' => $langkahMaster])
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Informasi Sidang</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Jadwal</dt>
                        <dd class="text-right font-medium text-slate-800">
                            @if ($sidang->tanggal_sidang)
                                {{ $sidang->tanggal_sidang->translatedFormat('d M Y') }} &middot; {{ \Illuminate\Support\Carbon::parse($sidang->jam_sidang)->format('H:i') }}
                            @else
                                Belum ditetapkan
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Tempat</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $sidang->tempat ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Nilai USEP</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $sidang->nilai_usep ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">DKN</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $sidang->dkn_terverifikasi ? 'Terverifikasi' : 'Belum diverifikasi' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Nomor SK Penguji</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $sidang->nomor_sk_penguji ?? '-' }}</dd>
                    </div>
                    @if ($sidang->jumlah_penjadwalan_ulang > 0)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Penjadwalan Ulang</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $sidang->jumlah_penjadwalan_ulang }}x</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Dewan Penguji</h3>
                @include('partials.penguji-list', ['sidang' => $sidang])
            </div>

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Dokumen</h3>
                @include('partials.dokumen-list', ['dokumens' => $sidang->dokumens, 'sidang' => $sidang])
            </div>
        </div>
    </div>
@endsection
