<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tema pasir (sand/ink) dipakai halaman login, halaman galat, dan semua halaman setelah login untuk semua role.
 * Tes ini hanya memastikan kerangka tampilan terpasang; fungsi halaman diuji di tes lain.
 */
class TemaTampilanTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_memakai_tema_pasir_dan_form_tidak_berubah(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('tema-pasir', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="login"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="ingat"', false)
            ->assertSee('data-toggle-password="password"', false)
            ->assertSee('Masuk ke akun Anda');
    }

    public function test_dashboard_semua_role_memakai_kerangka_pasir_dengan_menu_profil_dan_logout(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route($role->value.'.dashboard'))
                ->assertOk()
                ->assertSee('tema-pasir', false)
                ->assertSee('id="shell-pasir"', false)
                ->assertSee('id="toggle-pasir"', false)
                ->assertSee('Profil')
                ->assertSee('Logout');

            $this->actingAs($user)->get(route('profil.show'))
                ->assertOk()
                ->assertSee('id="shell-pasir"', false);
        }
    }

    public function test_halaman_mahasiswa_memakai_kerangka_pasir(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        foreach ([
            route('mahasiswa.status.index'),
            route('mahasiswa.berkas.index'),
            route('mahasiswa.pengajuan.index', 'kp'),
        ] as $url) {
            $this->actingAs($mahasiswa)->get($url)
                ->assertOk()
                ->assertSee('id="shell-pasir"', false);
        }
    }

    public function test_halaman_galat_memakai_tema_pasir(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($mahasiswa)->get('/admin/pengguna')
            ->assertForbidden()
            ->assertSee('tema-pasir', false);
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('tema-pasir', false);
    }

    public function test_gambar_tema_tersedia_di_public(): void
    {
        foreach (['UnsriLogo.png', 'UnsriBG.png', 'FasilkomUnsri.png', 'BackgroundDashboard.png'] as $berkas) {
            $this->assertFileExists(public_path('images/'.$berkas));
        }
    }
}
