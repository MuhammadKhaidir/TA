<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrasiMahasiswaTest extends TestCase
{
    use RefreshDatabase;

    private ProgramStudi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = ProgramStudi::create(['kode' => 'TI', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'aktif' => true]);
    }

    /** @return array<string, mixed> */
    private function data(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Budi Pendaftar',
            'email' => 'budi@contoh.test',
            'nomor_induk' => '09021282126123',
            'program_studi_id' => $this->prodi->id,
            'angkatan' => 2022,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ubah);
    }

    public function test_halaman_daftar_tampil_dan_hanya_memuat_prodi_aktif(): void
    {
        ProgramStudi::create(['kode' => 'XX', 'nama' => 'Prodi Ditutup', 'jenjang' => 'S1', 'aktif' => false]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Daftar akun mahasiswa')
            ->assertSee('Teknik Informatika')
            ->assertDontSee('Prodi Ditutup');
    }

    public function test_halaman_login_punya_tautan_ke_halaman_daftar(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('register'), false);
    }

    public function test_pendaftaran_membuat_akun_mahasiswa_aktif_dan_langsung_masuk(): void
    {
        $this->post('/daftar', $this->data())->assertRedirect(route('mahasiswa.dashboard'));

        $user = User::where('email', 'budi@contoh.test')->firstOrFail();
        $this->assertSame(Role::Mahasiswa, $user->role);
        $this->assertTrue($user->aktif);
        $this->assertSame('09021282126123', $user->nomor_induk);
        $this->assertSame($this->prodi->id, $user->program_studi_id);
        $this->assertSame(2022, $user->angkatan);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotSame('rahasia123', $user->password);

        $this->assertAuthenticatedAs($user);
        $this->get(route('mahasiswa.dashboard'))->assertOk()->assertSee('Akun berhasil dibuat. Selamat datang, Budi Pendaftar.');
    }

    public function test_akun_baru_dapat_masuk_lagi_dengan_email_maupun_nim(): void
    {
        $this->post('/daftar', $this->data());
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => 'budi@contoh.test', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', ['login' => '09021282126123', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_pendaftar_tidak_bisa_menentukan_role_atau_status_aktif(): void
    {
        $this->post('/daftar', $this->data(['role' => 'admin', 'aktif' => 0]))->assertRedirect(route('mahasiswa.dashboard'));

        $user = User::where('email', 'budi@contoh.test')->firstOrFail();
        $this->assertSame(Role::Mahasiswa, $user->role);
        $this->assertTrue($user->aktif);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_email_dibuat_huruf_kecil_dan_duplikat_beda_huruf_ditolak(): void
    {
        $this->post('/daftar', $this->data(['email' => 'Budi@Contoh.TEST']));
        $this->assertDatabaseHas('users', ['email' => 'budi@contoh.test']);
        $this->post('/logout');

        $this->from('/daftar')
            ->post('/daftar', $this->data(['email' => 'BUDI@contoh.test', 'nomor_induk' => '09021282126999']))
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('role', Role::Mahasiswa->value)->count());
    }

    public function test_nim_yang_sudah_terdaftar_ditolak(): void
    {
        User::factory()->mahasiswa()->create(['nomor_induk' => '09021282126123']);

        $this->from('/daftar')
            ->post('/daftar', $this->data())
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors('nomor_induk');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }

    public function test_nim_harus_angka(): void
    {
        $this->post('/daftar', $this->data(['nomor_induk' => 'ABC123']))->assertSessionHasErrors('nomor_induk');
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }

    public function test_program_studi_nonaktif_atau_tidak_ada_ditolak(): void
    {
        $tutup = ProgramStudi::create(['kode' => 'XX', 'nama' => 'Prodi Ditutup', 'jenjang' => 'S1', 'aktif' => false]);

        $this->post('/daftar', $this->data(['program_studi_id' => $tutup->id]))->assertSessionHasErrors('program_studi_id');
        $this->post('/daftar', $this->data(['program_studi_id' => 9999]))->assertSessionHasErrors('program_studi_id');
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }

    public function test_angkatan_diluar_rentang_ditolak(): void
    {
        $this->post('/daftar', $this->data(['angkatan' => now()->year + 1]))->assertSessionHasErrors('angkatan');
        $this->post('/daftar', $this->data(['angkatan' => 1980]))->assertSessionHasErrors('angkatan');
    }

    public function test_kata_sandi_harus_8_karakter_dan_cocok_dengan_konfirmasi(): void
    {
        $this->post('/daftar', $this->data(['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors('password');
        $this->post('/daftar', $this->data(['password_confirmation' => 'beda-sekali']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }

    public function test_semua_isian_wajib_diisi(): void
    {
        $this->post('/daftar', [])->assertSessionHasErrors(['name', 'email', 'nomor_induk', 'program_studi_id', 'angkatan', 'password']);
        $this->assertGuest();
    }

    public function test_pengguna_yang_sudah_login_tidak_dapat_membuka_halaman_daftar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/daftar')->assertRedirect(route('dashboard'));
        $this->post('/daftar', $this->data())->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.test']);
    }
}
