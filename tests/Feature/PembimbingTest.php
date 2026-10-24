<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\DokumenPengajuan;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\PembimbingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

/**
 * Alur dosen pembimbing:
 *  - KP : ditetapkan operator dari database dosen (mahasiswa tidak memilih).
 *  - TA : dipilih mahasiswa, dosen menerima atau menolak; bila ditolak operator menetapkan
 *         pengganti yang beban bimbingannya paling sedikit.
 */
class PembimbingTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private User $mahasiswa;

    private User $dosenA;

    private User $dosenB;

    private User $dosenC;

    private User $operator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanMaster();

        $this->mahasiswa = User::factory()->mahasiswa()->create([
            'name' => 'Mahasiswa Uji', 'program_studi_id' => ProgramStudi::first()->id, 'angkatan' => 2022,
        ]);
        $this->dosenA = User::factory()->dosen()->create(['name' => 'Dosen Alpha']);
        $this->dosenB = User::factory()->dosen()->create(['name' => 'Dosen Bravo']);
        $this->dosenC = User::factory()->dosen()->create(['name' => 'Dosen Charlie']);
        $this->operator = User::factory()->operator()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /** Pengajuan TA yang memilih $pilihan dan menunggu konfirmasinya. */
    private function buatTa(StatusPengajuan $status, User $pilihan, ?User $mahasiswa = null): Pengajuan
    {
        return Pengajuan::create([
            'mahasiswa_id' => ($mahasiswa ?? $this->mahasiswa)->id,
            'pembimbing_pilihan_id' => $pilihan->id,
            'pembimbing_id' => null,
            'status_pembimbing' => StatusPembimbing::Menunggu,
            'jenis' => JenisPengajuan::Ta,
            'judul' => 'Judul TA Uji',
            'deskripsi' => 'Deskripsi TA uji.',
            'status' => $status,
            'diajukan_at' => $status === StatusPengajuan::Draft ? null : now(),
        ]);
    }

    private function konfirmasi(User $dosen, Pengajuan $p, array $data)
    {
        return $this->actingAs($dosen->fresh())->post(route('dosen.bimbingan.konfirmasi', $p), $data);
    }

    private function tetapkan(User $oleh, Pengajuan $p, User $dosen, array $tambahan = [])
    {
        return $this->actingAs($oleh->fresh())->patch(
            route('operator.pengajuan.pembimbing', $p),
            ['pembimbing_id' => $dosen->id] + $tambahan,
        );
    }

    public function test_kp_tidak_memilih_dosen_dan_kiriman_pembimbing_diabaikan(): void
    {
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.create', 'kp'))
            ->assertOk()
            ->assertDontSee('name="pembimbing_id"', false)
            ->assertSee('Operator akademik menetapkan dosen pembimbing KP');

        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'kp'), [
            'judul' => 'KP Uji', 'instansi' => 'PT Uji', 'pembimbing_id' => $this->dosenA->id,
        ])->assertSessionHasNoErrors();

        $p = Pengajuan::firstOrFail();
        $this->assertNull($p->pembimbing_id);
        $this->assertNull($p->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Belum, $p->status_pembimbing);
        $this->assertDatabaseCount('riwayat_pembimbing', 0);
    }

    public function test_ta_wajib_memilih_dosen_aktif_dan_pilihan_tercatat(): void
    {
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.create', 'ta'))
            ->assertOk()
            ->assertSee('name="pembimbing_id"', false)
            ->assertSee('Dosen Alpha')->assertSee('Dosen Bravo');

        $dasar = ['judul' => 'TA Uji', 'instansi' => 'Lab Uji'];
        $nonaktif = User::factory()->dosen()->nonaktif()->create();

        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'ta'), $dasar)
            ->assertSessionHasErrors('pembimbing_id');
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'ta'), $dasar + ['pembimbing_id' => $this->operator->id])
            ->assertSessionHasErrors('pembimbing_id');
        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'ta'), $dasar + ['pembimbing_id' => $nonaktif->id])
            ->assertSessionHasErrors('pembimbing_id');
        $this->assertDatabaseCount('pengajuan', 0);

        $this->actingAs($this->mahasiswa)->post(route('mahasiswa.pengajuan.store', 'ta'), $dasar + ['pembimbing_id' => $this->dosenA->id])
            ->assertSessionHasNoErrors();

        $p = Pengajuan::firstOrFail();
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_pilihan_id);
        $this->assertNull($p->pembimbing_id);
        $this->assertSame(StatusPembimbing::Menunggu, $p->status_pembimbing);
        $this->assertDatabaseHas('riwayat_pembimbing', [
            'pengajuan_id' => $p->id, 'dosen_id' => $this->dosenA->id, 'user_id' => $this->mahasiswa->id, 'status' => 'menunggu',
        ]);

        // Selama draft, mahasiswa masih boleh mengganti dosen pilihannya.
        $this->actingAs($this->mahasiswa)->put(route('mahasiswa.pengajuan.update', $p), $dasar + ['pembimbing_id' => $this->dosenB->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->dosenB->id, (int) $p->fresh()->pembimbing_pilihan_id);
        $this->assertDatabaseCount('riwayat_pembimbing', 2);
    }

    public function test_dosen_pilihan_baru_melihat_permintaan_setelah_dikirim_dan_belum_berwenang_atas_berkas(): void
    {
        $p = $this->buatTa(StatusPengajuan::Draft, $this->dosenA);

        // Selama draft, permintaan belum tampil dan belum bisa dijawab.
        $this->actingAs($this->dosenA)->get(route('dosen.bimbingan.index'))->assertOk()->assertDontSee('Judul TA Uji');
        $this->konfirmasi($this->dosenA, $p, ['keputusan' => 'terima'])->assertForbidden();

        $p->update(['status' => StatusPengajuan::Diajukan, 'diajukan_at' => now()]);

        $this->actingAs($this->dosenA)->get(route('dosen.bimbingan.index'))
            ->assertOk()->assertSee('Permintaan Pembimbing TA')->assertSee('Judul TA Uji')->assertSee('Mahasiswa Uji');
        $this->actingAs($this->dosenA)->get(route('dosen.dashboard'))->assertOk()->assertSee('memilih Anda sebagai calon pembimbing');

        // Dosen lain tidak melihat dan tidak dapat menjawab.
        $this->actingAs($this->dosenB)->get(route('dosen.bimbingan.index'))->assertOk()->assertDontSee('Judul TA Uji');
        $this->konfirmasi($this->dosenB, $p, ['keputusan' => 'terima'])->assertForbidden();

        // Sebelum menerima, dosen pilihan belum berwenang atas isi pengajuan maupun berkasnya.
        $syarat = Persyaratan::aktif()->untuk(JenisPengajuan::Ta)->firstOrFail();
        $dokumen = DokumenPengajuan::create([
            'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $this->mahasiswa->id,
            'versi' => 1, 'terbaru' => true, 'nama_asli' => 'a.pdf', 'path' => 'x/a.pdf', 'mime' => 'application/pdf',
            'ukuran' => 10, 'status' => StatusDokumen::Menunggu,
        ]);
        $this->actingAs($this->dosenA)->get(route('dosen.pemeriksaan.show', $p))->assertForbidden();
        $this->actingAs($this->dosenA)->get(route('berkas.dokumen', ['dokumen' => $dokumen]))->assertForbidden();

        // Mahasiswa melihat dosen pilihannya sedang menunggu konfirmasi.
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Dosen Alpha (menunggu konfirmasi)')->assertSee('Menunggu konfirmasi dosen');
    }

    public function test_dosen_menerima_permintaan_ta(): void
    {
        $p = $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);

        $this->konfirmasi($this->dosenA, $p, ['keputusan' => 'terima'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dosen.bimbingan.index'));

        $p->refresh();
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_id);
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Diterima, $p->status_pembimbing);
        $this->assertDatabaseHas('riwayat_pembimbing', [
            'pengajuan_id' => $p->id, 'dosen_id' => $this->dosenA->id, 'user_id' => $this->dosenA->id, 'status' => 'diterima',
        ]);
        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $this->dosenA->id, 'aksi' => 'pembimbing.diterima']);

        // Kini menjadi mahasiswa bimbingan dan berwenang atas pengajuan.
        $this->actingAs($this->dosenA)->get(route('dosen.bimbingan.index'))
            ->assertOk()->assertDontSee('Permintaan Pembimbing TA')->assertSee('Mahasiswa Uji')->assertSee('Judul TA Uji');
        $this->actingAs($this->dosenA)->get(route('dosen.pemeriksaan.show', $p))->assertOk();
        $this->actingAs($this->dosenA)->get(route('dosen.dashboard'))->assertOk()->assertDontSee('memilih Anda sebagai calon pembimbing');

        // Permintaan yang sudah dijawab tidak dapat dijawab lagi.
        $this->konfirmasi($this->dosenA, $p, ['keputusan' => 'tolak', 'catatan' => 'Berubah pikiran'])->assertForbidden();
        $this->assertSame(StatusPembimbing::Diterima, $p->fresh()->status_pembimbing);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Dosen Alpha')->assertSee('Diterima dosen');
    }

    public function test_dosen_menolak_wajib_beralasan_lalu_operator_menetapkan_dosen_dengan_bimbingan_tersedikit(): void
    {
        // Beban bimbingan aktif: Alpha 1, Bravo 2, Charlie 0 (pengajuan yang sudah diarsipkan tidak dihitung).
        $lain = fn () => User::factory()->mahasiswa()->create();
        $this->buatPengajuan($lain(), $this->dosenA, StatusPengajuan::Diajukan);
        $this->buatPengajuan($lain(), $this->dosenB, StatusPengajuan::Diajukan);
        $this->buatPengajuan($lain(), $this->dosenB, StatusPengajuan::MenungguDosen);
        $this->buatPengajuan($lain(), $this->dosenC, StatusPengajuan::Diarsipkan);

        $p = $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);

        // Menolak tanpa alasan ditolak.
        $this->konfirmasi($this->dosenA, $p, ['keputusan' => 'tolak', 'catatan' => ''])->assertSessionHasErrors('catatan');
        $this->assertSame(StatusPembimbing::Menunggu, $p->fresh()->status_pembimbing);

        $this->konfirmasi($this->dosenA, $p, ['keputusan' => 'tolak', 'catatan' => 'Bidang tidak sesuai keahlian saya.'])
            ->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertNull($p->pembimbing_id);
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Ditolak, $p->status_pembimbing);
        $this->assertDatabaseHas('riwayat_pembimbing', [
            'pengajuan_id' => $p->id, 'dosen_id' => $this->dosenA->id, 'status' => 'ditolak', 'catatan' => 'Bidang tidak sesuai keahlian saya.',
        ]);
        $this->actingAs($this->dosenA)->get(route('dosen.pemeriksaan.show', $p))->assertForbidden();

        // Mahasiswa melihat penolakan dan alasannya.
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Ditolak dosen')->assertSee('Dosen Alpha menolak.');
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.status.index'))
            ->assertOk()->assertSee('Bidang tidak sesuai keahlian saya.');

        // Operator melihatnya sebagai pengajuan yang perlu penetapan.
        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertSee('Perlu Penetapan Dosen Pembimbing')->assertSee('Mahasiswa Uji');
        $this->actingAs($this->operator)->get(route('operator.pengajuan.index', ['pembimbing' => 'perlu']))
            ->assertOk()->assertSee('Judul TA Uji');

        // Daftar dosen urut dari bimbingan paling sedikit; yang sudah menolak tidak dapat dipilih.
        $beban = app(PembimbingService::class)->daftarDosen($p)->pluck('beban_aktif', 'name');
        $this->assertSame(0, $beban['Dosen Charlie']);
        $this->assertSame(1, $beban['Dosen Alpha']);
        $this->assertSame(2, $beban['Dosen Bravo']);

        $this->actingAs($this->operator)->get(route('operator.pengajuan.show', $p))
            ->assertOk()
            ->assertSeeInOrder(['Dosen Charlie', 'Dosen Alpha', 'Dosen Bravo'])
            ->assertSee('Bimbingan paling sedikit')
            ->assertSee('Menolak pengajuan ini');

        // Dosen yang sudah menolak tidak dapat ditetapkan kembali.
        $this->tetapkan($this->operator, $p, $this->dosenA)->assertSessionHasErrors('pembimbing_id');
        $this->assertNull($p->fresh()->pembimbing_id);

        // Operator menetapkan dosen dengan bimbingan paling sedikit.
        $this->tetapkan($this->operator, $p, $this->dosenC)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame($this->dosenC->id, (int) $p->pembimbing_id);
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Ditetapkan, $p->status_pembimbing);
        $this->assertSame(1, $p->riwayatPembimbing()->where('status', 'ditolak')->count());
        $this->assertSame(1, $p->riwayatPembimbing()->where('status', 'ditetapkan')->count());
        $this->assertDatabaseHas('riwayat_pembimbing', [
            'pengajuan_id' => $p->id, 'dosen_id' => $this->dosenC->id, 'user_id' => $this->operator->id, 'status' => 'ditetapkan',
        ]);

        $this->actingAs($this->dosenC)->get(route('dosen.pemeriksaan.show', $p))->assertOk();
        $this->actingAs($this->dosenC)->get(route('dosen.bimbingan.index'))->assertOk()->assertSee('Mahasiswa Uji');
        $this->assertSame(0, Pengajuan::perluPembimbing()->count());

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Dosen Charlie')->assertSee('Ditetapkan operator');
        $this->actingAs($this->operator)->get(route('operator.pengajuan.show', $p))
            ->assertOk()->assertSee('Riwayat Dosen Pembimbing')->assertSee('Bidang tidak sesuai keahlian saya.');
    }

    public function test_pembimbing_kp_ditetapkan_operator_dan_tampil_di_dashboard_mahasiswa(): void
    {
        $kp = $this->buatPengajuan($this->mahasiswa, null, StatusPengajuan::Diajukan, JenisPengajuan::Kp);
        $mahasiswaDua = User::factory()->mahasiswa()->create(['name' => 'Mahasiswa Dua']);
        $this->buatPengajuan($mahasiswaDua, null, StatusPengajuan::Draft, JenisPengajuan::Kp);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Pembimbing KP')->assertSee('Pembimbing TA')
            ->assertSee('Belum ditentukan')->assertSee('Belum ada pengajuan TA');

        // Antrean operator hanya memuat pengajuan yang sudah dikirim, bukan draft.
        $this->assertSame(1, Pengajuan::perluPembimbing()->count());
        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertSee('Mahasiswa Uji')->assertDontSee('Mahasiswa Dua');

        $this->tetapkan($this->operator, $kp, $this->dosenB)->assertSessionHasNoErrors();

        $kp->refresh();
        $this->assertSame($this->dosenB->id, (int) $kp->pembimbing_id);
        $this->assertNull($kp->pembimbing_pilihan_id);
        $this->assertSame(StatusPembimbing::Ditetapkan, $kp->status_pembimbing);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()->assertSee('Dosen Bravo')->assertSee('Ditetapkan operator');
        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.show', $kp))
            ->assertOk()->assertSee('Dosen Bravo');
        $this->actingAs($this->dosenB)->get(route('dosen.bimbingan.index'))->assertOk()->assertSee('Mahasiswa Uji');
    }

    public function test_dashboard_menampilkan_pembimbing_kp_dan_ta_dari_sumber_masing_masing(): void
    {
        $this->buatPengajuan($this->mahasiswa, $this->dosenA, StatusPengajuan::Diajukan, JenisPengajuan::Kp);
        $this->buatTa(StatusPengajuan::Draft, $this->dosenB);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Pembimbing KP', 'Dosen Alpha', 'Ditetapkan operator', 'Pembimbing TA', 'Dosen Bravo (menunggu konfirmasi)']);
    }

    public function test_hanya_operator_yang_menetapkan_dan_hanya_dosen_pilihan_yang_menjawab(): void
    {
        $p = $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);

        foreach ([$this->mahasiswa, $this->dosenA, $this->dosenB, $this->admin] as $pengguna) {
            $this->tetapkan($pengguna, $p, $this->dosenC)->assertForbidden();
        }

        foreach ([$this->mahasiswa, $this->dosenB, $this->operator, $this->admin] as $pengguna) {
            $this->konfirmasi($pengguna, $p, ['keputusan' => 'terima'])->assertForbidden();
        }

        $p->refresh();
        $this->assertNull($p->pembimbing_id);
        $this->assertSame(StatusPembimbing::Menunggu, $p->status_pembimbing);
        $this->assertDatabaseCount('riwayat_pembimbing', 0);
    }

    public function test_operator_wajib_beralasan_saat_menggantikan_dosen_pilihan_mahasiswa(): void
    {
        $p = $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);

        // Menggantikan dosen yang masih menunggu konfirmasi harus beralasan.
        $this->tetapkan($this->operator, $p, $this->dosenB)->assertSessionHasErrors('catatan');
        $this->assertNull($p->fresh()->pembimbing_id);

        $this->tetapkan($this->operator, $p, $this->dosenB, ['catatan' => 'Dosen Alpha sedang cuti panjang.'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->dosenB->id, (int) $p->fresh()->pembimbing_id);
        $this->assertDatabaseHas('riwayat_pembimbing', ['pengajuan_id' => $p->id, 'status' => 'ditetapkan', 'catatan' => 'Dosen Alpha sedang cuti panjang.']);

        // Mengganti pembimbing yang ditetapkan operator tidak perlu alasan, tetapi dosen yang sama ditolak.
        $this->tetapkan($this->operator, $p, $this->dosenC)->assertSessionHasNoErrors();
        $this->tetapkan($this->operator, $p, $this->dosenC)->assertSessionHasErrors('pembimbing_id');

        // Menggantikan dosen yang sudah menerima juga harus beralasan.
        $mahasiswaDua = User::factory()->mahasiswa()->create();
        $diterima = $this->buatPengajuan($mahasiswaDua, $this->dosenA, StatusPengajuan::Diajukan, JenisPengajuan::Ta);
        $this->tetapkan($this->operator, $diterima, $this->dosenB)->assertSessionHasErrors('catatan');
        $this->assertSame($this->dosenA->id, (int) $diterima->fresh()->pembimbing_id);
    }

    public function test_pengajuan_yang_sudah_diarsipkan_tidak_dapat_diganti_pembimbingnya(): void
    {
        $p = $this->buatPengajuan($this->mahasiswa, $this->dosenA, StatusPengajuan::Diarsipkan);

        $this->tetapkan($this->operator, $p, $this->dosenB)->assertForbidden();
        $this->assertSame($this->dosenA->id, (int) $p->fresh()->pembimbing_id);
    }

    public function test_mahasiswa_tidak_dapat_mengganti_dosen_pilihan_setelah_pengajuan_dikirim(): void
    {
        $p = $this->buatTa(StatusPengajuan::RevisiAkademik, $this->dosenA);

        $this->actingAs($this->mahasiswa)->get(route('mahasiswa.pengajuan.edit', $p))
            ->assertOk()->assertDontSee('name="pembimbing_id"', false)->assertSee('tidak dapat diubah setelah pengajuan dikirim');

        $this->actingAs($this->mahasiswa)->put(route('mahasiswa.pengajuan.update', $p), [
            'judul' => 'Judul Revisi', 'pembimbing_id' => $this->dosenB->id,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Judul Revisi', $p->judul);
        $this->assertSame($this->dosenA->id, (int) $p->pembimbing_pilihan_id);
        $this->assertDatabaseCount('riwayat_pembimbing', 0);
    }

    public function test_permintaan_ke_dosen_yang_dinonaktifkan_masuk_antrean_operator(): void
    {
        $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);
        $this->assertSame(0, Pengajuan::perluPembimbing()->count());

        $this->dosenA->update(['aktif' => false]);

        $this->assertSame(1, Pengajuan::perluPembimbing()->count());
    }

    public function test_dosen_pilihan_yang_masih_dipakai_tidak_dapat_dihapus_admin(): void
    {
        $this->buatTa(StatusPengajuan::Diajukan, $this->dosenA);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->dosenA))->assertSessionHas('galat');
        $this->assertDatabaseHas('users', ['id' => $this->dosenA->id]);
    }
}
