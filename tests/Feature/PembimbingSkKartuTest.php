<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Dosen;
use App\Models\Pengaturan;
use App\Models\SuratKeputusan;
use App\Services\BimbinganService;
use App\Services\KartuKonsultasiService;
use App\Services\PengajuanService;
use App\Services\SkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Pembimbing 1 dan 2, SK jalur offline, dan kartu konsultasi PDF. */
class PembimbingSkKartuTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
    }

    // ------------------------------------------------------------------
    // Pembimbing 1 dan 2
    // ------------------------------------------------------------------

    public function test_pembimbing_1_dan_2_dipilih_dari_master_dosen_dan_harus_berbeda(): void
    {
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'terverifikasi', null, $operator);
        $pengajuan = $pengajuan->fresh();
        $service = app(PengajuanService::class);

        $this->expectException(WorkflowException::class);
        try {
            $service->tetapkanDospem($pengajuan, $a, $operator, $a);
        } finally {
            $this->assertNull($pengajuan->fresh()->dospem_id);
        }
    }

    public function test_pembimbing_2_wajib_kecuali_pengaturan_menyatakan_lain(): void
    {
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $service = app(PengajuanService::class);
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'terverifikasi', null, $operator);

        // Bawaan: wajib.
        try {
            $service->tetapkanDospem($pengajuan, $a, $operator);
            $this->fail('Pembimbing 2 seharusnya wajib.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('Pembimbing 2 wajib', $e->getMessage());
        }

        // Fakultas menyatakan tidak wajib [K-07]: cukup Pembimbing 1, dan persetujuan hanya satu.
        Pengaturan::simpan('ta.pembimbing_kedua_wajib', '0');
        $pengajuan = $service->tetapkanDospem($pengajuan, $a, $operator);
        $this->assertNull($pengajuan->dospem2_id);
        $pengajuan = $service->setujuiAkademik($pengajuan, $a->user);
        $this->assertSame(StatusAkademik::Disetujui, $pengajuan->status_akademik);
    }

    public function test_dosen_nonaktif_atau_tanpa_akun_tidak_sah_sebagai_pembimbing(): void
    {
        $operator = $this->buatOperator();
        $sah = $this->buatDospem();
        $nonaktif = $this->buatDospem();
        $nonaktif->update(['aktif' => false]);
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'terverifikasi', null, $operator);
        $service = app(PengajuanService::class);

        foreach ([[$sah, $nonaktif], [$nonaktif, $sah]] as [$satu, $dua]) {
            try {
                $service->tetapkanDospem($pengajuan, $satu, $operator, $dua);
                $this->fail('Dosen nonaktif seharusnya ditolak.');
            } catch (WorkflowException) {
                $this->assertNull($pengajuan->fresh()->dospem_id);
            }
        }
    }

    public function test_persetujuan_akademik_baru_selesai_setelah_kedua_pembimbing_menyetujui(): void
    {
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $lain = $this->buatDospem();
        $service = app(PengajuanService::class);
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'terverifikasi', null, $operator);
        $pengajuan = $service->tetapkanDospem($pengajuan, $a, $operator, $b);

        // Dosen yang bukan pembimbing ditolak (web: 403).
        $this->actingAs($lain->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertForbidden();

        $this->actingAs($a->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertSessionHasNoErrors();
        $pengajuan->refresh();
        $this->assertNotNull($pengajuan->setuju_dospem1_pada);
        $this->assertNull($pengajuan->setuju_dospem2_pada);
        $this->assertSame(StatusAkademik::Menunggu, $pengajuan->status_akademik);
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->status);

        // Persetujuan ganda oleh pembimbing yang sama tidak dihitung dua kali.
        $this->actingAs($a->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertSessionHasErrors('workflow');
        $this->assertSame(StatusAkademik::Menunggu, $pengajuan->fresh()->status_akademik);

        $this->actingAs($b->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertSessionHasNoErrors();
        $this->assertSame(StatusAkademik::Disetujui, $pengajuan->fresh()->status_akademik);
        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->fresh()->status);
    }

    public function test_pembimbing_2_dapat_memvalidasi_bimbingan_dan_dospem_lain_tidak(): void
    {
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $lain = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $this->taDenganSk($mhs, $a, $b);

        $bimbingan = app(BimbinganService::class)->catat($mhs, JenisPengajuan::Ta, ['tanggal' => now()->toDateString(), 'topik' => 'Bab 1'], $this->berkasGambar(), $mhs->user);

        $this->actingAs($lain->user)->post(route('dospem.bimbingan.validasi', $bimbingan), ['keputusan' => 'validasi'])->assertForbidden();
        $this->actingAs($b->user)->post(route('dospem.bimbingan.validasi', $bimbingan), ['keputusan' => 'validasi'])->assertSessionHasNoErrors();
        $this->assertSame('divalidasi', $bimbingan->fresh()->status_validasi->value);
    }

    // ------------------------------------------------------------------
    // SK jalur offline
    // ------------------------------------------------------------------

    private function salinanSk(string $nama = 'sk-resmi.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n%sk resmi\n");
    }

    public function test_mode_offline_menutup_penerbitan_website_dan_mencatat_sk_resmi(): void
    {
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui', null, $operator);
        Pengaturan::simpan('sk.mode', 'offline');

        // Penerbitan dari template tidak tersedia; pencatatan hanya oleh Admin (bawaan).
        $this->konfigurasiSk();
        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasErrors('workflow');
        $this->actingAs($operator)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertForbidden();
        $this->actingAs($mhs->user)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertForbidden();
        $this->assertDatabaseCount('surat_keputusans', 0);

        // Validasi input: nomor, tanggal (tidak di masa depan), salinan (pdf/gambar).
        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => '', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertSessionHasErrors('nomor');
        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->addDay()->toDateString(), 'salinan' => $this->salinanSk()])->assertSessionHasErrors('tanggal');
        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => UploadedFile::fake()->create('x.exe', 5)])->assertSessionHasErrors('salinan');
        $this->assertDatabaseCount('surat_keputusans', 0);

        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => '123/UN9.FASILKOM/TU/2026', 'tanggal' => '2026-10-01', 'salinan' => $this->salinanSk()])->assertSessionHasNoErrors();

        $sk = SuratKeputusan::firstOrFail();
        $this->assertSame('offline', $sk->sumber);
        $this->assertTrue($sk->dicatatOffline());
        $this->assertSame('123/UN9.FASILKOM/TU/2026', $sk->nomor);
        $this->assertSame('2026-10-01', $sk->tanggal->toDateString());
        $this->assertNull($sk->template_sk_id);
        $this->assertSame($admin->id, $sk->diterbitkan_oleh);
        Storage::disk('local')->assertExists($sk->file_path);
        $this->assertSame(StatusPengajuan::Selesai, $pengajuan->fresh()->status);

        // Mahasiswa mengunduh salinan resmi, pihak luar tidak.
        $this->actingAs($mhs->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertOk();
        $this->actingAs($this->buatMahasiswa()->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertForbidden();

        // Tidak bisa dicatat dua kali.
        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/2', 'tanggal' => '2026-10-02', 'salinan' => $this->salinanSk()])->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_keputusans', 1);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'sk_dicatat_offline']);
    }

    public function test_nomor_sk_offline_tidak_boleh_kembar_dan_berkas_tidak_menggantung(): void
    {
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        Pengaturan::simpan('sk.mode', 'offline');
        $p1 = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);
        $p2 = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);
        $service = app(SkService::class);

        $service->catatOffline($p1, $admin, 'SK/77', '2026-10-01', $this->salinanSk());
        $berkasSebelum = Storage::disk('local')->allFiles('sk');

        try {
            $service->catatOffline($p2, $admin, 'SK/77', '2026-10-01', $this->salinanSk('lain.pdf'));
            $this->fail('Nomor SK kembar seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('sudah dipakai', $e->getMessage());
        }

        $this->assertDatabaseCount('surat_keputusans', 1);
        $this->assertSame(StatusPengajuan::Disetujui, $p2->fresh()->status);
        $this->assertEqualsCanonicalizing($berkasSebelum, Storage::disk('local')->allFiles('sk'));
    }

    public function test_jalur_website_menolak_pencatatan_offline(): void
    {
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);

        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_keputusans', 0);
    }

    public function test_sk_tidak_terbit_di_kedua_jalur_tanpa_surat_balasan_valid(): void
    {
        $this->konfigurasiSk();
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);
        // Simulasikan surat balasan yang belum valid.
        $pengajuan->suratBalasan()->update(['status' => 'tidak_valid']);
        $service = app(SkService::class);

        try {
            $service->terbitkan($pengajuan->fresh(), $operator);
            $this->fail('SK website seharusnya tertahan.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('surat balasan', mb_strtolower($e->getMessage()));
        }

        Pengaturan::simpan('sk.mode', 'offline');
        $this->expectException(WorkflowException::class);
        $service->catatOffline($pengajuan->fresh(), $admin, 'SK/1', '2026-10-01', $this->salinanSk());
    }

    public function test_pengaturan_pelaku_pencatatan_sk_dapat_dialihkan_ke_operator(): void
    {
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);
        Pengaturan::simpan('sk.mode', 'offline');
        Pengaturan::simpan('akses.catat_sk', 'operator_akademik');

        $this->actingAs($admin)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertForbidden();
        $this->actingAs($operator)->post(route('operator.sk.catat', $pengajuan), ['nomor' => 'SK/1', 'tanggal' => now()->toDateString(), 'salinan' => $this->salinanSk()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('surat_keputusans', 1);
    }

    // ------------------------------------------------------------------
    // Kartu konsultasi
    // ------------------------------------------------------------------

    public function test_kartu_konsultasi_terkunci_sampai_sk_terbit_lalu_dapat_diunduh(): void
    {
        $this->konfigurasiSk();
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui', $a, $operator, $b);
        $kartu = app(KartuKonsultasiService::class);

        $this->assertTrue($kartu->terkunci($pengajuan));
        $this->actingAs($mhs->user)->get(route('pengajuan.kartu.unduh', $pengajuan))->assertRedirect()->assertSessionHasErrors('workflow');
        try {
            $kartu->render($pengajuan);
            $this->fail('Kartu seharusnya terkunci.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('SK TA terbit', $e->getMessage());
        }

        app(BimbinganService::class)->catat($mhs, JenisPengajuan::Ta, ['tanggal' => now()->subDays(3)->toDateString(), 'topik' => 'Latar belakang masalah'], null, $mhs->user);
        app(SkService::class)->terbitkan($pengajuan, $operator);
        $pengajuan = $pengajuan->fresh();

        $this->assertFalse($kartu->terkunci($pengajuan));
        $respons = $this->actingAs($mhs->user)->get(route('pengajuan.kartu.unduh', $pengajuan));
        $respons->assertOk();
        $this->assertSame('application/pdf', $respons->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $respons->getContent());

        // Isi kartu memuat identitas, kedua pembimbing, dan topik bimbingan.
        $html = $kartu->renderHtml($pengajuan);
        $this->assertStringContainsString($mhs->nama, $html);
        $this->assertStringContainsString($a->nama, $html);
        $this->assertStringContainsString($b->nama, $html);
        $this->assertStringContainsString('Latar belakang masalah', $html);

        // Dospem pembimbing dan operator boleh; mahasiswa lain tidak.
        $this->actingAs($a->user)->get(route('pengajuan.kartu.unduh', $pengajuan))->assertOk();
        $this->actingAs($operator)->get(route('pengajuan.kartu.unduh', $pengajuan))->assertOk();
        $this->actingAs($this->buatMahasiswa()->user)->get(route('pengajuan.kartu.unduh', $pengajuan))->assertForbidden();
    }

    public function test_kunci_kartu_dapat_dimatikan_lewat_pengaturan_dan_template_dapat_diganti(): void
    {
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui');
        $kartu = app(KartuKonsultasiService::class);

        $this->assertTrue($kartu->terkunci($pengajuan));
        Pengaturan::simpan('kartu.butuh_sk', '0');
        $this->assertFalse($kartu->terkunci($pengajuan));

        // Format resmi fakultas cukup diganti lewat config tanpa menyentuh service [K-04].
        $dir = resource_path('views/pdf');
        $contoh = $dir.'/kartu-uji-format-lain.blade.php';
        file_put_contents($contoh, '<p>FORMAT LAIN {{ $mahasiswa->nim }}</p>');
        try {
            config(['ta.kartu_view' => 'pdf.kartu-uji-format-lain']);
            $this->assertStringContainsString('FORMAT LAIN '.$mhs->nim, $kartu->renderHtml($pengajuan));
        } finally {
            @unlink($contoh);
        }
    }

    public function test_kartu_konsultasi_tidak_tersedia_untuk_kp(): void
    {
        $mhs = $this->buatMahasiswa();
        $kp = app(PengajuanService::class)->buat($mhs, JenisPengajuan::Kp, ['judul' => 'KP uji'], $mhs->user);
        Pengaturan::simpan('kartu.butuh_sk', '0');

        $this->assertTrue(app(KartuKonsultasiService::class)->terkunci($kp));
        $this->actingAs($mhs->user)->get(route('pengajuan.kartu.unduh', $kp))->assertSessionHasErrors('workflow');
    }

    /** Pembantu: gambar kecil untuk bukti bimbingan. */
    private function berkasGambar(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg', 50, 50);
    }
}
