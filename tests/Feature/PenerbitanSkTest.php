<?php

namespace Tests\Feature;

use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Pengaturan;
use App\Models\SuratKeputusan;
use App\Models\TemplateSk;
use App\Services\SkPdfRenderer;
use App\Services\SkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class PenerbitanSkTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
    }

    public function test_sk_tidak_terbit_selama_konfigurasi_kampus_belum_lengkap(): void
    {
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);

        $kurang = app(SkService::class)->kekuranganKonfigurasi();
        $this->assertContains('Template SK aktif', $kurang);
        $this->assertContains('Nama penandatangan SK', $kurang);

        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_keputusans', 0);
        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->fresh()->status);

        // Halaman detail menjelaskan bagian yang harus diisi, tanpa mengarang isinya.
        $this->actingAs($operator)->get(route('pengajuan.show', $pengajuan))->assertOk()->assertSee('Nama penandatangan SK');
    }

    public function test_pdf_gagal_tidak_meninggalkan_sk_palsu_dan_nomor_tidak_terpakai(): void
    {
        $this->konfigurasiSk();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);

        $this->app->bind(SkPdfRenderer::class, fn () => new class extends SkPdfRenderer
        {
            public function render(string $html): string
            {
                throw new WorkflowException('Pembuatan PDF SK gagal (simulasi).');
            }
        });

        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasErrors('workflow');

        $this->assertDatabaseCount('surat_keputusans', 0);
        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('sk'));
        $this->assertDatabaseMissing('log_aktivitas', ['aksi' => 'sk_diterbitkan']);

        // Nomor 001 tidak terbuang: setelah mesin PDF pulih, SK pertama tetap bernomor 001.
        // (Langsung lewat service: Laravel menyimpan instance controller per route selama satu proses uji.)
        $this->app->bind(SkPdfRenderer::class, SkPdfRenderer::class);
        app(SkService::class)->terbitkan($pengajuan, $operator);
        $this->assertStringStartsWith('001/', SuratKeputusan::firstOrFail()->nomor);
    }

    public function test_penomoran_berurutan_dan_unik_per_tahun(): void
    {
        $this->konfigurasiSk();
        Pengaturan::simpan('sk.format_nomor', '{urut}/SK/{bulan_romawi}/{kode_prodi}/{tahun}');
        $operator = $this->buatOperator();

        $nomor = [];
        foreach (['TI', 'SI', 'TI'] as $kode) {
            $pengajuan = $this->pengajuanTa($this->buatMahasiswa($kode), 'disetujui', null, $operator);
            $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasNoErrors();
            $nomor[] = $pengajuan->fresh()->suratKeputusan->nomor;
        }

        $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month];
        $this->assertSame(["001/SK/{$romawi}/TI/".now()->year, "002/SK/{$romawi}/SI/".now()->year, "003/SK/{$romawi}/TI/".now()->year], $nomor);
        $this->assertCount(3, array_unique($nomor));
    }

    public function test_sk_tidak_bisa_diterbitkan_untuk_pengajuan_yang_belum_memenuhi_persetujuan(): void
    {
        $this->konfigurasiSk();
        $operator = $this->buatOperator();
        $service = app(SkService::class);

        foreach (['diajukan', 'terverifikasi'] as $tahap) {
            $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), $tahap, null, $operator);
            try {
                $service->terbitkan($pengajuan, $operator);
                $this->fail("SK terbit padahal tahap {$tahap}");
            } catch (WorkflowException $e) {
                $this->assertStringContainsString('hanya dapat diterbitkan', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('surat_keputusans', 0);
    }

    public function test_template_menahan_html_berbahaya_pada_data_mahasiswa(): void
    {
        $this->konfigurasiSk();
        $operator = $this->buatOperator();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui', null, $operator);
        $pengajuan->update(['judul' => '<script>alert(1)</script> Judul']);

        $html = app(SkService::class)->renderHtml($pengajuan->fresh());

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_hanya_satu_template_yang_aktif(): void
    {
        $pertama = $this->konfigurasiSk();
        $kedua = TemplateSk::create(['nama' => 'Template baru', 'isi' => '<p>baru</p>', 'aktif' => true]);

        $this->assertFalse($pertama->fresh()->aktif);
        $this->assertSame($kedua->id, TemplateSk::aktif()->id);
    }
}
