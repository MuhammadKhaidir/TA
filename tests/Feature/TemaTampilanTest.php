<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tema pasir (sand/ink) hanya untuk halaman login dan area Mahasiswa. Role lain tetap memakai tema navy.
 * Tes ini hanya memastikan kerangka tampilan terpasang pada halaman yang tepat; fungsi halaman diuji di tes lain.
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

    public function test_halaman_mahasiswa_memakai_kerangka_pasir_dengan_menu_profil_dan_logout(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        foreach ([
            route('mahasiswa.dashboard'),
            route('mahasiswa.status.index'),
            route('mahasiswa.berkas.index'),
            route('mahasiswa.pengajuan.index', 'kp'),
            route('profil.show'),
        ] as $url) {
            $this->actingAs($mahasiswa)->get($url)
                ->assertOk()
                ->assertSee('tema-pasir', false)
                ->assertSee('id="shell-pasir"', false)
                ->assertSee('id="toggle-pasir"', false)
                ->assertSee('Profil')
                ->assertSee('Logout');
        }
    }

    public function test_role_selain_mahasiswa_tetap_memakai_tema_navy(): void
    {
        foreach ([Role::Dosen, Role::Operator, Role::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route($role->value.'.dashboard'))
                ->assertOk()
                ->assertDontSee('tema-pasir', false)
                ->assertDontSee('shell-pasir', false)
                ->assertSee('id="sidebar"', false);
        }
    }

    public function test_gambar_tema_tersedia_di_public(): void
    {
        foreach (['UnsriLogo.png', 'UnsriBG.png', 'FasilkomUnsri.png', 'BackgroundDashboard.png'] as $berkas) {
            $this->assertFileExists(public_path('images/'.$berkas));
        }
    }
}
