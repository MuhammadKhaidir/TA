<?php

namespace Tests\Feature;

use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Models\Dosen;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\SuratKeputusan;
use App\Services\PengajuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class AlurTaTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
        $this->konfigurasiSk();
    }

    public function test_alur_penuh_dari_pengajuan_sampai_sk_terbit_dan_diunduh(): void
    {
        $mhs = $this->buatMahasiswa('TI');
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $dospem = $this->buatDospem('TI');
        $dospem2 = $this->buatDospem('TI');

        // 1. Mahasiswa membuat draft.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.store', 'ta'), ['judul' => 'Sistem Rekomendasi Skripsi'])
            ->assertRedirect();
        $pengajuan = Pengajuan::firstOrFail();
        $this->assertSame(StatusPengajuan::Draft, $pengajuan->status);
        $this->assertSame($mhs->prodi_id, $pengajuan->prodi_id);

        // 1a. Gerbang: pengajuan SK belum boleh dikirim sebelum surat pengantar selesai dan surat balasan valid.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.kirim', $pengajuan))->assertSessionHasErrors('workflow');

        // 1b. Surat pengantar: isi data, kirim; Operator memeriksa; Admin memproses offline lalu menyelesaikan.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.simpan', $pengajuan), $this->isianSuratPengantar())->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengantar.kirim', $pengajuan))->assertSessionHasNoErrors();
        $surat = $pengajuan->fresh()->suratPengantar;
        $this->actingAs($operator)->post(route('operator.pengantar.periksa', $surat))->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('operator.pengantar.proses', $surat))->assertForbidden(); // tugas Admin
        $this->actingAs($admin)->post(route('operator.pengantar.proses', $surat), ['nomor_surat' => 'SP/001'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('operator.pengantar.selesai', $surat))->assertSessionHasNoErrors();

        // 1c. Surat balasan: diunggah mahasiswa, divalidasi Operator dengan checklist lengkap.
        $this->actingAs($mhs->user)->post(route('mahasiswa.balasan.unggah', $pengajuan), ['berkas' => $this->berkas('balasan.pdf')])->assertSessionHasNoErrors();
        $balasan = $pengajuan->fresh()->suratBalasan;
        $this->actingAs($operator)->post(route('operator.balasan.validasi', $balasan), [
            'checklist' => array_fill_keys(array_keys(\App\Models\SuratBalasan::daftarChecklist()), 1),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($pengajuan->fresh()->suratBalasanValid());

        // 1d. Sekarang dokumen diunggah dan pengajuan SK dikirim.
        // Draft tanpa dokumen wajib tidak dapat dikirim.
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.kirim', $pengajuan))->assertSessionHasErrors('workflow');

        $syarat = $pengajuan->persyaratanPengajuan()->first();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$pengajuan, $syarat]), ['berkas' => $this->berkas()])
            ->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.kirim', $pengajuan))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->fresh()->status);

        // 2. SK belum bisa terbit sebelum persetujuan wajib terpenuhi.
        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_keputusans', 0);

        // 3. Operator verifikasi administrasi dan menetapkan dospem.
        $this->actingAs($operator)->post(route('operator.pengajuan.verifikasi', $pengajuan))->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $dospem->id])->assertSessionHasErrors('workflow'); // Pembimbing 2 wajib
        $this->actingAs($operator)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $dospem->id, 'dosen2_id' => $dospem2->id])->assertSessionHasNoErrors();
        $pengajuan->refresh();
        $this->assertSame(StatusAdministrasi::Terverifikasi, $pengajuan->status_administrasi);
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->status, 'Belum disetujui akademik, jadi belum Disetujui.');

        // 4. Pembimbing 1 dan 2 sama-sama menyetujui -> status gabungan Disetujui.
        $this->actingAs($dospem->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertSessionHasNoErrors();
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->fresh()->status, 'Baru satu pembimbing yang setuju.');
        $this->actingAs($dospem2->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertSessionHasNoErrors();
        $pengajuan->refresh();
        $this->assertSame(StatusAkademik::Disetujui, $pengajuan->status_akademik);
        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->status);

        // 5. Pratinjau lalu terbitkan SK dari website.
        $this->actingAs($operator)->get(route('operator.sk.pratinjau', $pengajuan))
            ->assertOk()
            ->assertSee('Sistem Rekomendasi Skripsi', false)
            ->assertSee($mhs->nama, false)
            ->assertHeader('Content-Security-Policy');

        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasNoErrors();

        $sk = SuratKeputusan::firstOrFail();
        $this->assertSame('001/SK/TI/'.now()->year, $sk->nomor);
        $this->assertSame(StatusPengajuan::Selesai, $pengajuan->fresh()->status);
        $this->assertSame($operator->id, $sk->diterbitkan_oleh);
        $this->assertStringContainsString('Sistem Rekomendasi Skripsi', $sk->isi_html);

        $pdf = Storage::disk('local')->get($sk->file_path);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));

        // 6. Mahasiswa mengunduh SK miliknya; riwayat penerbitan tercatat.
        $this->actingAs($mhs->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertOk()->assertDownload();
        $this->assertTrue(LogAktivitas::where('aksi', 'sk_diterbitkan')->where('user_id', $operator->id)->exists());

        // 7. Tidak bisa terbit ganda.
        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('surat_keputusans', 1);
    }

    public function test_operator_mengembalikan_pengajuan_dan_mahasiswa_memperbaiki(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan');

        $this->actingAs($operator)->post(route('operator.pengajuan.kembalikan', $pengajuan), ['catatan' => ''])->assertSessionHasErrors('catatan');
        $this->actingAs($operator)->post(route('operator.pengajuan.kembalikan', $pengajuan), ['catatan' => 'Proposal tidak terbaca, unggah ulang.'])
            ->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame(StatusPengajuan::PerluRevisi, $pengajuan->status);
        $this->assertSame(StatusAdministrasi::Revisi, $pengajuan->status_administrasi);

        // Mahasiswa melihat catatan revisi di halaman detail.
        $this->actingAs($mhs->user)->get(route('pengajuan.show', $pengajuan))->assertOk()->assertSee('Proposal tidak terbaca, unggah ulang.');

        // Perbaiki dokumen dan ajukan ulang.
        $syarat = $pengajuan->persyaratanPengajuan()->first();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$pengajuan, $syarat]), ['berkas' => $this->berkas('baru.pdf')])->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.kirim', $pengajuan))->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->status);
        $this->assertSame(StatusAdministrasi::Menunggu, $pengajuan->status_administrasi);
        $this->assertSame('baru.pdf', $pengajuan->dokumens()->first()->nama_file_asli);
    }

    public function test_dospem_meminta_revisi_dan_status_administrasi_tidak_tercampur(): void
    {
        $mhs = $this->buatMahasiswa();
        $dospem = $this->buatDospem();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs, 'terverifikasi', $dospem, $operator);
        app(PengajuanService::class)->tetapkanDospem($pengajuan, $dospem, $operator, $this->buatDospem());

        $this->actingAs($dospem->user)->post(route('dospem.pengajuan.revisi', $pengajuan), ['catatan' => 'Latar belakang perlu diperdalam.'])
            ->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame(StatusPengajuan::PerluRevisi, $pengajuan->status);
        $this->assertSame(StatusAkademik::Revisi, $pengajuan->status_akademik);
        $this->assertSame(StatusAdministrasi::Terverifikasi, $pengajuan->status_administrasi, 'Revisi akademik tidak membatalkan verifikasi administrasi.');
    }

    public function test_mahasiswa_tidak_bisa_memanipulasi_status_lewat_request(): void
    {
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'draft');

        $this->actingAs($mhs->user)->put(route('mahasiswa.pengajuan.update', $pengajuan), [
            'judul' => 'Judul Baru',
            'status' => 'selesai', 'status_administrasi' => 'terverifikasi', 'status_akademik' => 'disetujui',
            'dospem_id' => $this->buatDospem()->id, 'prodi_id' => $this->prodi('SK')->id, 'mahasiswa_id' => $this->buatMahasiswa()->id,
        ])->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame('Judul Baru', $pengajuan->judul);
        $this->assertSame(StatusPengajuan::Draft, $pengajuan->status);
        $this->assertSame(StatusAdministrasi::Menunggu, $pengajuan->status_administrasi);
        $this->assertSame(StatusAkademik::Menunggu, $pengajuan->status_akademik);
        $this->assertNull($pengajuan->dospem_id);
        $this->assertSame($mhs->prodi_id, $pengajuan->prodi_id);
        $this->assertSame($mhs->id, $pengajuan->mahasiswa_id);
    }

    public function test_pengajuan_tidak_bisa_diubah_setelah_dikirim(): void
    {
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan');

        $this->actingAs($mhs->user)->put(route('mahasiswa.pengajuan.update', $pengajuan), ['judul' => 'Ganti Diam-diam'])
            ->assertSessionHasErrors('workflow');
        $syarat = $pengajuan->persyaratanPengajuan()->first();
        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.dokumen.unggah', [$pengajuan, $syarat]), ['berkas' => $this->berkas()])
            ->assertSessionHasErrors('workflow');
        $this->assertNotSame('Ganti Diam-diam', $pengajuan->fresh()->judul);
    }

    public function test_satu_mahasiswa_hanya_satu_pengajuan_ta_berjalan(): void
    {
        $mhs = $this->buatMahasiswa();
        $this->pengajuanTa($mhs, 'draft');

        $this->actingAs($mhs->user)->post(route('mahasiswa.pengajuan.store', 'ta'), ['judul' => 'Judul Kedua'])->assertSessionHasErrors('workflow');
        $this->assertSame(1, Pengajuan::count());
    }

    public function test_dospem_tidak_valid_ditolak_saat_penetapan(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan');

        $nonaktif = $this->buatDospem();
        $nonaktif->update(['aktif' => false]);
        $tanpaAkun = Dosen::create(['nip' => '19700101', 'nama' => 'Dosen Tanpa Akun', 'prodi_id' => $this->prodi()->id, 'aktif' => true]);

        $sah = $this->buatDospem();
        foreach ([$nonaktif, $tanpaAkun] as $dosen) {
            // Tidak sah sebagai Pembimbing 1 maupun Pembimbing 2.
            $this->actingAs($operator)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $dosen->id, 'dosen2_id' => $sah->id])->assertSessionHasErrors('workflow');
            $this->actingAs($operator)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $sah->id, 'dosen2_id' => $dosen->id])->assertSessionHasErrors('workflow');
        }
        $this->assertNull($pengajuan->fresh()->dospem_id);
        $this->assertNull($pengajuan->fresh()->dospem2_id);
    }

    public function test_pergantian_dospem_mengulang_persetujuan_akademik(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui', null, $operator);
        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->status);

        $baru = $this->buatDospem();
        $this->actingAs($operator)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $baru->id, 'dosen2_id' => $pengajuan->dospem2_id])->assertSessionHasNoErrors();

        $pengajuan->refresh();
        $this->assertSame($baru->id, $pengajuan->dospem_id);
        $this->assertNull($pengajuan->setuju_dospem1_pada);
        $this->assertNull($pengajuan->setuju_dospem2_pada);
        $this->assertSame(StatusAkademik::Menunggu, $pengajuan->status_akademik);
        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->status);
    }
}
