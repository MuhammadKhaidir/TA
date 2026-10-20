<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Models\Bimbingan;
use App\Models\JenisSurat;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\StrukturLaporan;
use App\Models\SuratPermohonan;
use App\Models\User;
use App\Services\PengajuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class ModulKpTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    private const DATA_KP = [
        'instansi_nama' => 'PT Contoh Teknologi', 'instansi_alamat' => 'Jl. Contoh No. 1', 'bidang_kegiatan' => 'Pengembangan web',
        'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2027-01-31',
        'pembimbing_lapangan_nama' => 'Bapak Lapangan', 'pembimbing_lapangan_kontak' => '0812000111',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
    }

    public function test_pengajuan_kp_menyimpan_data_tempat_kp_tanpa_membuat_akun_pembimbing_lapangan(): void
    {
        $mhs = $this->buatMahasiswa('SI');
        $usersSebelum = User::count();

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.store', 'kp'), self::DATA_KP)->assertSessionHasNoErrors();

        $kp = Pengajuan::firstOrFail();
        $this->assertSame(JenisPengajuan::Kp, $kp->jenis);
        $this->assertSame($this->prodi('SI')->id, $kp->prodi_id);
        $this->assertSame('Bapak Lapangan', $kp->pembimbing_lapangan_nama);
        $this->assertSame($usersSebelum, User::count(), 'Pembimbing lapangan hanya data kontak.');

        // Validasi tanggal.
        $this->actingAs($this->buatMahasiswa()->user)->post(route('mahasiswa.pengajuan.store', 'kp'), [...self::DATA_KP, 'tanggal_selesai' => '2026-10-01'])
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_alur_kp_hingga_diselesaikan_tanpa_sk(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $dospem = $this->buatDospem();
        $service = app(PengajuanService::class);

        $kp = $service->buat($mhs, JenisPengajuan::Kp, self::DATA_KP, $mhs->user);
        $kp = $service->kirim($kp, $mhs->user);
        $kp = $service->verifikasiAdministrasi($kp, $operator);
        $kp = $service->tetapkanDospem($kp, $dospem, $operator);
        $kp = $service->setujuiAkademik($kp, $dospem->user);
        $this->assertSame(StatusPengajuan::Disetujui, $kp->status);

        // KP tidak punya SK dan pratinjau SK bukan untuk KP.
        $this->actingAs($operator)->get(route('operator.sk.pratinjau', $kp))->assertNotFound();

        $this->actingAs($operator)->post(route('operator.pengajuan.selesaikan-kp', $kp))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Selesai, $kp->fresh()->status);
    }

    public function test_surat_kp_diajukan_mahasiswa_dan_diproses_operator(): void
    {
        $mhs = $this->buatMahasiswa('TI');
        $operator = $this->buatOperator(['TI']);
        $operatorLain = $this->buatOperator(['SI']);
        $kp = app(PengajuanService::class)->buat($mhs, JenisPengajuan::Kp, self::DATA_KP, $mhs->user);
        $jenis = JenisSurat::create(['kode' => 'surat-pengantar', 'nama' => 'Surat pengantar KP', 'aktif' => true]);

        $this->actingAs($mhs->user)->post(route('mahasiswa.surat.store', $kp), ['jenis_surat_id' => $jenis->id, 'catatan' => 'Untuk PT Contoh'])->assertSessionHasNoErrors();
        $surat = SuratPermohonan::firstOrFail();
        $this->assertSame('diajukan', $surat->status->value);

        // Permohonan ganda yang masih berjalan ditolak.
        $this->actingAs($mhs->user)->post(route('mahasiswa.surat.store', $kp), ['jenis_surat_id' => $jenis->id])->assertSessionHasErrors('workflow');

        // Operator prodi lain tidak berwenang.
        $this->actingAs($operatorLain)->post(route('operator.surat.proses', $surat), ['tujuan' => 'diproses'])->assertForbidden();

        // Menandai selesai tanpa surat hasil ditolak; dengan berkas diterima.
        $this->actingAs($operator)->post(route('operator.surat.proses', $surat), ['tujuan' => 'selesai'])->assertSessionHasErrors('workflow');
        $this->actingAs($operator)->post(route('operator.surat.proses', $surat), ['tujuan' => 'ditolak', 'catatan' => ''])->assertSessionHasErrors('workflow');
        $this->actingAs($operator)->post(route('operator.surat.proses', $surat), [
            'tujuan' => 'selesai', 'nomor_surat' => '123/KP/2026', 'berkas' => UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('selesai', $surat->status->value);
        $this->assertSame($operator->id, $surat->diproses_oleh);

        $this->actingAs($mhs->user)->get(route('surat.unduh', $surat))->assertOk();
        $this->actingAs($this->buatMahasiswa()->user)->get(route('surat.unduh', $surat))->assertForbidden();

        // Setelah selesai tidak dapat diproses lagi.
        $this->actingAs($operator)->post(route('operator.surat.proses', $surat), ['tujuan' => 'diproses'])->assertSessionHasErrors('workflow');
    }

    public function test_bimbingan_dicatat_divalidasi_dihitung_dan_minimum_dapat_dikonfigurasi(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $dospem = $this->buatDospem();
        $service = app(PengajuanService::class);

        // Sebelum pembimbing ditetapkan, bimbingan belum dapat dicatat.
        $kp = $service->buat($mhs, JenisPengajuan::Kp, self::DATA_KP, $mhs->user);
        $this->actingAs($mhs->user)->post(route('mahasiswa.bimbingan.store', 'kp'), ['tanggal' => now()->toDateString(), 'topik' => 'Kickoff'])
            ->assertSessionHasErrors('workflow');

        $kp = $service->kirim($kp, $mhs->user);
        $service->tetapkanDospem($kp, $dospem, $operator);

        foreach ([1, 2, 3] as $hari) {
            $this->actingAs($mhs->user)->post(route('mahasiswa.bimbingan.store', 'kp'), [
                'tanggal' => now()->subDays($hari)->toDateString(), 'topik' => "Topik {$hari}", 'catatan' => 'Hasil', 'tindak_lanjut' => 'Revisi bab 1',
            ])->assertSessionHasNoErrors();
        }
        // Tanggal sama tidak boleh ganda; tanggal masa depan ditolak.
        $this->actingAs($mhs->user)->post(route('mahasiswa.bimbingan.store', 'kp'), ['tanggal' => now()->subDay()->toDateString(), 'topik' => 'Dobel'])->assertSessionHasErrors('workflow');
        $this->actingAs($mhs->user)->post(route('mahasiswa.bimbingan.store', 'kp'), ['tanggal' => now()->addDay()->toDateString(), 'topik' => 'Besok'])->assertSessionHasErrors('tanggal');

        $semua = Bimbingan::orderBy('tanggal')->get();
        $this->actingAs($dospem->user)->post(route('dospem.bimbingan.validasi', $semua[0]), ['keputusan' => 'validasi'])->assertSessionHasNoErrors();
        $this->actingAs($dospem->user)->post(route('dospem.bimbingan.validasi', $semua[1]), ['keputusan' => 'perbaiki', 'catatan' => ''])->assertSessionHasErrors('workflow');
        $this->actingAs($dospem->user)->post(route('dospem.bimbingan.validasi', $semua[1]), ['keputusan' => 'perbaiki', 'catatan' => 'Lengkapi topik'])->assertSessionHasNoErrors();

        // Bimbingan yang sudah divalidasi tidak dapat dihapus mahasiswa.
        $this->actingAs($mhs->user)->delete(route('mahasiswa.bimbingan.destroy', $semua[0]))->assertSessionHasErrors('workflow');

        // Jumlah tercatat vs tervalidasi; tidak ada angka minimum bila kampus belum menetapkan.
        $ringkasan = $mhs->fresh()->ringkasanBimbingan(JenisPengajuan::Kp);
        $this->assertSame(['tercatat' => 3, 'divalidasi' => 1, 'minimal' => null], $ringkasan);

        Pengaturan::simpan('bimbingan.minimal_kp', '8');
        $this->assertSame(8, $mhs->fresh()->ringkasanBimbingan(JenisPengajuan::Kp)['minimal']);
        $this->actingAs($mhs->user)->get(route('mahasiswa.dashboard'))->assertOk()->assertSee('Minimum')->assertSee('8');
    }

    public function test_struktur_laporan_dan_lampiran_tampil_dan_pengumpulan_dikendalikan_admin(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $dospem = $this->buatDospem();
        $service = app(PengajuanService::class);
        StrukturLaporan::create(['urutan' => 1, 'judul' => 'BAB I Pendahuluan', 'deskripsi' => 'Latar belakang KP']);
        StrukturLaporan::create(['urutan' => 2, 'judul' => 'Bagian Nonaktif', 'aktif' => false]);
        $lampiran = Persyaratan::create(['jenis' => 'kp_lampiran', 'nama' => 'Logbook', 'wajib' => true, 'waktu_penyerahan' => 'Saat laporan']);

        $kp = $service->buat($mhs, JenisPengajuan::Kp, self::DATA_KP, $mhs->user);
        $this->actingAs($mhs->user)->get(route('mahasiswa.dashboard'))->assertOk()
            ->assertSee('BAB I Pendahuluan')->assertDontSee('Bagian Nonaktif')->assertSee('Logbook');

        // Belum disetujui / pengumpulan belum dibuka -> unggah ditolak.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$kp, $lampiran]), ['berkas' => $this->berkas('logbook.pdf')])
            ->assertSessionHasErrors('workflow');

        $kp = $service->kirim($kp, $mhs->user);
        $kp = $service->verifikasiAdministrasi($kp, $operator);
        $kp = $service->tetapkanDospem($kp, $dospem, $operator);
        $service->setujuiAkademik($kp, $dospem->user);

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$kp, $lampiran]), ['berkas' => $this->berkas('logbook.pdf')])
            ->assertSessionHasErrors('workflow');

        Pengaturan::simpan('kp.pengumpulan_lampiran_aktif', '1');
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$kp, $lampiran]), ['berkas' => $this->berkas('logbook.pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $kp->dokumens()->count());
    }
}
