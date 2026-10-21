<?php

namespace Tests\Feature;

use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\Notifikasi;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Services\NotifikasiService;
use App\Services\PengajuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Notifikasi dalam sistem serta pembatalan, pengajuan ulang, dan pencegahan pengajuan ganda. */
class NotifikasiPembatalanTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
    }

    // ------------------------------------------------------------------
    // Notifikasi
    // ------------------------------------------------------------------

    public function test_service_notifikasi_tidak_memberi_tahu_pelaku_dan_tidak_menggandakan_penerima(): void
    {
        $a = $this->buatUser(\App\Enums\Peran::Mahasiswa);
        $b = $this->buatUser(\App\Enums\Peran::Mahasiswa);

        $jumlah = app(NotifikasiService::class)->kirim([$a, $b, $b, null], 'Judul', 'Isi', '/x', null, $a);

        $this->assertSame(1, $jumlah);
        $this->assertDatabaseCount('notifikasis', 1);
        $this->assertDatabaseHas('notifikasis', ['user_id' => $b->id, 'judul' => 'Judul']);
    }

    public function test_alur_pengajuan_memberi_notifikasi_ke_pihak_berikutnya(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan', null, $operator);

        // Operator pemegang pemeriksaan kelengkapan diberi tahu ada pengajuan masuk.
        $this->assertTrue(Notifikasi::where('user_id', $operator->id)->exists());

        $service = app(PengajuanService::class);
        $service->verifikasiAdministrasi($pengajuan, $operator);
        $service->tetapkanDospem($pengajuan->fresh(), $a, $operator, $b);

        // Kedua pembimbing dan mahasiswa diberi tahu penetapan; pelaku (operator) tidak diberi tahu aksinya sendiri.
        foreach ([$a->user, $b->user, $mhs->user] as $penerima) {
            $this->assertTrue(Notifikasi::where('user_id', $penerima->id)->where('judul', 'like', '%pembimbing%')->exists(), $penerima->name);
        }
        $this->assertFalse(Notifikasi::where('user_id', $operator->id)->where('judul', 'Dosen pembimbing ditetapkan')->exists());
    }

    public function test_daftar_baca_dan_baca_semua_notifikasi_hanya_milik_sendiri(): void
    {
        $saya = $this->buatMahasiswa()->user;
        $lain = $this->buatMahasiswa()->user;
        $service = app(NotifikasiService::class);
        $service->kirim([$saya], 'Untuk saya 1', 'isi', '/mahasiswa');
        $service->kirim([$saya], 'Untuk saya 2', null, 'https://situs-luar.example/phishing');
        $service->kirim([$lain], 'Rahasia orang lain', 'isi');
        $punyaSaya = Notifikasi::where('user_id', $saya->id)->orderBy('id')->get();
        $punyaLain = Notifikasi::where('user_id', $lain->id)->firstOrFail();

        $this->actingAs($saya)->get(route('notifikasi.index'))->assertOk()->assertSee('Untuk saya 1')->assertDontSee('Rahasia orang lain');
        $this->actingAs($saya)->get(route('mahasiswa.dashboard'))->assertOk()->assertSee('2 belum dibaca');

        // Baca satu: diteruskan ke tujuan internal; tautan luar tidak diikuti (anti open-redirect).
        $this->actingAs($saya)->post(route('notifikasi.baca', $punyaSaya[0]))->assertRedirect('/mahasiswa');
        $this->assertTrue($punyaSaya[0]->fresh()->sudahDibaca());
        $this->actingAs($saya)->post(route('notifikasi.baca', $punyaSaya[1]))->assertRedirect(route('notifikasi.index'));

        // Notifikasi orang lain: 404 dan tidak berubah.
        $this->actingAs($saya)->post(route('notifikasi.baca', $punyaLain))->assertNotFound();
        $this->assertFalse($punyaLain->fresh()->sudahDibaca());

        $service->kirim([$saya], 'Baru lagi');
        $this->actingAs($saya)->post(route('notifikasi.baca-semua'))->assertRedirect(route('notifikasi.index'));
        $this->assertSame(0, $service->jumlahBelumDibaca($saya));
        $this->assertSame(1, $service->jumlahBelumDibaca($lain));

        $this->get(route('notifikasi.index'))->assertOk(); // masih login sebagai $saya
        auth()->logout();
        $this->get(route('notifikasi.index'))->assertRedirect(route('login'));
    }

    public function test_surat_pengantar_dan_balasan_memberi_notifikasi(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $pengajuan = $this->pengajuanTa($mhs);

        $surat = $this->kirimSuratPengantar($pengajuan, $mhs);
        $this->assertTrue(Notifikasi::where('user_id', $operator->id)->where('judul', 'like', '%surat pengantar%')->exists());

        $pengantar = app(\App\Services\SuratPengantarService::class);
        $surat = $pengantar->periksa($surat, $operator);
        $this->assertTrue(Notifikasi::where('user_id', $admin->id)->where('judul', 'like', '%surat pengantar%')->exists(), 'Admin diberi tahu ada surat siap diproses');

        $pengantar->selesai($pengantar->proses($surat, $admin), $admin);
        $this->assertTrue(Notifikasi::where('user_id', $mhs->user_id)->where('judul', 'like', '%surat pengantar%')->exists());
    }

    // ------------------------------------------------------------------
    // Pembatalan dan pengajuan ulang pengajuan TA
    // ------------------------------------------------------------------

    public function test_mahasiswa_membatalkan_pengajuan_lalu_mengajukan_ulang_tanpa_menggandakan(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan', null, $operator);

        $this->actingAs($this->buatMahasiswa()->user)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'x'])->assertForbidden();
        $this->actingAs($mhs->user)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($mhs->user)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'Ganti topik'])->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame(StatusPengajuan::Dibatalkan, $pengajuan->status);
        $this->assertSame('Ganti topik', $pengajuan->alasan_batal);
        $this->assertTrue(Notifikasi::where('user_id', $operator->id)->where('judul', 'like', 'Pengajuan TA dibatalkan%')->exists());

        // Pengajuan yang dibatalkan tidak lagi diperiksa/diubah siapa pun.
        $this->actingAs($operator)->post(route('operator.pengajuan.verifikasi', $pengajuan))->assertSessionHasErrors('workflow');
        $this->actingAs($mhs->user)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'lagi'])->assertSessionHasErrors('workflow');

        // Mahasiswa lain tidak boleh mengajukan ulang; pemilik bisa, sekali saja.
        $this->actingAs($this->buatMahasiswa()->user)->post(route('mahasiswa.pengajuan.ajukan-ulang', $pengajuan))->assertForbidden();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.ajukan-ulang', $pengajuan))->assertRedirect();

        $baru = Pengajuan::where('id', '!=', $pengajuan->id)->firstOrFail();
        $this->assertSame(StatusPengajuan::Draft, $baru->status);
        $this->assertSame(StatusPengajuan::Digantikan, $pengajuan->fresh()->status);
        $this->assertSame($baru->id, $pengajuan->fresh()->digantikan_oleh_id);
        $this->assertSame($pengajuan->judul, $baru->judul);

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.ajukan-ulang', $pengajuan))->assertSessionHasErrors('workflow');
        $this->assertSame(2, Pengajuan::where('mahasiswa_id', $mhs->id)->count());

        // Pengajuan yang sudah Digantikan/Dibatalkan tidak menghalangi pengajuan baru bagi mahasiswa tersebut,
        // tetapi draft aktif tidak boleh dobel.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.store', 'ta'), ['judul' => 'Dobel'])->assertSessionHasErrors();
        $this->assertSame(2, Pengajuan::where('mahasiswa_id', $mhs->id)->count());
    }

    public function test_pengajuan_yang_sudah_sk_terbit_atau_draft_tidak_bisa_dibatalkan(): void
    {
        $mhs = $this->buatMahasiswa();
        $draft = $this->pengajuanTa($mhs);
        $this->actingAs($mhs->user)->post(route('pengajuan.batalkan', $draft), ['alasan' => 'x'])->assertSessionHasErrors('workflow');
        $this->assertSame(StatusPengajuan::Draft, $draft->fresh()->status);

        $ta = $this->taDenganSk();
        $this->assertSame(StatusPengajuan::Selesai, $ta->status);
        $this->actingAs($ta->mahasiswa->user)->post(route('pengajuan.batalkan', $ta), ['alasan' => 'x'])->assertSessionHasErrors('workflow');
        $this->assertSame(StatusPengajuan::Selesai, $ta->fresh()->status);
    }

    public function test_operator_membatalkan_dan_pembimbing_tidak_bisa(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $operatorSi = $this->buatOperator(['SI']);
        $dospem = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($mhs, 'terverifikasi', null, $operator);

        $this->actingAs($dospem->user)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'x'])->assertForbidden();
        $this->actingAs($operatorSi)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'x'])->assertForbidden();
        $this->actingAs($operator)->post(route('pengajuan.batalkan', $pengajuan), ['alasan' => 'Dokumen palsu'])->assertSessionHasNoErrors();

        $this->assertSame(StatusPengajuan::Dibatalkan, $pengajuan->fresh()->status);
        $this->assertTrue(Notifikasi::where('user_id', $mhs->user_id)->where('judul', 'like', 'Pengajuan TA dibatalkan%')->exists());
    }

    public function test_pengajuan_dibatalkan_dan_digantikan_tidak_dihitung_sebagai_aktif_untuk_bimbingan(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $a = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($mhs, 'terverifikasi', null, $operator);
        $service = app(PengajuanService::class);
        $service->tetapkanDospem($pengajuan, $a, $operator, $this->buatDospem());

        $bimbingan = app(\App\Services\BimbinganService::class);
        $this->assertNotNull($bimbingan->pengajuanTerkait($mhs, \App\Enums\JenisPengajuan::Ta));

        $service->batalkanPengajuan($pengajuan->fresh(), $mhs->user, 'Batal');
        $this->assertNull($bimbingan->pengajuanTerkait($mhs, \App\Enums\JenisPengajuan::Ta));
    }
}
