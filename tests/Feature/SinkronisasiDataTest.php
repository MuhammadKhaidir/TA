<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

/**
 * Memastikan perubahan lewat menu admin langsung terbaca di halaman lain
 * (tanpa salinan data) begitu halaman dimuat ulang.
 */
class SinkronisasiDataTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_perubahan_data_mahasiswa_oleh_admin_langsung_dipakai_di_semua_halaman_terkait(): void
    {
        $this->siapkanMaster();
        $ti = ProgramStudi::where('kode', 'TI')->firstOrFail();
        $si = ProgramStudi::where('kode', 'SI')->firstOrFail();

        $admin = User::factory()->admin()->create();
        $dosen = User::factory()->dosen()->create();
        $operator = User::factory()->operator()->create();
        $mahasiswa = User::factory()->mahasiswa()->create([
            'name' => 'Nama Lama Mahasiswa',
            'nomor_induk' => '09021282100001',
            'program_studi_id' => $ti->id,
            'angkatan' => 2020,
        ]);
        $pengajuan = $this->buatPengajuan($mahasiswa, $dosen, StatusPengajuan::Diajukan);

        $this->actingAs($mahasiswa->fresh())->get('/profil')
            ->assertSee('Nama Lama Mahasiswa')->assertSee('09021282100001')->assertSee('Teknik Informatika')->assertSee('2020');

        // Admin mengubah data mahasiswa lewat Manajemen Pengguna.
        $this->actingAs($admin)->put(route('admin.users.update', $mahasiswa), [
            'name' => 'Nama Baru Mahasiswa',
            'email' => $mahasiswa->email,
            'nomor_induk' => '09021282100777',
            'role' => 'mahasiswa',
            'program_studi_id' => $si->id,
            'angkatan' => 2023,
            'no_hp' => '081234567890',
            'password' => '',
            'aktif' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $mahasiswa->id, 'name' => 'Nama Baru Mahasiswa', 'program_studi_id' => $si->id]);

        // Halaman mahasiswa.
        foreach (['/profil', '/mahasiswa/dashboard'] as $url) {
            $this->actingAs($mahasiswa->fresh())->get($url)
                ->assertSee('Nama Baru Mahasiswa')->assertSee('09021282100777')
                ->assertSee('Sistem Informasi')->assertSee('2023')
                ->assertDontSee('Nama Lama Mahasiswa')->assertDontSee('09021282100001');
        }
        $this->actingAs($mahasiswa->fresh())->get(route('mahasiswa.pengajuan.show', $pengajuan))
            ->assertSee('Nama Baru Mahasiswa')->assertSee('Sistem Informasi');

        // Halaman operator dan dosen membaca sumber data yang sama.
        $this->actingAs($operator)->get(route('operator.verifikasi.index'))
            ->assertSee('Nama Baru Mahasiswa')->assertSee('09021282100777')->assertDontSee('Nama Lama Mahasiswa');
        $this->actingAs($operator)->get(route('operator.pengajuan.index'))->assertSee('Nama Baru Mahasiswa');
        $this->actingAs($operator)->get(route('operator.verifikasi.show', $pengajuan))
            ->assertSee('Nama Baru Mahasiswa')->assertSee('Sistem Informasi');
        $this->actingAs($dosen)->get(route('dosen.bimbingan.index'))
            ->assertSee('Nama Baru Mahasiswa')->assertSee('2023')->assertDontSee('Nama Lama Mahasiswa');

        // Mengganti nama program studi di Data Master terbaca di semua halaman.
        $this->actingAs($admin)->put(route('admin.program-studi.update', $si), [
            'kode' => 'SI', 'nama' => 'Sistem Informasi Bisnis', 'jenjang' => 'S1', 'aktif' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($mahasiswa->fresh())->get('/profil')->assertSee('Sistem Informasi Bisnis');
        $this->actingAs($operator)->get(route('operator.pengajuan.index'))->assertSee('Sistem Informasi Bisnis');

        // Mengganti nama dosen terbaca di halaman pengajuan mahasiswa.
        $this->actingAs($admin)->put(route('admin.users.update', $dosen), [
            'name' => 'Dr. Nama Dosen Baru', 'email' => $dosen->email, 'nomor_induk' => $dosen->nomor_induk,
            'role' => 'dosen', 'aktif' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($mahasiswa->fresh())->get(route('mahasiswa.pengajuan.show', $pengajuan))->assertSee('Dr. Nama Dosen Baru');
        $this->actingAs($mahasiswa->fresh())->get(route('mahasiswa.status.index'))->assertSee('Dr. Nama Dosen Baru');
    }

    public function test_pengaturan_persyaratan_langsung_dipakai_pada_form_dan_validasi_mahasiswa(): void
    {
        Storage::fake('local');
        $this->siapkanMaster();

        $admin = User::factory()->admin()->create();
        $dosen = User::factory()->dosen()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $pengajuan = $this->buatPengajuan($mahasiswa, $dosen);

        $awal = Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->firstOrFail();

        // Tambah persyaratan baru, tampil di halaman mahasiswa.
        $this->actingAs($admin)->post(route('admin.persyaratan.store'), [
            'jenis_pengajuan' => 'kp', 'nama' => 'Surat Izin Orang Tua', 'pemeriksa' => 'operator',
            'urutan' => 9, 'ukuran_maks_kb' => 2048, 'ekstensi' => ['pdf'], 'wajib' => 1, 'aktif' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($mahasiswa)->get(route('mahasiswa.pengajuan.show', $pengajuan))->assertSee('Surat Izin Orang Tua');

        // Persyaratan wajib baru menghalangi pengiriman sampai diunggah.
        foreach (Persyaratan::aktif()->untuk(JenisPengajuan::Kp)->where('nama', '!=', 'Surat Izin Orang Tua')->get() as $s) {
            $this->actingAs($mahasiswa)->post(route('mahasiswa.dokumen.store', $pengajuan), [
                'persyaratan_id' => $s->id, 'berkas' => $this->pdf(),
            ])->assertSessionHasNoErrors();
        }
        $this->actingAs($mahasiswa)->post(route('mahasiswa.pengajuan.ajukan', $pengajuan))
            ->assertSessionHasErrors('dokumen');

        // Format dan batas ukuran dibaca dari pengaturan: hanya DOCX, maks. 100 KB.
        $this->actingAs($admin)->put(route('admin.persyaratan.update', $awal), [
            'jenis_pengajuan' => 'kp', 'nama' => $awal->nama, 'pemeriksa' => 'operator',
            'urutan' => 1, 'ukuran_maks_kb' => 100, 'ekstensi' => ['docx'], 'wajib' => 1, 'aktif' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($mahasiswa)->post(route('mahasiswa.dokumen.store', $pengajuan), [
            'persyaratan_id' => $awal->id, 'berkas' => $this->pdf('x.pdf', 50),
        ])->assertSessionHasErrors('berkas');

        // Persyaratan yang dinonaktifkan hilang dari form pengajuan baru dan tidak lagi wajib.
        $baru = Persyaratan::where('nama', 'Surat Izin Orang Tua')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.persyaratan.update', $baru), [
            'jenis_pengajuan' => 'kp', 'nama' => $baru->nama, 'pemeriksa' => 'operator',
            'urutan' => 9, 'ukuran_maks_kb' => 2048, 'ekstensi' => ['pdf'], 'wajib' => 1, 'aktif' => 0,
        ])->assertSessionHasNoErrors();

        $lain = User::factory()->mahasiswa()->create();
        $this->actingAs($lain)->get(route('mahasiswa.pengajuan.create', 'kp'))->assertOk()->assertDontSee('Surat Izin Orang Tua');
    }
}
