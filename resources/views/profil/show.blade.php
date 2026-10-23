@extends('layouts.app')

@section('judul', 'Profil')

@section('konten')
    <x-page-header judul="Profil" sub="Data identitas dan akademik dikelola oleh admin. Anda dapat memperbarui nomor HP dan kata sandi." />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Informasi Akun</h2>
                <span class="badge bg-navy-50 text-navy-800 ring-navy-200">{{ $user->role->label() }}</span>
            </div>
            <dl class="divide-y divide-slate-100 px-5 py-2">
                <div class="dl-baris"><dt>Nama</dt><dd>{{ $user->name }}</dd></div>
                <div class="dl-baris"><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                <div class="dl-baris"><dt>{{ $user->role->labelNomorInduk() }}</dt><dd>{{ $user->nomor_induk ?? '-' }}</dd></div>
                <div class="dl-baris"><dt>Program Studi</dt><dd>{{ $user->programStudi?->namaLengkap() ?? '-' }}</dd></div>
                @if ($user->role === \App\Enums\Role::Mahasiswa)
                    <div class="dl-baris"><dt>Angkatan</dt><dd>{{ $user->angkatan ?? '-' }}</dd></div>
                @endif
                <div class="dl-baris"><dt>Nomor HP</dt><dd>{{ $user->no_hp ?? '-' }}</dd></div>
            </dl>
        </section>

        <div class="space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="font-semibold text-slate-900">Ubah Nomor HP</h2></div>
                <form method="POST" action="{{ route('profil.update') }}" class="card-body space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="no_hp" class="label">Nomor HP</label>
                        <input id="no_hp" name="no_hp" type="text" value="{{ old('no_hp', $user->no_hp) }}" class="input" autocomplete="tel">
                        @error('no_hp')<p class="galat">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="font-semibold text-slate-900">Ganti Kata Sandi</h2></div>
                <form method="POST" action="{{ route('profil.password') }}" class="card-body space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="password_lama" class="label">Kata sandi saat ini</label>
                        <input id="password_lama" name="password_lama" type="password" class="input" autocomplete="current-password" required>
                        @error('password_lama')<p class="galat">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="label">Kata sandi baru</label>
                        <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                        <p class="hint">Minimal 8 karakter.</p>
                        @error('password')<p class="galat">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="label">Ulangi kata sandi baru</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Ganti kata sandi</button>
                </form>
            </section>
        </div>
    </div>
@endsection
