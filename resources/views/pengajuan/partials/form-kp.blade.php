@php /** @var \App\Models\Pengajuan|null $nilai */ @endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="mhs-label">Nama perusahaan / instansi</label>
        <input type="text" name="instansi_nama" value="{{ old('instansi_nama', $nilai?->instansi_nama) }}" maxlength="255" class="mhs-input" required>
        @error('instansi_nama')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="mhs-label">Alamat dan lokasi</label>
        <textarea name="instansi_alamat" rows="2" maxlength="1000" class="mhs-input" required>{{ old('instansi_alamat', $nilai?->instansi_alamat) }}</textarea>
        @error('instansi_alamat')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="mhs-label">Bidang / posisi kegiatan (opsional)</label>
        <input type="text" name="bidang_kegiatan" value="{{ old('bidang_kegiatan', $nilai?->bidang_kegiatan) }}" maxlength="255" class="mhs-input">
    </div>
    <div>
        <label class="mhs-label">Tanggal mulai</label>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $nilai?->tanggal_mulai?->toDateString()) }}" class="mhs-input" required>
        @error('tanggal_mulai')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mhs-label">Tanggal selesai</label>
        <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $nilai?->tanggal_selesai?->toDateString()) }}" class="mhs-input" required>
        @error('tanggal_selesai')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mhs-label">Pembimbing lapangan (data kontak, opsional)</label>
        <input type="text" name="pembimbing_lapangan_nama" value="{{ old('pembimbing_lapangan_nama', $nilai?->pembimbing_lapangan_nama) }}" maxlength="255" class="mhs-input">
    </div>
    <div>
        <label class="mhs-label">Kontak pembimbing lapangan (opsional)</label>
        <input type="text" name="pembimbing_lapangan_kontak" value="{{ old('pembimbing_lapangan_kontak', $nilai?->pembimbing_lapangan_kontak) }}" maxlength="255" class="mhs-input">
    </div>
</div>
