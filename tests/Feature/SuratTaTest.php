<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\DokumenPengajuan;
use App\Models\DokumenResmi;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\SuratBalasan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

/**
 * Alur surat Tugas Akhir: surat pengantar (operator) -> surat balasan (mahasiswa, diperiksa operator)
 * -> SK pembimbing (operator, hanya setelah balasan diterima dan dosen menyetujui). Hanya untuk TA.
 */
class SuratTaTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private User $mahasiswa;

    private User $mahasiswaLain;

    private User $dosen;

    private User $dosenLain;

    private User $operator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->siapkanMaster();

        $prodi = ProgramStudi::first()->id;
        $this->mahasiswa = User::factory()->mahasiswa()->create(['program_studi_id' => $prodi]);
        $this->mahasiswaLain = User::factory()->mahasiswa()->create(['program_studi_id' => $prodi]);
        $this->dosen = User::factory()->dosen()->create();
        $this->dosenLain = User::factory()->dosen()->create();
        $this->operator = User::factory()->operator()->create();
        $this->admin = User::factory()->admin()->create();
    }

    // ---------------------------------------------------------------- pembantu

    private function ta(StatusPengajuan $status, ?User $dosen = null, ?User $mahasiswa = null): Pengajuan
    {
        return $this->buatPengajuan($mahasiswa ?? $this->mahasiswa, $dosen ?? $this->dosen, $status, JenisPengajuan::Ta);
    }

    /** TA yang sudah dikirim, dengan semua dokumen persyaratan versi pertama (menunggu diperiksa). */
    private function taDiajukan(): Pengajuan
    {
        $p = $this->ta(StatusPengajuan::Diajukan);

        foreach (Persyaratan::aktif()->untuk(JenisPengajuan::Ta)->get() as $syarat) {
            DokumenPengajuan::create([
                'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $this->mahasiswa->id,
                'versi' => 1, 'terbaru' => true, 'nama_asli' => "syarat-{$syarat->id}.pdf",
                'path' => "pengajuan/{$p->id}/syarat-{$syarat->id}.pdf", 'mime' => 'application/pdf', 'ukuran' => 1000,
                'status' => StatusDokumen::Menunggu,
            ]);
        }

        return $p;
    }

    /** Memeriksa semua dokumen milik $peran (operator|dosen) dengan keputusan diterima lewat HTTP. */
    private function terimaSemua(User $pemeriksa, string $rute, Pengajuan $p, string $peran): void
    {
        $keputusan = $p->dokumenTerbaru()
            ->whereHas('persyaratan', fn ($q) => $q->where('pemeriksa', $peran))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => 'diterima'])
            ->all();

        $this->actingAs($pemeriksa)->post(route($rute, $p), ['keputusan' => $keputusan])->assertSessionHasNoErrors();
    }

    private function pengantar(Pengajuan $p): DokumenResmi
    {
        return DokumenResmi::create([
            'pengajuan_id' => $p->id, 'diunggah_oleh' => $this->operator->id, 'jenis' => JenisPengajuan::SURAT_PENGANTAR_TA,
            'nomor' => '010/UN9/2026', 'tanggal' => now()->toDateString(),
            'nama_asli' => 'pengantar.pdf', 'path' => "dokumen-resmi/{$p->id}/pengantar.pdf", 'mime' => 'application/pdf', 'ukuran' => 2000,
        ]);
    }

    private function balasan(Pengajuan $p, StatusDokumen $status, int $versi = 1, bool $terbaru = true, ?string $catatan = null): SuratBalasan
    {
        return SuratBalasan::create([
            'pengajuan_id' => $p->id, 'diunggah_oleh' => $p->mahasiswa_id, 'versi' => $versi, 'terbaru' => $terbaru,
            'nama_asli' => "balasan-v{$versi}.pdf", 'path' => "surat-balasan/{$p->id}/balasan-v{$versi}.pdf",
            'mime' => 'application/pdf', 'ukuran' => 3000, 'status' => $status, 'catatan' => $catatan,
        ]);
    }

    /** Menulis berkas contoh ke disk palsu agar bisa diunduh. */
    private function isiBerkas(string $path): void
    {
        Storage::disk('local')->put($path, '%PDF-1.4 contoh');
    }

    private function terbitkanPengantar(Pengajuan $p, ?UploadedFile $berkas = null)
    {
        return $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.surat-pengantar', $p), [
            'nomor_pengantar' => '010/UN9.FASILKOM/2026',
            'tanggal_pengantar' => '2026-10-20',
            'berkas_pengantar' => $berkas ?? $this->pdf('pengantar.pdf'),
        ]);
    }

    private function unggahBalasan(Pengajuan $p, ?UploadedFile $berkas = null, ?User $oleh = null)
    {
        return $this->actingAs($oleh ?? $this->mahasiswa)->post(route('mahasiswa.surat-balasan.store', $p), [
            'berkas_balasan' => $berkas ?? $this->pdf('balasan.pdf'),
        ]);
    }

    private function periksaBalasan(Pengajuan $p, SuratBalasan $b, string $keputusan, ?string $catatan = null)
    {
        return $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.surat-balasan', $p), [
            'balasan_id' => $b->id, 'keputusan' => $keputusan, 'catatan_balasan' => $catatan,
        ]);
    }

    private function terbitkanSk(Pengajuan $p)
    {
        return $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.store', $p), [
            'jenis' => JenisPengajuan::SK_TA, 'nomor' => '055/UN9/SK/2026', 'tanggal' => '2026-10-25', 'berkas' => $this->pdf('sk.pdf'),
        ]);
    }

    private function adaSk(Pengajuan $p): bool
    {
        return $p->dokumenResmi()->where('jenis', JenisPengajuan::SK_TA)->exists();
    }

    // ---------------------------------------------------------------- alur lengkap

    public function test_alur_lengkap_ta_dari_verifikasi_sampai_sk_dan_arsip(): void
    {
        $p = $this->taDiajukan();

        // Sebelum diverifikasi: tidak ada surat yang bisa diterbitkan.
        $this->terbitkanPengantar($p)->assertForbidden();

        // Operator memverifikasi, pengajuan lanjut ke dosen.
        $this->terimaSemua($this->operator, 'operator.verifikasi.store', $p, 'operator');
        $this->assertSame(StatusPengajuan::MenungguDosen, $p->fresh()->status);

        // Surat balasan belum bisa diunggah sebelum surat pengantar terbit.
        $this->unggahBalasan($p)->assertForbidden();
        $this->assertSame(0, SuratBalasan::count());

        // Operator menerbitkan surat pengantar: berkas hanya PDF, lalu tersimpan sebagai dokumen resmi.
        $this->terbitkanPengantar($p, UploadedFile::fake()->create('pengantar.jpg', 50, 'image/jpeg'))
            ->assertSessionHasErrors('berkas_pengantar');
        $this->assertSame(0, DokumenResmi::count());

        $this->terbitkanPengantar($p)->assertSessionHasNoErrors()->assertRedirect(route('operator.dokumen-resmi.show', $p));
        $pengantar = $p->fresh()->dokumenResmi()->where('jenis', JenisPengajuan::SURAT_PENGANTAR_TA)->firstOrFail();
        $this->assertSame('010/UN9.FASILKOM/2026', $pengantar->nomor);
        Storage::disk('local')->assertExists($pengantar->path);
        $this->assertStringStartsWith("dokumen-resmi/{$p->id}/", $pengantar->path);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'surat-pengantar.terbit']);

        // Surat pengantar hanya satu.
        $this->terbitkanPengantar($p)->assertForbidden();

        // Mahasiswa melihat tahapnya dan mengunduh surat pengantar.
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertOk()
            ->assertSee('Surat Tugas Akhir')
            ->assertSee('Menunggu unggahan Anda')
            ->assertSee('name="berkas_balasan"', false);
        $this->actingAs($this->mahasiswa)->get(route('berkas.resmi', ['dokumenResmi' => $pengantar, 'unduh' => 1]))->assertOk();

        // Surat balasan: format salah ditolak; PDF diterima sebagai versi 1 (menunggu).
        $this->unggahBalasan($p, UploadedFile::fake()->create('balasan.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->assertSessionHasErrors('berkas_balasan');
        $this->unggahBalasan($p, $this->pdf('balasan.pdf', 6000))->assertSessionHasErrors('berkas_balasan');
        $this->assertSame(0, SuratBalasan::count());

        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $b1 = $p->fresh()->balasanTerbaru();
        $this->assertSame(1, $b1->versi);
        $this->assertSame(StatusDokumen::Menunggu, $b1->status);
        $this->assertStringStartsWith("surat-balasan/{$p->id}/", $b1->path);
        Storage::disk('local')->assertExists($b1->path);

        // Dosen menyetujui pengajuan (paralel dengan pemeriksaan surat). SK tetap tertahan karena balasan belum diterima.
        $this->terimaSemua($this->dosen, 'dosen.pemeriksaan.store', $p, 'dosen');
        $this->assertSame(StatusPengajuan::Disetujui, $p->fresh()->status);

        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');
        $this->assertFalse($this->adaSk($p));
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.arsipkan', $p))->assertSessionHasErrors('dokumen_resmi');
        $this->assertSame(StatusPengajuan::Disetujui, $p->fresh()->status);

        // Operator meminta revisi (catatan wajib).
        $this->periksaBalasan($p, $b1, 'revisi')->assertSessionHasErrors('catatan_balasan');
        $this->assertSame(StatusDokumen::Menunggu, $b1->fresh()->status);
        $this->periksaBalasan($p, $b1, 'revisi', 'Cap instansi belum terlihat.')->assertSessionHasNoErrors();
        $this->assertSame(StatusDokumen::Revisi, $b1->fresh()->status);
        $this->assertSame($this->operator->id, (int) $b1->fresh()->diperiksa_oleh);
        $this->assertNotNull($b1->fresh()->diperiksa_at);

        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');

        // Mahasiswa melihat catatan revisi dan mengunggah ulang; versi lama menjadi jejak.
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertOk()->assertSee('Perlu revisi')->assertSee('Cap instansi belum terlihat.');
        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $b2 = $p->fresh()->balasanTerbaru();
        $this->assertSame(2, $b2->versi);
        $this->assertFalse($b1->fresh()->terbaru);
        $this->assertSame(StatusDokumen::Menunggu, $b2->status);
        $this->assertSame(2, $p->suratBalasan()->count());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');

        // Operator memeriksa versi yang sudah usang: ditolak, tidak ada yang berubah.
        $this->periksaBalasan($p, $b1, 'diterima')->assertSessionHasErrors('keputusan');
        $this->assertSame(StatusDokumen::Menunggu, $b2->fresh()->status);

        // Versi terbaru diterima: sekarang SK boleh terbit.
        $this->periksaBalasan($p, $b2, 'diterima')->assertSessionHasNoErrors();
        $this->assertSame(StatusDokumen::Diterima, $b2->fresh()->status);
        $this->assertNull($b2->fresh()->catatan);

        $this->actingAs($this->operator)->get(route('operator.dokumen-resmi.show', $p))
            ->assertOk()->assertSee('Terbitkan SK Pembimbing');

        $this->terbitkanSk($p)->assertSessionHasNoErrors();
        $sk = $p->fresh()->dokumenResmi()->where('jenis', JenisPengajuan::SK_TA)->firstOrFail();
        Storage::disk('local')->assertExists($sk->path);

        // SK hanya satu; setelah SK terbit keputusan balasan tidak bisa diubah lagi.
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');
        $this->assertSame(1, $p->dokumenResmi()->where('jenis', JenisPengajuan::SK_TA)->count());
        $this->periksaBalasan($p, $b2, 'revisi', 'Eh.')->assertForbidden();
        $this->unggahBalasan($p)->assertForbidden();

        // Mahasiswa mengunduh SK, lalu operator mengarsipkan.
        $this->actingAs($this->mahasiswa)->get(route('berkas.resmi', ['dokumenResmi' => $sk, 'unduh' => 1]))->assertOk();
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.arsipkan', $p), ['catatan' => 'Lengkap.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('operator.arsip.show', $p));
        $this->assertSame(StatusPengajuan::Diarsipkan, $p->fresh()->status);

        // Setelah diarsipkan semua langkah surat tertutup, tetapi riwayatnya tetap terlihat.
        $this->unggahBalasan($p)->assertForbidden();
        $this->periksaBalasan($p, $b2, 'revisi', 'Eh.')->assertForbidden();
        $this->actingAs($this->operator)->get(route('operator.arsip.show', $p))
            ->assertOk()->assertSee('Surat Pengantar')->assertSee('SK Pembimbing')->assertSee('Versi surat balasan sebelumnya');
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.berkas.index'))
            ->assertOk()->assertSee('Surat Balasan TA yang Diunggah')->assertSee('010/UN9.FASILKOM/2026')->assertSee('055/UN9/SK/2026');

        foreach (['surat-pengantar.terbit', 'surat-balasan.unggah', 'surat-balasan.periksa'] as $aksi) {
            $this->assertTrue(LogAktivitas::where('aksi', $aksi)->exists(), "log {$aksi}");
        }
        $this->assertSame(2, LogAktivitas::where('aksi', 'surat-balasan.unggah')->count());
    }

    // ---------------------------------------------------------------- pintu SK

    public function test_sk_hanya_terbit_bila_pengantar_balasan_diterima_pembimbing_dan_disetujui(): void
    {
        $p = $this->ta(StatusPengajuan::Disetujui);

        // 1. Belum ada surat pengantar.
        $this->assertSame('Surat pengantar belum diterbitkan.', $p->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');
        $this->assertFalse($this->adaSk($p));

        // 2. Ada pengantar, belum ada balasan.
        $this->pengantar($p);
        $p = $p->fresh();
        $this->assertSame('Surat balasan belum diunggah mahasiswa.', $p->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');

        // 3. Balasan menunggu pemeriksaan.
        $b = $this->balasan($p, StatusDokumen::Menunggu);
        $this->assertSame('Surat balasan belum diperiksa operator.', $p->fresh()->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');

        // 4. Balasan perlu revisi.
        $b->update(['status' => StatusDokumen::Revisi]);
        $this->assertStringContainsString('perlu direvisi', $p->fresh()->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');

        // Balasan diterima tetapi pembimbing hilang: tetap tertahan.
        $b->update(['status' => StatusDokumen::Diterima]);
        $p->update(['pembimbing_id' => null, 'status_pembimbing' => StatusPembimbing::Belum]);
        $this->assertSame('Dosen pembimbing belum ditetapkan.', $p->fresh()->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasErrors('jenis');
        $this->assertFalse($this->adaSk($p));

        // Semua syarat terpenuhi: SK terbit.
        $p->update(['pembimbing_id' => $this->dosen->id, 'status_pembimbing' => StatusPembimbing::Diterima]);
        $this->assertNull($p->fresh()->hambatanSk());
        $this->terbitkanSk($p)->assertSessionHasNoErrors();
        $this->assertTrue($this->adaSk($p));
    }

    public function test_sk_tidak_bisa_diterbitkan_sebelum_dosen_menyetujui_walau_balasan_diterima(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);
        $this->pengantar($p);
        $this->balasan($p, StatusDokumen::Diterima);

        $this->assertSame('Pengajuan belum disetujui dosen pembimbing.', $p->fresh()->hambatanSk());
        // Dokumen resmi baru bisa diproses setelah disetujui dosen.
        $this->terbitkanSk($p)->assertForbidden();
        $this->assertFalse($this->adaSk($p));

        $p->update(['status' => StatusPengajuan::Disetujui]);
        $this->terbitkanSk($p)->assertSessionHasNoErrors();
        $this->assertTrue($this->adaSk($p));
    }

    public function test_surat_pengantar_tidak_bisa_diterbitkan_lewat_formulir_dokumen_resmi_umum(): void
    {
        $p = $this->ta(StatusPengajuan::Disetujui);

        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.store', $p), [
            'jenis' => JenisPengajuan::SURAT_PENGANTAR_TA, 'nomor' => '1/X', 'tanggal' => '2026-10-25', 'berkas' => $this->pdf(),
        ])->assertSessionHasErrors('jenis');
        $this->assertSame(0, DokumenResmi::count());

        // Dokumen lain (lainnya) tetap bisa diunggah tanpa syarat surat balasan.
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.store', $p), [
            'jenis' => 'lainnya', 'nomor' => '2/X', 'tanggal' => '2026-10-25', 'berkas' => $this->pdf(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DokumenResmi::count());
    }

    public function test_arsip_ta_mewajibkan_sk_pembimbing(): void
    {
        $p = $this->ta(StatusPengajuan::Disetujui);
        $this->pengantar($p);

        // Sudah ada dokumen resmi (surat pengantar), tetapi SK belum terbit.
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.arsipkan', $p))->assertSessionHasErrors('dokumen_resmi');
        $this->assertSame(StatusPengajuan::Disetujui, $p->fresh()->status);

        $this->actingAs($this->operator)->get(route('operator.dokumen-resmi.show', $p))
            ->assertOk()->assertSee('Terbitkan SK Pembimbing Tugas Akhir terlebih dahulu.');

        $this->balasan($p, StatusDokumen::Diterima);
        $this->terbitkanSk($p)->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.arsipkan', $p))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Diarsipkan, $p->fresh()->status);
    }

    // ---------------------------------------------------------------- keputusan dan penarikan

    public function test_keputusan_balasan_dapat_diubah_selama_sk_belum_terbit(): void
    {
        $p = $this->ta(StatusPengajuan::Disetujui);
        $this->pengantar($p);
        $b = $this->balasan($p, StatusDokumen::Menunggu);

        $this->periksaBalasan($p, $b, 'diterima')->assertSessionHasNoErrors();
        $this->assertNull($p->fresh()->hambatanSk());

        // Salah menerima: operator membatalkan dengan meminta revisi, SK kembali tertahan.
        $this->periksaBalasan($p, $b, 'revisi', 'Salah berkas.')->assertSessionHasNoErrors();
        $this->assertSame(StatusDokumen::Revisi, $b->fresh()->status);
        $this->assertNotNull($p->fresh()->hambatanSk());

        // Setelah status revisi, tidak ada lagi yang diperiksa sampai mahasiswa mengunggah ulang.
        $this->periksaBalasan($p, $b, 'diterima')->assertForbidden();

        // Keputusan selain diterima/revisi ditolak.
        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $baru = $p->fresh()->balasanTerbaru();
        $this->periksaBalasan($p, $baru, 'menunggu')->assertSessionHasErrors('keputusan');
        $this->assertSame(StatusDokumen::Menunggu, $baru->fresh()->status);
    }

    public function test_mahasiswa_dapat_mengganti_balasan_yang_masih_menunggu_tetapi_tidak_yang_sudah_diterima(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);
        $this->pengantar($p);

        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $this->assertSame(2, $p->suratBalasan()->count());
        $this->assertSame(1, $p->suratBalasan()->where('terbaru', true)->count());
        $this->assertSame(2, $p->fresh()->balasanTerbaru()->versi);

        $p->fresh()->balasanTerbaru()->update(['status' => StatusDokumen::Diterima]);
        $this->unggahBalasan($p)->assertForbidden();
        $this->assertSame(2, $p->suratBalasan()->count());
    }

    public function test_surat_pengantar_dapat_ditarik_hanya_selama_belum_ada_balasan(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);
        $this->terbitkanPengantar($p)->assertSessionHasNoErrors();
        $pengantar = $p->fresh()->suratPengantarTa();
        Storage::disk('local')->assertExists($pengantar->path);

        // Mahasiswa dan dosen tidak berwenang menarik.
        $this->actingAs($this->mahasiswa)->delete(route('operator.dokumen-resmi.destroy', [$p, $pengantar]))->assertForbidden();
        $this->actingAs($this->dosen)->delete(route('operator.dokumen-resmi.destroy', [$p, $pengantar]))->assertForbidden();

        $this->actingAs($this->operator)->delete(route('operator.dokumen-resmi.destroy', [$p, $pengantar]))->assertSessionHasNoErrors();
        $this->assertNull($p->fresh()->suratPengantarTa());
        Storage::disk('local')->assertMissing($pengantar->path);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'surat-pengantar.hapus']);

        // Boleh menerbitkan yang baru; setelah mahasiswa mengunggah balasan, tidak bisa ditarik lagi.
        $this->terbitkanPengantar($p)->assertSessionHasNoErrors();
        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $baru = $p->fresh()->suratPengantarTa();
        $this->actingAs($this->operator)->delete(route('operator.dokumen-resmi.destroy', [$p, $baru]))->assertForbidden();
        $this->assertNotNull($p->fresh()->suratPengantarTa());
    }

    // ---------------------------------------------------------------- hak akses

    public function test_hak_akses_langkah_surat_per_role(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);

        // Hanya operator yang menerbitkan surat pengantar.
        foreach ([$this->mahasiswa, $this->dosen, $this->admin] as $bukanOperator) {
            $this->actingAs($bukanOperator)->post(route('operator.dokumen-resmi.surat-pengantar', $p), [
                'nomor_pengantar' => '1', 'tanggal_pengantar' => '2026-10-20', 'berkas_pengantar' => $this->pdf(),
            ])->assertForbidden();
        }
        $this->assertSame(0, DokumenResmi::count());

        $this->pengantar($p);

        // Hanya mahasiswa pemilik yang mengunggah surat balasan.
        $this->unggahBalasan($p, null, $this->mahasiswaLain)->assertForbidden();
        $this->unggahBalasan($p, null, $this->dosen)->assertForbidden();
        $this->unggahBalasan($p, null, $this->operator)->assertForbidden();
        $this->unggahBalasan($p, null, $this->admin)->assertForbidden();
        $this->assertSame(0, SuratBalasan::count());
        $this->unggahBalasan($p)->assertSessionHasNoErrors();
        $b = $p->fresh()->balasanTerbaru();

        // Hanya operator yang memeriksa.
        foreach ([$this->mahasiswa, $this->dosen, $this->admin] as $bukanOperator) {
            $this->actingAs($bukanOperator)->post(route('operator.dokumen-resmi.surat-balasan', $p), [
                'balasan_id' => $b->id, 'keputusan' => 'diterima',
            ])->assertForbidden();
        }
        $this->assertSame(StatusDokumen::Menunggu, $b->fresh()->status);

        // Halaman operator tertutup untuk role lain.
        $this->actingAs($this->mahasiswa)->get(route('operator.dokumen-resmi.show', $p))->assertForbidden();
        $this->actingAs($this->dosen)->get(route('operator.dokumen-resmi.show', $p))->assertForbidden();
    }

    public function test_unggah_balasan_butuh_login_dan_tidak_berlaku_untuk_kp(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);
        $this->pengantar($p);

        auth()->logout();
        $this->post(route('mahasiswa.surat-balasan.store', $p), ['berkas_balasan' => $this->pdf()])->assertRedirect(route('login'));

        $kp = $this->buatPengajuan($this->mahasiswaLain, $this->dosen, StatusPengajuan::MenungguDosen, JenisPengajuan::Kp);
        DokumenResmi::create([
            'pengajuan_id' => $kp->id, 'diunggah_oleh' => $this->operator->id, 'jenis' => JenisPengajuan::SURAT_PENGANTAR_TA,
            'nomor' => '1', 'tanggal' => now()->toDateString(), 'nama_asli' => 'x.pdf', 'path' => 'x.pdf', 'ukuran' => 1,
        ]);
        $this->unggahBalasan($kp, null, $this->mahasiswaLain)->assertForbidden();
        $this->terbitkanPengantar($kp)->assertForbidden();
    }

    // ---------------------------------------------------------------- berkas

    public function test_berkas_balasan_hanya_dapat_dibuka_pihak_yang_berwenang(): void
    {
        $p = $this->ta(StatusPengajuan::MenungguDosen);
        $this->pengantar($p);
        $b = $this->balasan($p, StatusDokumen::Menunggu);
        $this->isiBerkas($b->path);

        $url = route('berkas.balasan', ['suratBalasan' => $b, 'unduh' => 1]);

        $this->actingAs($this->mahasiswa)->get($url)->assertOk();
        $this->actingAs($this->operator)->get($url)->assertOk();
        $this->actingAs($this->dosen)->get($url)->assertOk();

        $this->actingAs($this->mahasiswaLain)->get($url)->assertForbidden();
        $this->actingAs($this->dosenLain)->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        // Berkas tidak punya URL publik.
        $this->assertStringNotContainsString('storage/surat-balasan', route('berkas.balasan', $b));
    }

    // ---------------------------------------------------------------- tampilan

    public function test_tahap_surat_tampil_sesuai_keadaan_untuk_mahasiswa(): void
    {
        $p = $this->ta(StatusPengajuan::Diajukan);

        // Belum diverifikasi: kartu surat belum tampil.
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertOk()->assertDontSee('id="surat-ta"', false);

        $p->update(['status' => StatusPengajuan::MenungguDosen]);
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertOk()->assertSee('id="surat-ta"', false)->assertSee('Menunggu operator')
            ->assertSee('Dapat diunggah setelah surat pengantar terbit.')
            ->assertDontSee('name="berkas_balasan"', false);

        $this->pengantar($p);
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Ada surat Tugas Akhir yang menunggu Anda')->assertSee('Menunggu unggahan Anda');

        $b = $this->balasan($p, StatusDokumen::Menunggu);
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertDontSee('Ada surat Tugas Akhir yang menunggu Anda');
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertOk()->assertSee('Menunggu pemeriksaan')->assertSee('Mengunggah lagi akan menggantikannya.');

        // Pengantar dan SK tidak ditampilkan dua kali di kartu "Dokumen Resmi".
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $p))
            ->assertDontSee('Belum ada dokumen resmi yang diterbitkan.');
    }

    public function test_dashboard_dan_daftar_operator_menampilkan_surat_ta_yang_perlu_diproses(): void
    {
        $perluPengantar = $this->ta(StatusPengajuan::MenungguDosen);
        $perluPeriksa = $this->ta(StatusPengajuan::MenungguDosen, null, $this->mahasiswaLain);
        $this->pengantar($perluPeriksa);
        $this->balasan($perluPeriksa, StatusDokumen::Menunggu);

        $this->assertTrue(Pengajuan::perluSuratPengantar()->whereKey($perluPengantar->id)->exists());
        $this->assertFalse(Pengajuan::perluSuratPengantar()->whereKey($perluPeriksa->id)->exists());
        $this->assertTrue(Pengajuan::balasanMenunggu()->whereKey($perluPeriksa->id)->exists());
        $this->assertFalse(Pengajuan::balasanMenunggu()->whereKey($perluPengantar->id)->exists());

        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertSee('Surat Pengantar TA')->assertSee('Surat Balasan TA')
            ->assertSee('Terbitkan surat pengantar')->assertSee('Periksa surat balasan');

        $this->actingAs($this->operator)->get(route('operator.dokumen-resmi.index'))
            ->assertOk()->assertSee('Surat TA Perlu Diproses')->assertSee($this->mahasiswa->name)->assertSee($this->mahasiswaLain->name);

        // Operator melihat formulir terbitkan untuk yang pertama dan formulir periksa untuk yang kedua.
        $this->actingAs($this->operator)->get(route('operator.dokumen-resmi.show', $perluPengantar))
            ->assertOk()->assertSee('name="berkas_pengantar"', false)->assertDontSee('id="periksa-balasan"', false);
        $this->actingAs($this->operator)->get(route('operator.dokumen-resmi.show', $perluPeriksa))
            ->assertOk()->assertSee('id="periksa-balasan"', false)->assertDontSee('name="berkas_pengantar"', false);
    }

    // ---------------------------------------------------------------- KP tidak berubah

    public function test_alur_kp_tidak_terpengaruh_surat_ta(): void
    {
        $kp = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Disetujui, JenisPengajuan::Kp);

        $this->assertSame([], $kp->tahapSurat());
        $this->assertFalse($kp->tampilkanSuratTa());

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $kp))
            ->assertOk()->assertDontSee('id="surat-ta"', false);

        // KP tetap memakai surat pengantar KP dan SK KP tanpa syarat surat balasan, dan tidak menerima kode jenis TA.
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.store', $kp), [
            'jenis' => JenisPengajuan::SK_TA, 'nomor' => '1', 'tanggal' => '2026-10-25', 'berkas' => $this->pdf(),
        ])->assertSessionHasErrors('jenis');
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.store', $kp), [
            'jenis' => 'sk_kp', 'nomor' => '2', 'tanggal' => '2026-10-25', 'berkas' => $this->pdf(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->post(route('operator.dokumen-resmi.arsipkan', $kp))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Diarsipkan, $kp->fresh()->status);
    }
}
