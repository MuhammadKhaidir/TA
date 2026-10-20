<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\Peran;
use App\Models\Pengajuan;
use App\Services\BimbinganService;
use App\Services\PengajuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Pengguna tidak boleh melewati otorisasi dengan mengganti ID di URL/request. */
class OtorisasiDataTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
        $this->konfigurasiSk();
    }

    public function test_mahasiswa_tidak_bisa_melihat_atau_mengubah_data_mahasiswa_lain(): void
    {
        $a = $this->buatMahasiswa();
        $b = $this->buatMahasiswa();
        $milikA = $this->pengajuanTa($a, 'diajukan');
        $dokumenA = $milikA->dokumens()->firstOrFail();

        $this->actingAs($b->user)->get(route('pengajuan.show', $milikA))->assertForbidden();
        $this->actingAs($b->user)->get(route('pengajuan.dokumen.unduh', $dokumenA))->assertForbidden();
        $this->actingAs($b->user)->put(route('mahasiswa.pengajuan.update', $milikA), ['judul' => 'Dibajak'])->assertForbidden();
        $this->actingAs($b->user)->post(route('mahasiswa.pengajuan.kirim', $milikA))->assertForbidden();
        $this->actingAs($b->user)->delete(route('mahasiswa.pengajuan.destroy', $milikA))->assertForbidden();
        $this->actingAs($b->user)->delete(route('mahasiswa.pengajuan.dokumen.hapus', $dokumenA))->assertForbidden();
        $this->assertSame('Sistem Uji Coba Otomatis', $milikA->fresh()->judul);

        // Pemilik tetap bisa.
        $this->actingAs($a->user)->get(route('pengajuan.show', $milikA))->assertOk();
        $this->actingAs($a->user)->get(route('pengajuan.dokumen.unduh', $dokumenA))->assertOk();
    }

    public function test_sk_hanya_dapat_diunduh_pemilik_operator_berwenang_dan_dospemnya(): void
    {
        $a = $this->buatMahasiswa('TI');
        $b = $this->buatMahasiswa('TI');
        $operator = $this->buatOperator();
        $dospem = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($a, 'disetujui', $dospem, $operator);
        $this->actingAs($operator)->post(route('operator.sk.terbitkan', $pengajuan))->assertSessionHasNoErrors();

        $this->actingAs($a->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertOk();
        $this->actingAs($operator)->get(route('pengajuan.sk.unduh', $pengajuan))->assertOk();
        $this->actingAs($dospem->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertOk();

        $this->actingAs($b->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertForbidden();
        $this->actingAs($this->buatDospem()->user)->get(route('pengajuan.sk.unduh', $pengajuan))->assertForbidden();
        $this->actingAs($this->buatOperator(['SI']))->get(route('pengajuan.sk.unduh', $pengajuan))->assertForbidden();
        $this->actingAs($this->buatAdmin())->get(route('pengajuan.sk.unduh', $pengajuan))->assertForbidden();
    }

    public function test_dospem_hanya_mengakses_mahasiswa_yang_ditugaskan_kepadanya(): void
    {
        $operator = $this->buatOperator();
        $dospemA = $this->buatDospem();
        $dospemB = $this->buatDospem();
        $pengajuan = $this->pengajuanTa($this->buatMahasiswa(), 'terverifikasi', null, $operator);
        app(PengajuanService::class)->tetapkanDospem($pengajuan, $dospemA, $operator);

        $this->actingAs($dospemA->user)->get(route('pengajuan.show', $pengajuan))->assertOk();
        $this->actingAs($dospemB->user)->get(route('pengajuan.show', $pengajuan))->assertForbidden();
        $this->actingAs($dospemB->user)->post(route('dospem.pengajuan.setujui', $pengajuan))->assertForbidden();
        $this->actingAs($dospemB->user)->post(route('dospem.pengajuan.revisi', $pengajuan), ['catatan' => 'x'])->assertForbidden();
        $this->assertNotSame('disetujui', $pengajuan->fresh()->status_akademik->value);

        $this->actingAs($dospemB->user)->get(route('dospem.dashboard'))->assertOk()->assertDontSee($pengajuan->mahasiswa->nama);
        $this->actingAs($dospemA->user)->get(route('dospem.dashboard'))->assertOk()->assertSee($pengajuan->mahasiswa->nama);
    }

    public function test_mahasiswa_dan_dospem_tidak_bisa_memanggil_aksi_operator(): void
    {
        $operator = $this->buatOperator();
        $dospem = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'diajukan');

        foreach ([$mhs->user, $dospem->user] as $pelaku) {
            $this->actingAs($pelaku)->post(route('operator.pengajuan.verifikasi', $pengajuan))->assertForbidden();
            $this->actingAs($pelaku)->post(route('operator.pengajuan.dospem', $pengajuan), ['dosen_id' => $dospem->id])->assertForbidden();
            $this->actingAs($pelaku)->post(route('operator.sk.terbitkan', $pengajuan))->assertForbidden();
        }
        $this->assertSame('menunggu', $pengajuan->fresh()->status_administrasi->value);
    }

    public function test_cakupan_operator_dibatasi_per_prodi(): void
    {
        $ti = $this->pengajuanTa($this->buatMahasiswa('TI'), 'diajukan');
        $si = $this->pengajuanTa($this->buatMahasiswa('SI'), 'diajukan');

        $operatorSi = $this->buatOperator(['SI']);
        $operatorSemua = $this->buatOperator();
        $operatorKosong = $this->buatUser(Peran::OperatorAkademik); // tanpa cakupan sama sekali

        // Operator SI hanya melihat dan memproses pengajuan SI.
        $this->actingAs($operatorSi)->get(route('pengajuan.show', $si))->assertOk();
        $this->actingAs($operatorSi)->get(route('pengajuan.show', $ti))->assertForbidden();
        $this->actingAs($operatorSi)->post(route('operator.pengajuan.verifikasi', $ti))->assertForbidden();
        $this->assertSame('menunggu', $ti->fresh()->status_administrasi->value);
        $this->actingAs($operatorSi)->post(route('operator.pengajuan.verifikasi', $si))->assertSessionHasNoErrors();

        $this->actingAs($operatorSi)->get(route('operator.dashboard'))->assertSee($si->mahasiswa->nama)->assertDontSee($ti->mahasiswa->nama);

        // Operator semua prodi melihat keduanya; filter prodi bekerja.
        $this->actingAs($operatorSemua)->get(route('operator.dashboard'))->assertSee($si->mahasiswa->nama)->assertSee($ti->mahasiswa->nama);
        $this->actingAs($operatorSemua)->get(route('operator.dashboard', ['prodi_id' => $this->prodi('TI')->id]))
            ->assertSee($ti->mahasiswa->nama)->assertDontSee($si->mahasiswa->nama);

        // Tanpa assignment = tanpa akses (tidak ada akses implisit).
        $this->actingAs($operatorKosong)->get(route('pengajuan.show', $ti))->assertForbidden();
        $this->actingAs($operatorKosong)->get(route('operator.dashboard'))->assertOk()->assertSee('belum diberi cakupan');
    }

    public function test_bukti_dan_validasi_bimbingan_hanya_untuk_pihak_berwenang(): void
    {
        $operator = $this->buatOperator();
        $dospemA = $this->buatDospem();
        $dospemB = $this->buatDospem();
        $a = $this->buatMahasiswa();
        $b = $this->buatMahasiswa();
        $this->pengajuanTa($a, 'terverifikasi', null, $operator);
        $pengajuan = Pengajuan::where('mahasiswa_id', $a->id)->first();
        app(PengajuanService::class)->tetapkanDospem($pengajuan, $dospemA, $operator);

        $bimbingan = app(BimbinganService::class)->catat($a, JenisPengajuan::Ta, ['tanggal' => now()->toDateString(), 'topik' => 'Bab 1'], $this->berkasGambar(), $a->user);

        $this->actingAs($a->user)->get(route('bimbingan.bukti', $bimbingan))->assertOk();
        $this->actingAs($dospemA->user)->get(route('bimbingan.bukti', $bimbingan))->assertOk();
        $this->actingAs($b->user)->get(route('bimbingan.bukti', $bimbingan))->assertForbidden();
        $this->actingAs($dospemB->user)->get(route('bimbingan.bukti', $bimbingan))->assertForbidden();

        $this->actingAs($dospemB->user)->post(route('dospem.bimbingan.validasi', $bimbingan), ['keputusan' => 'validasi'])->assertForbidden();
        $this->actingAs($b->user)->delete(route('mahasiswa.bimbingan.destroy', $bimbingan))->assertForbidden();
        $this->assertSame('menunggu', $bimbingan->fresh()->status_validasi->value);
    }

    private function berkasGambar()
    {
        return UploadedFile::fake()->image('bukti.jpg');
    }
}
