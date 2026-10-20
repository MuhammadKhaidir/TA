<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class AksesPeranTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
    }

    public function test_hanya_empat_role_yang_ada(): void
    {
        $this->assertEqualsCanonicalizing(Peran::nilai(), Role::query()->pluck('name')->all());
    }

    public function test_empat_aktor_login_ke_dashboard_masing_masing(): void
    {
        $tujuan = [
            [$this->buatMahasiswa()->user, route('mahasiswa.dashboard')],
            [$this->buatDospem()->user, route('dospem.dashboard')],
            [$this->buatOperator(), route('operator.dashboard')],
            [$this->buatAdmin(), url('/admin')],
        ];

        foreach ($tujuan as [$user, $url]) {
            $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($url);
            $this->post(route('logout'));
        }
    }

    public function test_role_lama_tidak_bisa_login_dan_tidak_memperoleh_akses(): void
    {
        // Role lama bisa saja masih tertinggal di database lain; pengguna dengan role di luar empat aktor ditolak.
        Role::create(['name' => 'sekdep_koor_prodi', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('sekdep_koor_prodi');

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Sesi yang sudah berjalan pun dikeluarkan.
        $this->actingAs($user)->get(route('operator.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_login_dan_sesinya_diputus(): void
    {
        $mahasiswa = $this->buatMahasiswa();
        $mahasiswa->user->update(['aktif' => false]);

        $this->post(route('login.process'), ['email' => $mahasiswa->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($mahasiswa->user)->get(route('mahasiswa.dashboard'))->assertRedirect(route('login'));
    }

    public function test_setiap_peran_hanya_bisa_membuka_halamannya_sendiri(): void
    {
        $mahasiswa = $this->buatMahasiswa()->user;
        $dospem = $this->buatDospem()->user;
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();

        $this->actingAs($mahasiswa)->get(route('mahasiswa.dashboard'))->assertOk();
        $this->actingAs($mahasiswa)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($mahasiswa)->get(route('dospem.dashboard'))->assertForbidden();

        $this->actingAs($dospem)->get(route('dospem.dashboard'))->assertOk();
        $this->actingAs($dospem)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($dospem)->get(route('mahasiswa.dashboard'))->assertForbidden();

        $this->actingAs($operator)->get(route('operator.dashboard'))->assertOk();
        $this->actingAs($operator)->get(route('dospem.dashboard'))->assertForbidden();
        $this->actingAs($operator)->get(route('mahasiswa.dashboard'))->assertForbidden();

        // Admin mengelola sistem, bukan memproses pengajuan.
        $this->actingAs($admin)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('dospem.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('mahasiswa.dashboard'))->assertForbidden();
    }

    public function test_panel_admin_hanya_untuk_admin(): void
    {
        $this->actingAs($this->buatAdmin())->get('/admin')->assertOk();
        $this->actingAs($this->buatOperator())->get('/admin')->assertForbidden();
        $this->actingAs($this->buatMahasiswa()->user)->get('/admin')->assertForbidden();
        $this->actingAs($this->buatDospem()->user)->get('/admin')->assertForbidden();
    }

    public function test_registrasi_selalu_mahasiswa_dan_prodi_harus_valid(): void
    {
        $data = [
            'name' => 'Calon Mahasiswa', 'email' => 'calon@ta.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'nim' => '0902118999', 'angkatan' => '2024',
        ];

        // Prodi karangan / role titipan tidak diterima.
        $this->post(route('register.process'), $data + ['prodi_id' => 9999, 'role' => 'admin'])->assertSessionHasErrors('prodi_id');
        $this->assertDatabaseMissing('users', ['email' => 'calon@ta.test']);

        $this->post(route('register.process'), $data + ['prodi_id' => $this->prodi('SI')->id, 'role' => 'admin'])
            ->assertRedirect(route('mahasiswa.dashboard'));

        $user = User::where('email', 'calon@ta.test')->firstOrFail();
        $this->assertSame([Peran::Mahasiswa->value], $user->getRoleNames()->all());
        $this->assertSame($this->prodi('SI')->id, Mahasiswa::where('user_id', $user->id)->value('prodi_id'));
    }

    public function test_prodi_nonaktif_tidak_bisa_dipilih_saat_registrasi(): void
    {
        $this->prodi('SK')->update(['aktif' => false]);

        $this->post(route('register.process'), [
            'name' => 'X', 'email' => 'x@ta.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'nim' => '0902118888', 'angkatan' => '2024', 'prodi_id' => $this->prodi('SK')->id,
        ])->assertSessionHasErrors('prodi_id');
    }
}
