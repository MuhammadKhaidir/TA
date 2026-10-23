<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Enums\StatusDokumen;
use App\Models\DokumenPengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AdminDanProfilTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanMaster();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_menambah_pengguna_dan_pengguna_baru_dapat_login(): void
    {
        $prodi = ProgramStudi::first();

        // Mahasiswa wajib memiliki program studi dan angkatan.
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Budi', 'email' => 'budi@fasilkom.test', 'nomor_induk' => '09021282199001',
            'role' => 'mahasiswa', 'password' => 'rahasia123', 'aktif' => 1,
        ])->assertSessionHasErrors(['program_studi_id', 'angkatan']);

        $data = [
            'name' => 'Budi', 'email' => 'budi@fasilkom.test', 'nomor_induk' => '09021282199001',
            'role' => 'mahasiswa', 'program_studi_id' => $prodi->id, 'angkatan' => 2022,
            'no_hp' => '0812-3456-7890', 'password' => 'rahasia123', 'aktif' => 1,
        ];
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();

        // Email dan nomor induk harus unik.
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)
            ->assertSessionHasErrors(['email', 'nomor_induk']);

        $this->post('/logout');
        $this->post('/login', ['login' => '09021282199001', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
        $this->get('/mahasiswa/dashboard')->assertOk()->assertSee('Budi');
    }

    public function test_ubah_pengguna_tanpa_password_mempertahankan_password_lama(): void
    {
        $dosen = User::factory()->dosen()->create();
        $hashLama = $dosen->password;

        $this->actingAs($this->admin)->put(route('admin.users.update', $dosen), [
            'name' => 'Dosen Diubah', 'email' => $dosen->email, 'nomor_induk' => $dosen->nomor_induk,
            'role' => 'dosen', 'password' => '', 'aktif' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($hashLama, $dosen->fresh()->password);

        $this->actingAs($this->admin)->put(route('admin.users.update', $dosen), [
            'name' => 'Dosen Diubah', 'email' => $dosen->email, 'nomor_induk' => $dosen->nomor_induk,
            'role' => 'dosen', 'password' => 'passwordbaru1', 'aktif' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($hashLama, $dosen->fresh()->password);
        $this->assertTrue(\Hash::check('passwordbaru1', $dosen->fresh()->password));
    }

    public function test_admin_tidak_dapat_menurunkan_atau_menghapus_akunnya_sendiri(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'nomor_induk' => $this->admin->nomor_induk,
            'role' => 'mahasiswa', 'program_studi_id' => ProgramStudi::first()->id, 'angkatan' => 2022, 'aktif' => 1,
        ])->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $this->admin->fresh()->role);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertSessionHas('galat');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_pengguna_yang_sudah_punya_data_tidak_dapat_dihapus_tetapi_dapat_dinonaktifkan(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();
        $this->buatPengajuan($mahasiswa, User::factory()->dosen()->create());

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $mahasiswa))->assertSessionHas('galat');
        $this->assertDatabaseHas('users', ['id' => $mahasiswa->id]);

        $kosong = User::factory()->mahasiswa()->create();
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $kosong))->assertSessionHas('sukses');
        $this->assertDatabaseMissing('users', ['id' => $kosong->id]);
    }

    public function test_filter_dan_pencarian_pengguna(): void
    {
        User::factory()->dosen()->create(['name' => 'Dosen Pencarian Unik']);
        User::factory()->mahasiswa()->create(['name' => 'Mahasiswa Pencarian Lain']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'dosen']))
            ->assertSee('Dosen Pencarian Unik')->assertDontSee('Mahasiswa Pencarian Lain');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'Mahasiswa Pencarian']))
            ->assertSee('Mahasiswa Pencarian Lain')->assertDontSee('Dosen Pencarian Unik');
    }

    public function test_crud_program_studi_dan_perlindungan_data_yang_dipakai(): void
    {
        $this->actingAs($this->admin)->post(route('admin.program-studi.store'), [
            'kode' => 'TI', 'nama' => 'Duplikat', 'jenjang' => 'S1', 'aktif' => 1,
        ])->assertSessionHasErrors('kode');

        $this->actingAs($this->admin)->post(route('admin.program-studi.store'), [
            'kode' => 'MI', 'nama' => 'Manajemen Informatika', 'jenjang' => 'D3', 'aktif' => 1,
        ])->assertSessionHasNoErrors();
        $baru = ProgramStudi::where('kode', 'MI')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.program-studi.index'))->assertOk()->assertSee('Manajemen Informatika');

        User::factory()->mahasiswa()->create(['program_studi_id' => $baru->id]);
        $this->actingAs($this->admin)->delete(route('admin.program-studi.destroy', $baru))->assertSessionHas('galat');
        $this->assertDatabaseHas('program_studi', ['id' => $baru->id]);

        $kosong = ProgramStudi::create(['kode' => 'XX', 'nama' => 'Kosong', 'jenjang' => 'S1', 'aktif' => true]);
        $this->actingAs($this->admin)->delete(route('admin.program-studi.destroy', $kosong))->assertSessionHas('sukses');
        $this->assertDatabaseMissing('program_studi', ['id' => $kosong->id]);
    }

    public function test_crud_persyaratan_validasi_ekstensi_dan_perlindungan_dokumen(): void
    {
        $dasar = [
            'jenis_pengajuan' => 'ta', 'nama' => 'Surat Keterangan', 'pemeriksa' => 'operator',
            'urutan' => 1, 'ukuran_maks_kb' => 1024, 'wajib' => 1, 'aktif' => 1,
        ];

        // Hanya ekstensi dalam daftar aman yang diterima.
        foreach ([['exe'], ['html'], ['svg'], []] as $ekstensi) {
            $this->actingAs($this->admin)->post(route('admin.persyaratan.store'), $dasar + ['ekstensi' => $ekstensi])
                ->assertSessionHasErrors();
        }
        $this->assertDatabaseMissing('persyaratan', ['nama' => 'Surat Keterangan']);

        $this->actingAs($this->admin)->post(route('admin.persyaratan.store'), $dasar + ['ekstensi' => ['pdf', 'jpg']])
            ->assertSessionHasNoErrors();
        $syarat = Persyaratan::where('nama', 'Surat Keterangan')->firstOrFail();
        $this->assertSame('pdf,jpg', $syarat->ekstensi);
        $this->assertSame(JenisPengajuan::Ta, $syarat->jenis_pengajuan);

        // Persyaratan yang sudah punya dokumen tidak boleh dihapus.
        $mahasiswa = User::factory()->mahasiswa()->create();
        $p = $this->buatPengajuan($mahasiswa, User::factory()->dosen()->create(), jenis: JenisPengajuan::Ta);
        DokumenPengajuan::create([
            'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $mahasiswa->id,
            'versi' => 1, 'terbaru' => true, 'nama_asli' => 'a.pdf', 'path' => 'x/a.pdf', 'mime' => 'application/pdf',
            'ukuran' => 1, 'status' => StatusDokumen::Menunggu,
        ]);
        $this->actingAs($this->admin)->delete(route('admin.persyaratan.destroy', $syarat))->assertSessionHas('galat');
        $this->assertDatabaseHas('persyaratan', ['id' => $syarat->id]);

        $kosong = Persyaratan::create($dasar + ['nama' => 'Tanpa Dokumen', 'ekstensi' => 'pdf']);
        $this->actingAs($this->admin)->delete(route('admin.persyaratan.destroy', $kosong))->assertSessionHas('sukses');
        $this->assertDatabaseMissing('persyaratan', ['id' => $kosong->id]);
    }

    public function test_log_aktivitas_mencatat_tindakan_admin_dan_dapat_difilter(): void
    {
        $this->actingAs($this->admin)->post(route('admin.program-studi.store'), [
            'kode' => 'LG', 'nama' => 'Program Log Uji', 'jenjang' => 'S1', 'aktif' => 1,
        ]);

        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $this->admin->id, 'aksi' => 'prodi.buat']);

        $this->actingAs($this->admin)->get(route('admin.log.index'))->assertOk()->assertSee('Program Log Uji');
        $this->actingAs($this->admin)->get(route('admin.log.index', ['aksi' => 'prodi']))->assertOk()->assertSee('Program Log Uji');
        $this->actingAs($this->admin)->get(route('admin.log.index', ['aksi' => 'auth']))->assertOk()->assertDontSee('Program Log Uji');
    }

    public function test_dashboard_admin_menampilkan_jumlah_dari_database(): void
    {
        User::factory()->mahasiswa()->count(3)->create();
        User::factory()->dosen()->count(2)->create();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Mahasiswa', '3', 'Dosen', '2']);
    }

    public function test_profil_hanya_mengubah_nomor_hp_dan_kata_sandi(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create(['name' => 'Tetap', 'nomor_induk' => '09021282100555']);

        $this->actingAs($mahasiswa)->put(route('profil.update'), [
            'no_hp' => '0811 222 333', 'name' => 'Diganti', 'role' => 'admin', 'nomor_induk' => '1',
        ])->assertSessionHasNoErrors();

        $mahasiswa->refresh();
        $this->assertSame('0811 222 333', $mahasiswa->no_hp);
        $this->assertSame('Tetap', $mahasiswa->name);
        $this->assertSame(Role::Mahasiswa, $mahasiswa->role);
        $this->assertSame('09021282100555', $mahasiswa->nomor_induk);

        $this->actingAs($mahasiswa)->put(route('profil.update'), ['no_hp' => 'abc'])->assertSessionHasErrors('no_hp');
    }

    public function test_ganti_kata_sandi_membutuhkan_kata_sandi_lama(): void
    {
        $user = User::factory()->dosen()->create();

        $this->actingAs($user)->put(route('profil.password'), [
            'password_lama' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('password_lama');

        $this->actingAs($user)->put(route('profil.password'), [
            'password_lama' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'tidak-sama',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('profil.password'), [
            'password_lama' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['login' => $user->email, 'password' => 'baru12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
