<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\DokumenPengajuan;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AlurPengajuanTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private User $mahasiswa;

    private User $dosen;

    private User $dosenLain;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->siapkanMaster();

        $this->mahasiswa = User::factory()->mahasiswa()->create(['program_studi_id' => ProgramStudi::first()->id]);
        $this->dosen = User::factory()->dosen()->create();
        $this->dosenLain = User::factory()->dosen()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function unggah(Pengajuan $p, Persyaratan $syarat, ?UploadedFile $file = null)
    {
        return $this->actingAs($this->mahasiswa->fresh())->post(route('mahasiswa.dokumen.store', $p), [
            'persyaratan_id' => $syarat->id,
            'berkas' => $file ?? $this->pdf(),
        ]);
    }

    /** @param array<int|string, string> $keputusan */
    private function periksa(User $user, string $rute, Pengajuan $p, array $keputusan, array $catatan = [], string $umum = '')
    {
        return $this->actingAs($user->fresh())->post(route($rute, $p), [
            'keputusan' => $keputusan,
            'catatan' => $catatan,
            'catatan_umum' => $umum,
        ]);
    }

    /** Dokumen versi terbaru milik pemeriksa tertentu. */
    private function dokumenPemeriksa(Pengajuan $p, string $pemeriksa)
    {
        return $p->dokumenTerbaru()
            ->whereHas('persyaratan', fn ($q) => $q->where('pemeriksa', $pemeriksa))
            ->with('persyaratan')
            ->orderBy('id')
            ->get();
    }

    /** @return list<string> */
    private function urutanStatus(Pengajuan $p): array
    {
        return $p->fresh()->riwayat->map(fn ($r) => $r->status_baru->value)->all();
    }

    public function test_alur_lengkap_kp_dari_pengajuan_sampai_arsip(): void
    {
        $wajib = Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->where('wajib', true)->orderBy('urutan')->get();

        // 1. Mahasiswa membuat pengajuan KP (draft). Pembimbing KP ditetapkan admin, bukan dipilih mahasiswa,
        //    sehingga pembimbing_id yang ikut terkirim diabaikan.
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'kp'), [
            'judul' => 'Rancang Bangun Sistem Arsip Digital',
            'instansi' => 'Dinas Komunikasi dan Informatika',
            'deskripsi' => 'Kerja praktik pengembangan aplikasi web.',
            'pembimbing_id' => $this->dosenLain->id,
        ])->assertSessionHasNoErrors();

        $p = Pengajuan::firstOrFail();
        $this->assertSame(StatusPengajuan::Draft, $p->status);
        $this->assertSame($this->mahasiswa->id, (int) $p->mahasiswa_id);
        $this->assertNull($p->pembimbing_id);
        $this->assertNull($p->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Belum, $p->status_pembimbing);

        // Pengajuan KP kedua selama yang pertama berjalan ditolak.
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'kp'), [
            'judul' => 'Duplikat', 'instansi' => 'X',
        ])->assertSessionHas('galat');
        $this->assertSame(1, Pengajuan::count());

        // 2. Belum bisa dikirim karena dokumen wajib belum lengkap.
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $p))
            ->assertSessionHasErrors('dokumen');
        $this->assertSame(StatusPengajuan::Draft, $p->fresh()->status);

        // 3. Validasi unggahan: format salah dan ukuran terlalu besar ditolak.
        $this->unggah($p, $wajib->first(), UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
            ->assertSessionHasErrors('berkas');
        $this->unggah($p, $wajib->first(), $this->pdf('besar.pdf', 6000))
            ->assertSessionHasErrors('berkas');
        $this->assertSame(0, $p->dokumen()->count());

        foreach ($wajib as $syarat) {
            $this->unggah($p, $syarat)->assertSessionHasNoErrors();
        }
        $this->assertSame($wajib->count(), $p->dokumenTerbaru()->count());

        $contoh = $p->dokumenTerbaru()->first();
        Storage::disk('local')->assertExists($contoh->path);
        $this->assertStringStartsWith("pengajuan/{$p->id}/", $contoh->path);

        // 4. Mahasiswa mengirim pengajuan.
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $p))->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(StatusPengajuan::Diajukan, $p->status);
        $this->assertNotNull($p->diajukan_at);

        // Setelah dikirim, mahasiswa tidak dapat lagi mengganti dokumen.
        $this->unggah($p, $wajib->first())->assertForbidden();

        // Pembimbing KP ditetapkan admin dari daftar dosen. Sebelum itu dosen belum berwenang atas pengajuan ini.
        $this->actingAs($this->dosen)->get(route('dosen.pemeriksaan.show', $p))->assertForbidden();
        $this->actingAs($this->dosen)->patch(route('admin.pengajuan.pembimbing', $p), ['pembimbing_id' => $this->dosen->id])
            ->assertForbidden();
        $this->actingAs($this->admin)->patch(route('admin.pengajuan.pembimbing', $p), ['pembimbing_id' => $this->dosen->id])
            ->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame($this->dosen->id, (int) $p->pembimbing_id);
        $this->assertSame(StatusPembimbing::Ditetapkan, $p->status_pembimbing);

        // 5. Admin: dosen tidak berwenang; hasil tanpa keputusan dan revisi tanpa catatan ditolak.
        $this->periksa($this->dosen, 'admin.verifikasi.store', $p, [])->assertForbidden();

        $docAdmin = $this->dokumenPemeriksa($p, 'admin');
        $this->assertGreaterThanOrEqual(2, $docAdmin->count());

        $this->periksa($this->admin, 'admin.verifikasi.store', $p, [])
            ->assertSessionHasErrors("keputusan.{$docAdmin[0]->id}");

        $keputusan = $docAdmin->mapWithKeys(fn ($d) => [$d->id => 'diterima'])->all();
        $keputusan[$docAdmin[0]->id] = 'revisi';
        $this->periksa($this->admin, 'admin.verifikasi.store', $p, $keputusan)
            ->assertSessionHasErrors("catatan.{$docAdmin[0]->id}");
        $this->assertSame(StatusPengajuan::Diajukan, $p->fresh()->status);
        $this->assertDatabaseCount('pemeriksaan', 0);

        // Admin meminta revisi satu dokumen.
        $this->periksa($this->admin, 'admin.verifikasi.store', $p, $keputusan, [$docAdmin[0]->id => 'Scan kurang jelas.'], 'Mohon perbaiki.')
            ->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::RevisiAdministrasi, $p->fresh()->status);
        $this->assertSame(StatusDokumen::Revisi, $docAdmin[0]->fresh()->status);
        $this->assertSame(StatusDokumen::Diterima, $docAdmin[1]->fresh()->status);
        $this->assertSame($docAdmin->count(), $p->pemeriksaan()->count());

        // 6. Mahasiswa memperbaiki: hanya dokumen berstatus revisi yang boleh diunggah ulang.
        $this->unggah($p, $docAdmin[1]->persyaratan)->assertSessionHasErrors('berkas');
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $p))->assertSessionHasErrors('dokumen');

        $this->unggah($p, $docAdmin[0]->persyaratan)->assertSessionHasNoErrors();
        $this->assertSame(2, $p->dokumen()->where('persyaratan_id', $docAdmin[0]->persyaratan_id)->count());
        $this->assertFalse($docAdmin[0]->fresh()->terbaru);
        $versiBaru = $p->dokumenTerbaru()->where('persyaratan_id', $docAdmin[0]->persyaratan_id)->first();
        $this->assertSame(2, $versiBaru->versi);
        $this->assertSame(StatusDokumen::Menunggu, $versiBaru->status);

        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $p))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Diajukan, $p->fresh()->status);

        // 7. Admin memeriksa lagi: hanya versi baru yang menunggu; semua diterima, lanjut ke dosen.
        $menunggu = $this->dokumenPemeriksa($p, 'admin')->filter(fn ($d) => $d->status === StatusDokumen::Menunggu);
        $this->assertCount(1, $menunggu);
        $this->periksa($this->admin, 'admin.verifikasi.store', $p, [$menunggu->first()->id => 'diterima'])
            ->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::MenungguDosen, $p->fresh()->status);

        // Dokumen resmi belum boleh diproses sebelum dosen menyetujui.
        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.arsipkan', $p))->assertForbidden();

        // 8. Dosen: hanya pembimbing yang berwenang.
        $this->periksa($this->dosenLain, 'dosen.pemeriksaan.store', $p, [])->assertForbidden();
        $this->periksa($this->admin, 'dosen.pemeriksaan.store', $p, [])->assertForbidden();

        $docDosen = $this->dokumenPemeriksa($p, 'dosen');
        $this->assertCount(1, $docDosen);
        $this->periksa($this->dosen, 'dosen.pemeriksaan.store', $p, [$docDosen[0]->id => 'revisi'], [$docDosen[0]->id => 'Tambahkan bab metodologi.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::RevisiAkademik, $p->fresh()->status);

        $this->unggah($p, $docDosen[0]->persyaratan, $this->pdf('proposal-revisi.pdf'))->assertSessionHasNoErrors();
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $p))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::MenungguDosen, $p->fresh()->status);

        $baru = $this->dokumenPemeriksa($p, 'dosen')->first();
        $this->periksa($this->dosen, 'dosen.pemeriksaan.store', $p, [$baru->id => 'diterima'])->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Disetujui, $p->fresh()->status);

        // 9. Dokumen resmi dan arsip oleh admin.
        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.arsipkan', $p))
            ->assertSessionHasErrors('dokumen_resmi');
        $this->assertSame(StatusPengajuan::Disetujui, $p->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.store', $p), [
            'jenis' => 'surat_pengantar_kp', 'nomor' => '001/UN9.FASILKOM/2026', 'tanggal' => '2026-10-20',
            'berkas' => UploadedFile::fake()->create('surat.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('berkas');

        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.store', $p), [
            'jenis' => 'sk_ta', 'nomor' => '002', 'tanggal' => '2026-10-20', 'berkas' => $this->pdf('sk.pdf'),
        ])->assertSessionHasErrors('jenis');

        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.store', $p), [
            'jenis' => 'surat_pengantar_kp', 'nomor' => '001/UN9.FASILKOM/2026', 'tanggal' => '2026-10-20',
            'berkas' => $this->pdf('surat-pengantar.pdf'),
        ])->assertSessionHasNoErrors();
        $resmi = $p->dokumenResmi()->firstOrFail();
        Storage::disk('local')->assertExists($resmi->path);

        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.arsipkan', $p), ['catatan' => 'Lengkap.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.arsip.show', $p));

        $p->refresh();
        $this->assertSame(StatusPengajuan::Diarsipkan, $p->status);
        $this->assertDatabaseHas('arsip', ['pengajuan_id' => $p->id, 'diarsipkan_oleh' => $this->admin->id]);

        // 10. Seluruh perubahan status tercatat berurutan beserta pelakunya.
        $this->assertSame([
            'draft', 'diajukan', 'revisi_administrasi', 'diajukan', 'menunggu_dosen',
            'revisi_akademik', 'menunggu_dosen', 'disetujui', 'diarsipkan',
        ], $this->urutanStatus($p));
        $this->assertSame($this->admin->id, (int) $p->riwayat->last()->user_id);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'pengajuan.status']);

        // 11. Setelah diarsipkan: mahasiswa tidak dapat mengubah apa pun; arsip tampil bagi yang berwenang.
        $this->unggah($p, $wajib->first())->assertForbidden();
        $this->actingAs($this->mahasiswa)->put(route('mahasiswa.pengajuan.update', $p), ['judul' => 'Ubah'])->assertForbidden();
        $this->actingAs($this->mahasiswa)->delete(route('mahasiswa.pengajuan.destroy', $p))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.arsip.index'))->assertOk()->assertSee('Rancang Bangun Sistem Arsip Digital');
        $this->actingAs($this->admin)->get(route('admin.arsip.show', $p))->assertOk()->assertSee('001/UN9.FASILKOM/2026');
        $this->actingAs($this->mahasiswa)->get(route('admin.arsip.index'))->assertForbidden();
        $this->actingAs($this->dosen)->get(route('dosen.pemeriksaan.show', $p))->assertOk();
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.berkas.index'))->assertOk()->assertSee('001/UN9.FASILKOM/2026')->assertSee('Rancang Bangun Sistem Arsip Digital');
    }

    public function test_berkas_privat_hanya_dapat_diakses_pihak_yang_berwenang(): void
    {
        $p = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Disetujui);
        $syarat = Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->firstOrFail();
        $this->unggah($p, $syarat);

        // Pengajuan berstatus disetujui sehingga unggahan mahasiswa ditolak; buat dokumen langsung.
        $dok = DokumenPengajuan::create([
            'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $this->mahasiswa->id,
            'versi' => 1, 'terbaru' => true, 'nama_asli' => 'rahasia.pdf',
            'path' => $this->pdf()->store("pengajuan/{$p->id}", 'local'), 'mime' => 'application/pdf', 'ukuran' => 1000,
            'status' => StatusDokumen::Diterima,
        ]);

        $this->actingAs($this->admin)->post(route('admin.dokumen-resmi.store', $p), [
            'jenis' => 'sk_kp', 'nomor' => '003', 'tanggal' => '2026-10-21', 'berkas' => $this->pdf('sk.pdf'),
        ])->assertSessionHasNoErrors();
        $resmi = $p->dokumenResmi()->firstOrFail();

        $lain = User::factory()->mahasiswa()->create();

        $this->assertSame('/berkas/dokumen/'.$dok->id, parse_url(route('berkas.dokumen', ['dokumen' => $dok]), PHP_URL_PATH));

        foreach ([
            [$dok, 'berkas.dokumen', 'dokumen'],
            [$resmi, 'berkas.resmi', 'dokumenResmi'],
        ] as [$model, $rute, $param]) {
            $url = route($rute, [$param => $model]);

            $this->actingAs($this->mahasiswa)->get($url)->assertOk();
            $this->actingAs($this->mahasiswa)->get($url.'?unduh=1')->assertDownload();
            $this->actingAs($this->dosen)->get($url)->assertOk();
            $this->actingAs($this->admin)->get($url)->assertOk();

            $this->actingAs($lain)->get($url)->assertForbidden();
            $this->actingAs($this->dosenLain)->get($url)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->get(route('berkas.dokumen', ['dokumen' => $dok]))->assertRedirect(route('login'));

        // Berkas tidak punya URL publik.
        $this->assertFileDoesNotExist(public_path($dok->path));
        $this->get('/storage/'.$dok->path)->assertNotFound();
    }

    public function test_draft_dapat_diubah_dan_dihapus_beserta_berkasnya(): void
    {
        $p = $this->buatPengajuan($this->mahasiswa);
        $syarat = Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->firstOrFail();
        $this->unggah($p, $syarat)->assertSessionHasNoErrors();
        $path = $p->dokumen()->firstOrFail()->path;
        Storage::disk('local')->assertExists($path);

        // Pada KP, kiriman pembimbing_id tidak berpengaruh karena pembimbing ditetapkan admin.
        $this->actingAs($this->mahasiswa)->put(route('mahasiswa.pengajuan.update', $p), [
            'judul' => 'Judul Diperbarui', 'instansi' => 'Instansi Baru', 'pembimbing_id' => $this->dosenLain->id,
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('Judul Diperbarui', $p->judul);
        $this->assertNull($p->pembimbing_id);
        $this->assertNull($p->pembimbing_pilihan_id);

        $this->actingAs($this->mahasiswa)->delete(route('mahasiswa.pengajuan.destroy', $p))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('pengajuan', ['id' => $p->id]);
        $this->assertDatabaseCount('dokumen_pengajuan', 0);
        $this->assertDatabaseCount('riwayat_status', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_admin_dapat_mengganti_pembimbing_dan_pemeriksaan_dosen_mengikutinya(): void
    {
        $p = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::MenungguDosen);

        $this->actingAs($this->admin)->patch(route('admin.pengajuan.pembimbing', $p), ['pembimbing_id' => $this->dosenLain->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->dosenLain->id, (int) $p->fresh()->pembimbing_id);

        $this->actingAs($this->dosen)->get(route('dosen.pemeriksaan.show', $p))->assertForbidden();
        $this->actingAs($this->dosenLain)->get(route('dosen.pemeriksaan.show', $p))->assertOk();
        $this->actingAs($this->dosenLain)->get(route('dosen.pemeriksaan.index'))->assertOk()->assertSee($this->mahasiswa->name);
        $this->actingAs($this->dosen)->get(route('dosen.pemeriksaan.index'))->assertOk()->assertDontSee($this->mahasiswa->name);

        // Mahasiswa tidak boleh mengganti pembimbing lewat jalur admin.
        $this->actingAs($this->mahasiswa)->patch(route('admin.pengajuan.pembimbing', $p), ['pembimbing_id' => $this->dosen->id])
            ->assertForbidden();
    }

    public function test_riwayat_pemeriksaan_dosen_hanya_memuat_miliknya(): void
    {
        $p = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::MenungguDosen);
        $syarat = Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->where('pemeriksa', 'dosen')->firstOrFail();
        $dok = DokumenPengajuan::create([
            'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $this->mahasiswa->id,
            'versi' => 1, 'terbaru' => true, 'nama_asli' => 'proposal.pdf', 'path' => 'x/proposal.pdf',
            'mime' => 'application/pdf', 'ukuran' => 10, 'status' => StatusDokumen::Menunggu,
        ]);

        $this->periksa($this->dosen, 'dosen.pemeriksaan.store', $p, [$dok->id => 'diterima'])->assertSessionHasNoErrors();

        $this->actingAs($this->dosen)->get(route('dosen.riwayat.index'))->assertOk()
            ->assertSee($this->mahasiswa->name)->assertSee('Diterima');
        $this->actingAs($this->dosenLain)->get(route('dosen.riwayat.index'))->assertOk()
            ->assertDontSee($this->mahasiswa->name);

        $pemeriksaan = $p->pemeriksaan()->firstOrFail();
        $this->assertSame(Role::Dosen, $pemeriksaan->peran);
        $this->assertSame($this->dosen->id, (int) $pemeriksaan->pemeriksa_id);
    }
}
