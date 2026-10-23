<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AksesPerRoleTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    /** @return array<string, list<string>> */
    private function halamanPerRole(): array
    {
        return [
            'mahasiswa' => [
                '/mahasiswa/dashboard', '/mahasiswa/pengajuan/kp', '/mahasiswa/pengajuan/ta',
                '/mahasiswa/status', '/mahasiswa/berkas',
            ],
            'dosen' => [
                '/dosen/dashboard', '/dosen/pemeriksaan', '/dosen/bimbingan', '/dosen/riwayat',
            ],
            'operator' => [
                '/operator/dashboard', '/operator/verifikasi', '/operator/pengajuan',
                '/operator/dokumen-resmi', '/operator/arsip',
            ],
            'admin' => [
                '/admin/dashboard', '/admin/pengguna', '/admin/data-master/program-studi',
                '/admin/persyaratan', '/admin/log-aktivitas',
            ],
        ];
    }

    public function test_setiap_role_hanya_dapat_membuka_halamannya_sendiri(): void
    {
        $this->siapkanMaster();

        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($this->halamanPerRole() as $pemilik => $daftar) {
                foreach ($daftar as $url) {
                    $diharapkan = $pemilik === $role->value ? 200 : 403;
                    $status = $this->actingAs($user)->get($url)->status();

                    $this->assertSame($diharapkan, $status, "{$role->value} membuka {$url}");
                }
            }

            $this->actingAs($user)->get('/profil')->assertOk();
        }
    }

    public function test_aksi_tulis_lintas_role_ditolak_di_backend(): void
    {
        $this->siapkanMaster();

        $mahasiswa = User::factory()->mahasiswa()->create();
        $dosen = User::factory()->dosen()->create();
        $operator = User::factory()->operator()->create();
        $admin = User::factory()->admin()->create();
        $pengajuan = $this->buatPengajuan($mahasiswa, $dosen, StatusPengajuan::Diajukan);

        $this->actingAs($mahasiswa)->post('/operator/verifikasi/'.$pengajuan->id)->assertForbidden();
        $this->actingAs($mahasiswa)->post('/admin/pengguna', ['name' => 'X'])->assertForbidden();
        $this->actingAs($mahasiswa)->delete('/admin/pengguna/'.$dosen->id)->assertForbidden();
        $this->actingAs($dosen)->post('/mahasiswa/pengajuan/kp', ['judul' => 'X'])->assertForbidden();
        $this->actingAs($dosen)->post('/operator/verifikasi/'.$pengajuan->id)->assertForbidden();
        $this->actingAs($operator)->post('/dosen/pemeriksaan/'.$pengajuan->id)->assertForbidden();
        $this->actingAs($operator)->post('/admin/persyaratan', ['nama' => 'X'])->assertForbidden();
        $this->actingAs($admin)->get('/operator/verifikasi/'.$pengajuan->id)->assertForbidden();
        $this->actingAs($admin)->get('/mahasiswa/pengajuan/detail/'.$pengajuan->id)->assertForbidden();

        // ID yang tidak ada tetap 403 untuk role yang salah, sehingga keberadaan data tidak bocor.
        $this->actingAs($mahasiswa)->get('/operator/verifikasi/999999')->assertForbidden();
        $this->actingAs($dosen)->get('/mahasiswa/pengajuan/detail/999999')->assertForbidden();

        $this->assertSame(StatusPengajuan::Diajukan, $pengajuan->fresh()->status);
        $this->assertDatabaseCount('users', 4);
    }

    public function test_mahasiswa_tidak_dapat_mengakses_pengajuan_milik_orang_lain(): void
    {
        $pemilik = User::factory()->mahasiswa()->create();
        $lain = User::factory()->mahasiswa()->create();
        $dosen = User::factory()->dosen()->create();
        $pengajuan = $this->buatPengajuan($pemilik, $dosen);

        $this->actingAs($pemilik)->get(route('mahasiswa.pengajuan.show', $pengajuan))->assertOk();

        $this->actingAs($lain)->get(route('mahasiswa.pengajuan.show', $pengajuan))->assertForbidden();
        $this->actingAs($lain)->get(route('mahasiswa.pengajuan.edit', $pengajuan))->assertForbidden();
        $this->actingAs($lain)->put(route('mahasiswa.pengajuan.update', $pengajuan), ['judul' => 'Dibajak'])->assertForbidden();
        $this->actingAs($lain)->post(route('mahasiswa.pengajuan.ajukan', $pengajuan))->assertForbidden();
        $this->actingAs($lain)->post(route('mahasiswa.dokumen.store', $pengajuan))->assertForbidden();
        $this->actingAs($lain)->delete(route('mahasiswa.pengajuan.destroy', $pengajuan))->assertForbidden();

        $this->assertNotSame('Dibajak', $pengajuan->fresh()->judul);
    }

    public function test_dosen_hanya_dapat_membuka_pengajuan_bimbingannya(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();
        $pembimbing = User::factory()->dosen()->create();
        $dosenLain = User::factory()->dosen()->create();
        $pengajuan = $this->buatPengajuan($mahasiswa, $pembimbing, StatusPengajuan::MenungguDosen);

        $this->actingAs($pembimbing)->get(route('dosen.pemeriksaan.show', $pengajuan))->assertOk();

        $this->actingAs($dosenLain)->get(route('dosen.pemeriksaan.show', $pengajuan))->assertForbidden();
        $this->actingAs($dosenLain)->post(route('dosen.pemeriksaan.store', $pengajuan))->assertForbidden();
    }

    public function test_dosen_tidak_dapat_memeriksa_pengajuan_yang_belum_gilirannya(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();
        $pembimbing = User::factory()->dosen()->create();
        $pengajuan = $this->buatPengajuan($mahasiswa, $pembimbing, StatusPengajuan::Diajukan);

        $this->actingAs($pembimbing)->post(route('dosen.pemeriksaan.store', $pengajuan))->assertForbidden();
    }

    public function test_menu_sidebar_sesuai_role_dan_ada_profil_serta_logout(): void
    {
        $menu = [
            'mahasiswa' => ['Dashboard', 'Pengajuan KP', 'Pengajuan TA', 'Status Pengajuan', 'Berkas Saya'],
            'dosen' => ['Dashboard', 'Pemeriksaan Berkas', 'Mahasiswa Bimbingan', 'Riwayat Pemeriksaan'],
            'operator' => ['Dashboard', 'Verifikasi Administrasi', 'Pengelolaan KP/TA', 'Dokumen Resmi', 'Arsip Berkas'],
            'admin' => ['Dashboard', 'Manajemen Pengguna', 'Data Master', 'Pengaturan Persyaratan', 'Log Aktivitas'],
        ];

        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $halaman = $this->actingAs($user)->get(route($role->value.'.dashboard'))->assertOk();

            foreach ($menu[$role->value] as $label) {
                $halaman->assertSee($label);
            }
            $halaman->assertSee('Profil')->assertSee('Logout');

            // Menu role lain tidak ditampilkan.
            foreach ($menu as $pemilik => $daftar) {
                if ($pemilik === $role->value) {
                    continue;
                }
                foreach (array_diff($daftar, $menu[$role->value]) as $label) {
                    $halaman->assertDontSee($label);
                }
            }
        }
    }
}
