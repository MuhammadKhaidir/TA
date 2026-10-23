<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/mahasiswa/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/pengguna')->assertRedirect(route('login'));
        $this->get('/profil')->assertRedirect(route('login'));
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
    }

    public function test_login_diarahkan_ke_dashboard_sesuai_role(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->post('/login', ['login' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);

            $this->get('/dashboard')->assertRedirect(route($role->value.'.dashboard'));
            $this->get(route($role->value.'.dashboard'))->assertOk();

            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_login_dapat_memakai_nomor_induk(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create(['nomor_induk' => '09021282126999']);

        $this->post('/login', ['login' => '09021282126999', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($mahasiswa);
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['login' => $user->email, 'password' => 'salah-total'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $user = User::factory()->nonaktif()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_akun_yang_dinonaktifkan_saat_login_langsung_dikeluarkan(): void
    {
        $user = User::factory()->mahasiswa()->create();

        $this->actingAs($user)->get('/mahasiswa/dashboard')->assertOk();

        $user->update(['aktif' => false]);

        $this->get('/mahasiswa/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_dan_logout_tercatat_di_log_aktivitas(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->post('/logout');

        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $user->id, 'aksi' => 'auth.login']);
        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $user->id, 'aksi' => 'auth.logout']);
        $this->assertSame(2, LogAktivitas::count());
    }

    public function test_pengguna_login_tidak_dapat_membuka_halaman_login_lagi(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
    }
}
