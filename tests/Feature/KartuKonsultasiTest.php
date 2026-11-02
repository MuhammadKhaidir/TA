<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

/**
 * Kartu konsultasi (PDF): hanya mahasiswa pemilik, dan hanya setelah dosen pembimbingnya sah
 * (KP: ditetapkan operator, TA: diterima dosen pilihan atau ditetapkan operator).
 */
class KartuKonsultasiTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private User $mahasiswa;

    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanMaster();

        $this->mahasiswa = User::factory()->mahasiswa()->create([
            'name' => 'Mahasiswa Uji', 'nomor_induk' => '09021282227001',
            'program_studi_id' => ProgramStudi::first()->id, 'angkatan' => 2022,
        ]);
        $this->dosen = User::factory()->dosen()->create(['name' => 'Dosen Alpha', 'nomor_induk' => '198001012005011001']);
    }

    private function unduh(User $oleh, Pengajuan $pengajuan): TestResponse
    {
        return $this->actingAs($oleh)->get(route('mahasiswa.kartu-konsultasi.unduh', $pengajuan));
    }

    /** TA yang dosen pilihannya belum menjawab atau sudah menolak: belum ada pembimbing sah. */
    private function buatTaTanpaPembimbingSah(User $mahasiswa, StatusPembimbing $status): Pengajuan
    {
        return Pengajuan::create([
            'mahasiswa_id' => $mahasiswa->id,
            'pembimbing_id' => null,
            'pembimbing_pilihan_id' => $this->dosen->id,
            'status_pembimbing' => $status,
            'jenis' => JenisPengajuan::Ta,
            'judul' => 'Judul TA Uji',
            'status' => StatusPengajuan::Diajukan,
            'diajukan_at' => now(),
        ]);
    }

    public function test_mahasiswa_mengunduh_kartu_kp_berisi_data_pengajuannya(): void
    {
        $pengajuan = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Diajukan, JenisPengajuan::Kp);

        $respons = $this->unduh($this->mahasiswa, $pengajuan)->assertOk();

        $respons->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $respons->headers->get('Content-Disposition'));
        $this->assertStringContainsString('kartu-konsultasi-kp-09021282227001.pdf', (string) $respons->headers->get('Content-Disposition'));

        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF-', $isi);

        foreach ([
            'KARTU KONSULTASI KERJA PRAKTIK', 'Mahasiswa Uji', '09021282227001', 'angkatan 2022',
            'Sistem Informasi Contoh', 'PT Contoh Nusantara', 'Dosen Alpha', '198001012005011001',
        ] as $teks) {
            $this->assertStringContainsString($teks, $isi, $teks);
        }
    }

    public function test_kartu_ta_tersedia_setelah_dosen_pilihan_menerima(): void
    {
        $pengajuan = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::MenungguDosen, JenisPengajuan::Ta);

        $respons = $this->unduh($this->mahasiswa, $pengajuan)->assertOk();

        $this->assertStringContainsString('KARTU KONSULTASI TUGAS AKHIR', $respons->getContent());
        $this->assertStringContainsString('kartu-konsultasi-ta-', (string) $respons->headers->get('Content-Disposition'));
    }

    public function test_kartu_belum_tersedia_sebelum_ada_dosen_pembimbing_sah(): void
    {
        $kpTanpaPembimbing = $this->buatPengajuan($this->mahasiswa, null, StatusPengajuan::Diajukan, JenisPengajuan::Kp);
        $this->unduh($this->mahasiswa, $kpTanpaPembimbing)->assertForbidden();

        $menunggu = User::factory()->mahasiswa()->create();
        $taMenunggu = $this->buatTaTanpaPembimbingSah($menunggu, StatusPembimbing::Menunggu);
        $this->unduh($menunggu, $taMenunggu)->assertForbidden();

        $ditolak = User::factory()->mahasiswa()->create();
        $taDitolak = $this->buatTaTanpaPembimbingSah($ditolak, StatusPembimbing::Ditolak);
        $this->unduh($ditolak, $taDitolak)->assertForbidden();
    }

    public function test_hanya_mahasiswa_pemilik_yang_dapat_mengunduh(): void
    {
        $pengajuan = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Diajukan, JenisPengajuan::Kp);

        $this->unduh(User::factory()->mahasiswa()->create(), $pengajuan)->assertForbidden();
        $this->unduh($this->dosen, $pengajuan)->assertForbidden();
        $this->unduh(User::factory()->operator()->create(), $pengajuan)->assertForbidden();
        $this->unduh(User::factory()->admin()->create(), $pengajuan)->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $pengajuan = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Diajukan, JenisPengajuan::Kp);

        $this->get(route('mahasiswa.kartu-konsultasi.unduh', $pengajuan))->assertRedirect(route('login'));
    }

    public function test_halaman_detail_menampilkan_tombol_unduh_hanya_bila_pembimbing_sah(): void
    {
        $siap = $this->buatPengajuan($this->mahasiswa, $this->dosen, StatusPengajuan::Diajukan, JenisPengajuan::Kp);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $siap))
            ->assertOk()
            ->assertSee('Unduh Kartu Konsultasi')
            ->assertSee(route('mahasiswa.kartu-konsultasi.unduh', $siap));

        $lain = User::factory()->mahasiswa()->create();
        $belum = $this->buatPengajuan($lain, null, StatusPengajuan::Diajukan, JenisPengajuan::Kp);

        $this->actingAs($lain)->get(route('mahasiswa.pengajuan.show', $belum))
            ->assertOk()
            ->assertDontSee('Unduh Kartu Konsultasi')
            ->assertSee('Kartu konsultasi tersedia setelah dosen pembimbing ditetapkan.');
    }
}
