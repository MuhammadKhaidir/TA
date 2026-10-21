<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSuratBalasan;
use App\Enums\StatusSuratPengantar;
use App\Exceptions\WorkflowException;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\SuratBalasan;
use App\Models\SuratPengantar;
use App\Services\PengajuanService;
use App\Services\SuratBalasanService;
use App\Services\SuratPengantarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Surat pengantar TA, surat balasan, dan gerbang pengiriman pengajuan SK. */
class SuratPengantarBalasanTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
    }

    private function dokumenKumpulanA(int $jumlah = 2): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            Persyaratan::create(['jenis' => 'ta_surat_pengantar', 'nama' => "Dokumen A{$i}", 'wajib' => true, 'urutan' => $i]);
        }
    }

    public function test_surat_pengantar_tidak_bisa_dikirim_bila_isian_atau_dokumen_kumpulan_a_kurang(): void
    {
        $this->dokumenKumpulanA();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs);
        $service = app(SuratPengantarService::class);

        // Isian belum ada sama sekali.
        $surat = $service->simpanData($pengajuan, [], $mhs->user);
        try {
            $service->kirim($surat, $mhs->user);
            $this->fail('Surat pengantar tanpa isian seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('belum lengkap', $e->getMessage());
        }

        // Isian lengkap tetapi dokumen belum diunggah.
        $surat = $service->simpanData($pengajuan, $this->isianSuratPengantar(), $mhs->user);
        try {
            $service->kirim($surat, $mhs->user);
            $this->fail('Surat pengantar tanpa dokumen Kumpulan A seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('Dokumen A1', $e->getMessage());
        }
        $this->assertSame(StatusSuratPengantar::Draft, $surat->fresh()->status);

        // Sesudah semuanya lengkap, surat terkirim.
        $terkirim = $this->kirimSuratPengantar($pengajuan, $mhs);
        $this->assertSame(StatusSuratPengantar::Diajukan, $terkirim->status);
        $this->assertSame(1, SuratPengantar::count());
    }

    public function test_alur_surat_pengantar_periksa_kembalikan_proses_selesai_lewat_http(): void
    {
        $this->dokumenKumpulanA();
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($mhs);

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.simpan', $pengajuan), $this->isianSuratPengantar())->assertSessionHasNoErrors();
        foreach ($pengajuan->persyaratanPengantar() as $syarat) {
            $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$pengajuan, $syarat]), ['berkas' => $this->berkas()])->assertSessionHasNoErrors();
        }
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.kirim', $pengajuan))->assertSessionHasNoErrors();
        $surat = $pengajuan->fresh()->suratPengantar;
        $this->assertSame(StatusSuratPengantar::Diajukan, $surat->status);

        // Mahasiswa tidak boleh memeriksa; Admin tidak memeriksa kelengkapan menurut pengaturan bawaan.
        $this->actingAs($mhs->user)->post(route('operator.pengantar.periksa', $surat))->assertForbidden();
        $this->actingAs($admin)->post(route('operator.pengantar.periksa', $surat))->assertForbidden();

        // Operator mengembalikan: catatan wajib, mahasiswa memperbaiki lalu mengirim ulang.
        $this->actingAs($operator)->post(route('operator.pengantar.kembalikan', $surat), ['catatan' => ''])->assertSessionHasErrors('catatan');
        $this->actingAs($operator)->post(route('operator.pengantar.kembalikan', $surat), ['catatan' => 'Alamat instansi kurang jelas'])->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratPengantar::PerluRevisi, $surat->fresh()->status);

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.simpan', $pengajuan), array_merge($this->isianSuratPengantar(), ['instansi_alamat' => 'Jl. Perbaikan No. 1']))->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.kirim', $pengajuan))->assertSessionHasNoErrors();
        $this->assertSame('Jl. Perbaikan No. 1', $surat->fresh()->data['instansi_alamat']);

        // Operator memverifikasi kelengkapan, tetapi tidak boleh memproses (itu tugas Admin).
        $this->actingAs($operator)->post(route('operator.pengantar.periksa', $surat))->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratPengantar::Lengkap, $surat->fresh()->status);
        $this->actingAs($operator)->post(route('operator.pengantar.proses', $surat), ['nomor_surat' => 'X'])->assertForbidden();
        $this->actingAs($mhs->user)->post(route('operator.pengantar.proses', $surat), ['nomor_surat' => 'X'])->assertForbidden();

        // Admin memproses lalu menyelesaikan; tidak bisa melompati tahap.
        $this->actingAs($admin)->post(route('operator.pengantar.selesai', $surat))->assertSessionHasErrors('workflow');
        $this->actingAs($admin)->post(route('operator.pengantar.proses', $surat), ['nomor_surat' => 'SP/001/2026'])->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratPengantar::Diproses, $surat->fresh()->status);
        $this->actingAs($admin)->post(route('operator.pengantar.selesai', $surat))->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratPengantar::Selesai, $surat->fresh()->status);

        // Surat yang sudah selesai tidak dapat diubah mahasiswa lagi.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.simpan', $pengajuan), $this->isianSuratPengantar())->assertSessionHasErrors('workflow');

        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'surat_pengantar_selesai']);
    }

    public function test_surat_pengantar_milik_mahasiswa_lain_dan_prodi_lain_tidak_bisa_disentuh(): void
    {
        $this->dokumenKumpulanA(0);
        $a = $this->buatMahasiswa('TI');
        $b = $this->buatMahasiswa('TI');
        $pengajuanA = $this->pengajuanTa($a);
        $surat = $this->kirimSuratPengantar($pengajuanA, $a);

        $this->actingAs($b->user)->post(route('mahasiswa.pengantar.simpan', $pengajuanA), $this->isianSuratPengantar())->assertForbidden();
        $this->actingAs($b->user)->post(route('mahasiswa.pengantar.kirim', $pengajuanA))->assertForbidden();

        $operatorSi = $this->buatOperator(['SI']);
        $this->actingAs($operatorSi)->post(route('operator.pengantar.periksa', $surat))->assertForbidden();
        $this->assertSame(StatusSuratPengantar::Diajukan, $surat->fresh()->status);

        $this->actingAs($this->buatOperator(['TI']))->post(route('operator.pengantar.periksa', $surat))->assertSessionHasNoErrors();
    }

    public function test_pelaku_tugas_dapat_dialihkan_lewat_pengaturan_tanpa_mengubah_kode(): void
    {
        $this->dokumenKumpulanA(0);
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($mhs);
        $surat = $this->kirimSuratPengantar($pengajuan, $mhs);

        // Pengaturan bawaan: Operator memeriksa, Admin memproses.
        $this->actingAs($operator)->post(route('operator.pengantar.periksa', $surat))->assertSessionHasNoErrors();

        // Fakultas ternyata menetapkan sebaliknya [K-01]: Operator memproses, Admin memeriksa kelengkapan.
        Pengaturan::simpan('akses.proses_surat_pengantar', 'operator_akademik');
        Pengaturan::simpan('akses.periksa_kelengkapan', 'admin');

        $this->actingAs($admin)->post(route('operator.pengantar.proses', $surat))->assertForbidden();
        $this->actingAs($operator)->post(route('operator.pengantar.proses', $surat), ['nomor_surat' => 'SP/9'])->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('operator.pengantar.selesai', $surat))->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratPengantar::Selesai, $surat->fresh()->status);

        // Pemeriksaan kelengkapan pengajuan kini milik Admin: Operator ditolak, Admin diterima dan dapat melihatnya.
        $pengajuan2 = $this->pengajuanTa($this->buatMahasiswa(), 'diajukan');
        $this->actingAs($operator)->post(route('operator.pengajuan.verifikasi', $pengajuan2))->assertForbidden();
        $this->actingAs($admin)->get(route('pengajuan.show', $pengajuan2))->assertOk();
        $this->actingAs($admin)->post(route('operator.pengajuan.verifikasi', $pengajuan2))->assertSessionHasNoErrors();
        $this->assertSame('terverifikasi', $pengajuan2->fresh()->status_administrasi->value);
    }

    public function test_surat_balasan_hanya_bisa_diunggah_setelah_surat_pengantar_selesai(): void
    {
        $this->dokumenKumpulanA(0);
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs);

        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => $this->berkas('balasan.pdf')])->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_balasans', 0);

        // Berkas wajib PDF/gambar.
        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('berkas');
    }

    public function test_validasi_surat_balasan_dengan_checklist_dan_unggah_ulang(): void
    {
        $this->dokumenKumpulanA(0);
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs);

        // Selesaikan surat pengantar saja.
        $pengantar = app(SuratPengantarService::class);
        $admin = $this->buatAdmin();
        $surat = $pengantar->periksa($this->kirimSuratPengantar($pengajuan, $mhs), $operator);
        $pengantar->selesai($pengantar->proses($surat, $admin), $admin);

        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => $this->berkas('balasan.pdf')])->assertSessionHasNoErrors();
        $sb = $pengajuan->fresh()->suratBalasan;
        $this->assertSame(StatusSuratBalasan::Menunggu, $sb->status);
        $this->assertFalse($pengajuan->fresh()->suratBalasanValid());

        // Mahasiswa dan Admin (bawaan) tidak boleh memvalidasi.
        $this->actingAs($mhs->user)->post(route('operator.balasan.validasi', $sb), ['checklist' => []])->assertForbidden();
        $this->actingAs($admin)->post(route('operator.balasan.validasi', $sb), ['checklist' => []])->assertForbidden();

        // Ada butir yang tidak dicentang tanpa catatan: ditolak, status tidak berubah.
        $kunci = array_keys(SuratBalasan::daftarChecklist());
        $sebagian = array_fill_keys($kunci, true);
        $sebagian[$kunci[0]] = false;
        $this->actingAs($operator)->post(route('operator.balasan.validasi', $sb), ['checklist' => $sebagian])->assertSessionHasErrors('workflow');
        $this->assertSame(StatusSuratBalasan::Menunggu, $sb->fresh()->status);

        // Dengan catatan: Tidak valid, dan pengajuan SK tetap tertahan.
        $this->actingAs($operator)->post(route('operator.balasan.validasi', $sb), ['checklist' => $sebagian, 'catatan' => 'Tanda tangan tidak jelas'])->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratBalasan::TidakValid, $sb->fresh()->status);
        $this->assertFalse($pengajuan->fresh()->suratBalasanValid());

        // Mahasiswa mengunggah ulang: kembali Menunggu, checklist lama dibersihkan.
        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => $this->berkas('balasan-baru.pdf')])->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratBalasan::Menunggu, $sb->fresh()->status);
        $this->assertNull($sb->fresh()->catatan_operator);
        $this->assertSame(1, SuratBalasan::count());

        // Semua dicentang: Valid; sesudahnya tidak bisa diunggah ulang.
        $this->actingAs($operator)->post(route('operator.balasan.validasi', $sb), ['checklist' => array_fill_keys($kunci, true)])->assertSessionHasNoErrors();
        $this->assertSame(StatusSuratBalasan::Valid, $sb->fresh()->status);
        $this->assertTrue($pengajuan->fresh()->suratBalasanValid());
        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => $this->berkas('lagi.pdf')])->assertSessionHasErrors('workflow');

        // Berkas surat balasan hanya dapat diunduh pihak berwenang.
        $this->actingAs($mhs->user)->get(route('balasan.unduh', $pengajuan))->assertOk();
        $this->actingAs($operator)->get(route('balasan.unduh', $pengajuan))->assertOk();
        $this->actingAs($this->buatMahasiswa()->user)->get(route('balasan.unduh', $pengajuan))->assertForbidden();
    }

    public function test_pengajuan_sk_tertahan_sampai_surat_balasan_valid(): void
    {
        $this->dokumenKumpulanA(0);
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs);
        $service = app(PengajuanService::class);
        foreach ($pengajuan->persyaratanPengajuan() as $syarat) {
            $service->unggahDokumen($pengajuan, $syarat, $this->berkas(), $mhs->user);
        }

        // Belum ada surat pengantar maupun surat balasan.
        try {
            $service->kirim($pengajuan, $mhs->user);
            $this->fail('Pengajuan SK seharusnya tertahan gerbang surat balasan.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('surat balasan', mb_strtolower($e->getMessage()));
        }
        $this->assertSame(StatusPengajuan::Draft, $pengajuan->fresh()->status);

        // Surat balasan hanya diunggah, belum valid: masih tertahan.
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengantar = app(SuratPengantarService::class);
        $surat = $pengantar->periksa($this->kirimSuratPengantar($pengajuan, $mhs), $operator);
        $pengantar->selesai($pengantar->proses($surat, $admin), $admin);
        app(SuratBalasanService::class)->unggah($pengajuan, $this->berkas('balasan.pdf'), $mhs->user);
        $this->expectException(WorkflowException::class);
        $service->kirim($pengajuan->fresh(), $mhs->user);
    }

    public function test_pengajuan_lama_sebelum_revisi_tidak_terkena_gerbang_surat_balasan(): void
    {
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs);
        Pengajuan::whereKey($pengajuan->id)->update(['wajib_surat_balasan' => false]);
        $pengajuan = $pengajuan->fresh();

        $service = app(PengajuanService::class);
        foreach ($pengajuan->persyaratanPengajuan() as $syarat) {
            $service->unggahDokumen($pengajuan, $syarat, $this->berkas(), $mhs->user);
        }

        $this->assertFalse($pengajuan->gerbangSuratBalasan());
        $this->assertSame(StatusPengajuan::Diajukan, $service->kirim($pengajuan, $mhs->user)->status);
    }

    public function test_surat_pengantar_tidak_tersedia_untuk_kp(): void
    {
        $mhs = $this->buatMahasiswa();
        $kp = app(PengajuanService::class)->buat($mhs, JenisPengajuan::Kp, ['judul' => 'KP uji'], $mhs->user);

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.simpan', $kp), $this->isianSuratPengantar())->assertNotFound();
        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $kp), ['berkas' => $this->berkas()])->assertNotFound();
    }
}
