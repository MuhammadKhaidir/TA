<?php

namespace Tests\Concerns;

use App\Enums\JenisPengajuan;
use App\Enums\Peran;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\Prodi;
use App\Models\SuratBalasan;
use App\Models\SuratPengantar;
use App\Models\TemplateSk;
use App\Models\User;
use App\Services\PengajuanService;
use App\Services\SkService;
use App\Services\SuratBalasanService;
use App\Services\SuratPengantarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Pembantu pembuatan data uji. Empat role dan prodi TI/SI/SK sudah tersedia
 * dari migrasi, jadi tidak perlu seeder.
 */
trait MenyiapkanData
{
    private int $urutNim = 0;

    protected function siapkanLingkungan(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutVite();
    }

    protected function prodi(string $kode = 'TI'): Prodi
    {
        return Prodi::query()->where('kode', $kode)->firstOrFail();
    }

    protected function buatUser(Peran $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran->value);

        return $user;
    }

    protected function buatMahasiswa(string $kodeProdi = 'TI'): Mahasiswa
    {
        $user = $this->buatUser(Peran::Mahasiswa);

        return Mahasiswa::create([
            'user_id' => $user->id,
            'nim' => '0902118'.str_pad((string) ++$this->urutNim, 6, '0', STR_PAD_LEFT),
            'nama' => $user->name,
            'prodi_id' => $this->prodi($kodeProdi)->id,
            'angkatan' => '2022',
        ]);
    }

    protected function buatDospem(string $kodeProdi = 'TI'): Dosen
    {
        $user = $this->buatUser(Peran::Dospem);

        return Dosen::create([
            'user_id' => $user->id,
            'nip' => '1980'.str_pad((string) ++$this->urutNim, 10, '0', STR_PAD_LEFT),
            'nama' => $user->name,
            'prodi_id' => $this->prodi($kodeProdi)->id,
            'aktif' => true,
        ]);
    }

    /** Operator dengan cakupan tertentu; tanpa argumen = melayani semua prodi. */
    protected function buatOperator(?array $kodeProdi = null): User
    {
        $operator = $this->buatUser(Peran::OperatorAkademik, ['semua_prodi' => $kodeProdi === null]);
        if ($kodeProdi) {
            $operator->prodiOperator()->sync(Prodi::query()->whereIn('kode', $kodeProdi)->pluck('id'));
        }

        return $operator;
    }

    protected function buatAdmin(): User
    {
        return $this->buatUser(Peran::Admin);
    }

    protected function persyaratanProposal(bool $wajib = true): Persyaratan
    {
        return Persyaratan::create(['jenis' => 'ta_pengajuan', 'nama' => 'Proposal TA', 'wajib' => $wajib, 'urutan' => 1]);
    }

    protected function konfigurasiSk(): TemplateSk
    {
        Pengaturan::simpan('sk.format_nomor', '{urut}/SK/{kode_prodi}/{tahun}');
        Pengaturan::simpan('sk.kota', 'Palembang');
        Pengaturan::simpan('sk.penandatangan_nama', 'Dr. Pejabat Uji');
        Pengaturan::simpan('sk.penandatangan_jabatan', 'Dekan Uji');

        return TemplateSk::create([
            'nama' => 'Template uji',
            'aktif' => true,
            'isi' => '<h3 class="tengah">SK {{nomor_sk}}</h3><p>{{nama_mahasiswa}} ({{nim}}) — {{prodi}}</p><p>{{judul}}</p><p>{{dosen_pembimbing}}</p><p>{{dosen_pembimbing_2}}</p><p>{{kota}}, {{tanggal}}<br>{{penandatangan_nama}}</p>',
        ]);
    }

    protected function berkas(string $nama = 'proposal.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n%uji\n");
    }

    /**
     * Bawa pengajuan TA sampai tahap tertentu: draft | diajukan | terverifikasi | disetujui.
     * Memakai service yang sama dengan aplikasi, sehingga status selalu sah. Sejak revisi, pengajuan TA
     * lebih dulu melewati surat pengantar dan surat balasan valid (gerbang SK) sebelum boleh dikirim,
     * dan pembimbing 1 serta 2 sama-sama menyetujui. $dospem2 dibuat otomatis bila tidak diberikan.
     */
    protected function pengajuanTa(Mahasiswa $mahasiswa, string $tahap = 'draft', ?Dosen $dospem = null, ?User $operator = null, ?Dosen $dospem2 = null): Pengajuan
    {
        $service = app(PengajuanService::class);
        $pengajuan = $service->buat($mahasiswa, JenisPengajuan::Ta, ['judul' => 'Sistem Uji Coba Otomatis'], $mahasiswa->user);
        if ($tahap === 'draft') {
            return $pengajuan;
        }

        $operator ??= $this->buatOperator();
        $this->lewatiGerbangSk($pengajuan, $mahasiswa, $operator);

        foreach ($pengajuan->persyaratanPengajuan() as $syarat) {
            $service->unggahDokumen($pengajuan, $syarat, $this->berkas(), $mahasiswa->user);
        }
        $pengajuan = $service->kirim($pengajuan, $mahasiswa->user);
        if ($tahap === 'diajukan') {
            return $pengajuan;
        }

        $pengajuan = $service->verifikasiAdministrasi($pengajuan, $operator);
        if ($tahap === 'terverifikasi') {
            return $pengajuan;
        }

        $dospem ??= $this->buatDospem($mahasiswa->prodi->kode);
        $dospem2 ??= $this->buatDospem($mahasiswa->prodi->kode);
        $pengajuan = $service->tetapkanDospem($pengajuan, $dospem, $operator, $dospem2);
        $pengajuan = $service->setujuiAkademik($pengajuan, $dospem->user);

        return $service->setujuiAkademik($pengajuan, $dospem2->user);
    }

    /** Isian surat pengantar yang lengkap menurut config/ta.php. */
    protected function isianSuratPengantar(): array
    {
        return collect(config('ta.surat_pengantar_data'))->map(fn ($def, $kunci) => $def[1] === 'date' ? '2026-11-01' : 'Isian uji '.$kunci)->all();
    }

    /** Dokumen Kumpulan A diunggah semua (bila ada persyaratannya), lalu surat dikirim. */
    protected function kirimSuratPengantar(Pengajuan $pengajuan, Mahasiswa $mahasiswa): SuratPengantar
    {
        $service = app(SuratPengantarService::class);
        $surat = $service->simpanData($pengajuan, $this->isianSuratPengantar(), $mahasiswa->user);
        foreach ($pengajuan->persyaratanPengantar() as $syarat) {
            app(PengajuanService::class)->unggahDokumen($pengajuan, $syarat, $this->berkas(), $mahasiswa->user);
        }

        return $service->kirim($surat, $mahasiswa->user);
    }

    /** Surat pengantar selesai diproses dan surat balasan dinyatakan valid: syarat mengirim pengajuan SK. */
    protected function lewatiGerbangSk(Pengajuan $pengajuan, Mahasiswa $mahasiswa, ?User $operator = null): SuratBalasan
    {
        $operator ??= $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengantar = app(SuratPengantarService::class);

        $surat = $this->kirimSuratPengantar($pengajuan, $mahasiswa);
        $surat = $pengantar->periksa($surat, $operator);
        $surat = $pengantar->proses($surat, $admin);
        $pengantar->selesai($surat, $admin);

        $balasan = app(SuratBalasanService::class);
        $sb = $balasan->unggah($pengajuan, $this->berkas('balasan.pdf'), $mahasiswa->user);

        return $balasan->validasi($sb, $operator, array_fill_keys(array_keys(SuratBalasan::daftarChecklist()), true), null);
    }

    /** TA yang SK-nya sudah terbit lewat jalur website (konfigurasi SK ikut disiapkan). */
    protected function taDenganSk(?Mahasiswa $mahasiswa = null, ?Dosen $dospem = null, ?Dosen $dospem2 = null): Pengajuan
    {
        $this->konfigurasiSk();
        $mahasiswa ??= $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mahasiswa, 'disetujui', $dospem, $operator, $dospem2);
        app(SkService::class)->terbitkan($pengajuan, $operator);

        return $pengajuan->fresh();
    }
}
