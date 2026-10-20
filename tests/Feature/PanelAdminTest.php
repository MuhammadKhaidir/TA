<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\Peran;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\LogAktivitas\LogAktivitasResource;
use App\Filament\Resources\Mahasiswas\MahasiswaResource;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Filament\Resources\Pengaturans\PengaturanResource;
use App\Filament\Resources\PeriodeAkademiks\PeriodeAkademikResource;
use App\Filament\Resources\Persyaratans\PersyaratanResource;
use App\Filament\Resources\Prodis\Pages\CreateProdi;
use App\Filament\Resources\Prodis\ProdiResource;
use App\Filament\Resources\StrukturLaporans\StrukturLaporanResource;
use App\Filament\Resources\SuratKeputusans\SuratKeputusanResource;
use App\Filament\Resources\TemplateSks\TemplateSkResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\RingkasanKp;
use App\Filament\Widgets\RingkasanSistem;
use App\Filament\Widgets\RingkasanTa;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengajuanService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

class PanelAdminTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->admin = $this->buatAdmin();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_semua_halaman_pengelolaan_admin_dapat_dibuka(): void
    {
        $mhs = $this->buatMahasiswa();
        $dosen = $this->buatDospem();

        foreach ([
            ProdiResource::class, PeriodeAkademikResource::class, UserResource::class, DosenResource::class, MahasiswaResource::class,
            PersyaratanResource::class, TemplateSkResource::class, JenisSuratResource::class, StrukturLaporanResource::class,
            PengaturanResource::class, LogAktivitasResource::class, PengajuanResource::class, SuratKeputusanResource::class,
        ] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }

        foreach ([ProdiResource::class, UserResource::class, PersyaratanResource::class, TemplateSkResource::class, JenisSuratResource::class, StrukturLaporanResource::class, PeriodeAkademikResource::class] as $resource) {
            $this->get($resource::getUrl('create'))->assertOk();
        }

        $this->get(MahasiswaResource::getUrl('edit', ['record' => $mhs]))->assertOk();
        $this->get(DosenResource::getUrl('edit', ['record' => $dosen]))->assertOk();
        $this->get(UserResource::getUrl('edit', ['record' => $mhs->user]))->assertOk();
        $this->get(PengaturanResource::getUrl('edit', ['record' => Pengaturan::firstOrFail()]))->assertOk();
    }

    public function test_admin_menambah_prodi_baru_tanpa_mengubah_kode(): void
    {
        Livewire::test(CreateProdi::class)
            ->fillForm(['kode' => 'MI', 'nama' => 'Manajemen Informatika', 'aktif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('prodis', ['kode' => 'MI', 'nama' => 'Manajemen Informatika']);

        // Kode ganda ditolak.
        Livewire::test(CreateProdi::class)
            ->fillForm(['kode' => 'TI', 'nama' => 'Lain'])
            ->call('create')
            ->assertHasFormErrors(['kode']);

        // Prodi baru langsung muncul sebagai pilihan registrasi.
        $this->post(route('logout'));
        $this->get(route('register'))->assertOk()->assertSee('Manajemen Informatika');
    }

    public function test_admin_membuat_akun_untuk_empat_role_dan_cakupan_operator(): void
    {
        $si = Prodi::where('kode', 'SI')->firstOrFail();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Operator Baru', 'email' => 'op.baru@ta.test', 'password' => 'rahasia123',
                'peran' => Peran::OperatorAkademik->value, 'aktif' => true, 'semua_prodi' => false, 'prodiOperator' => [$si->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $operator = User::where('email', 'op.baru@ta.test')->firstOrFail();
        $this->assertSame([Peran::OperatorAkademik->value], $operator->getRoleNames()->all());
        $this->assertSame([$si->id], $operator->cakupanProdi());
        $this->assertTrue(\Hash::check('rahasia123', $operator->password));
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'akun_dibuat', 'user_id' => $this->admin->id]);

        // Role di luar empat aktor tidak bisa dipilih.
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'x@ta.test', 'password' => 'rahasia123', 'peran' => 'kaprodi'])
            ->call('create')
            ->assertHasFormErrors(['peran']);
        $this->assertDatabaseMissing('users', ['email' => 'x@ta.test']);
    }

    public function test_admin_menonaktifkan_akun_dan_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $mhs = $this->buatMahasiswa();

        Livewire::test(EditUser::class, ['record' => $mhs->user->getRouteKey()])
            ->fillForm(['aktif' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($mhs->user->fresh()->aktif);

        Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
            ->assertFormFieldIsDisabled('aktif');
        $this->assertTrue($this->admin->fresh()->aktif);

        // Akun nonaktif tidak dapat login.
        $this->post(route('logout'));
        $this->post(route('login.process'), ['email' => $mhs->user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_perubahan_peran_lewat_panel_menggantikan_role_lama(): void
    {
        $mhs = $this->buatMahasiswa();

        Livewire::test(EditUser::class, ['record' => $mhs->user->getRouteKey()])
            ->fillForm(['peran' => Peran::Dospem->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([Peran::Dospem->value], $mhs->user->fresh()->getRoleNames()->all());
    }

    public function test_menu_dan_dashboard_admin_menampilkan_ta_dan_kp(): void
    {
        $halaman = $this->get('/admin')->assertOk();
        foreach (['Tugas Akhir (TA)', 'Kerja Praktik (KP)', 'Persyaratan Pengajuan TA', 'Persyaratan Pengajuan KP', 'Template SK TA',
            'Penomoran dan Penandatangan SK', 'Daftar Pengajuan TA', 'Daftar Pengajuan KP', 'Riwayat SK TA Terbit', 'Jenis Surat KP',
            'Struktur Laporan KP', 'Lampiran Laporan KP', 'Pengguna', 'Log Aktivitas'] as $teks) {
            $halaman->assertSee($teks);
        }

        // Widget dashboard dimuat lazy oleh Livewire, jadi diuji langsung.
        Livewire::test(RingkasanTa::class)->assertSee('Pengajuan TA')->assertSee('Siap terbit SK')->assertSee('SK TA terbit');
        Livewire::test(RingkasanKp::class)->assertSee('Pengajuan KP')->assertSee('Surat KP berjalan');
        Livewire::test(RingkasanSistem::class)->assertSee('Penerbitan SK TA')->assertSee('Belum lengkap');
    }

    public function test_pemantauan_pengajuan_read_only_dan_terfilter_per_jenis(): void
    {
        $this->persyaratanProposal();
        $ta = $this->pengajuanTa($this->buatMahasiswa('TI'), 'diajukan');
        $kp = app(PengajuanService::class)->buat($this->buatMahasiswa('SI'), JenisPengajuan::Kp, [
            'instansi_nama' => 'PT Pemantau', 'instansi_alamat' => 'Jl. Uji', 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2027-01-01',
        ], User::first());

        $this->get(PengajuanResource::getUrl('index', ['filters' => ['jenis' => ['value' => 'ta']]]))
            ->assertOk()->assertSee($ta->mahasiswa->nama)->assertDontSee('PT Pemantau');
        $this->get(PengajuanResource::getUrl('index', ['filters' => ['jenis' => ['value' => 'kp']]]))
            ->assertOk()->assertSee('PT Pemantau')->assertDontSee($ta->mahasiswa->nama);

        // Admin tidak punya aksi ubah/buat dan tetap tidak bisa membuka detail atau dokumen lewat web.
        $this->assertFalse(PengajuanResource::canCreate());
        $this->assertFalse(PengajuanResource::canEdit($ta));
        $this->assertFalse(PengajuanResource::canDelete($ta));
        $this->get(route('pengajuan.show', $ta))->assertForbidden();
        $this->get(route('pengajuan.dokumen.unduh', $ta->dokumens()->firstOrFail()))->assertForbidden();

        // Hanya admin yang boleh melihat daftar pemantauan.
        $this->assertFalse(Gate::forUser($ta->mahasiswa->user)->allows('viewAny', Pengajuan::class));
        $this->assertFalse(Gate::forUser($this->buatOperator())->allows('viewAny', Pengajuan::class));
    }

    public function test_pengaturan_terfilter_per_kelompok(): void
    {
        $this->get(PengaturanResource::getUrl('index', ['filters' => ['kelompok' => ['value' => 'sk']]]))
            ->assertOk()->assertSee('Format nomor SK')->assertDontSee('Minimal bimbingan TA');
        $this->get(PengaturanResource::getUrl('index', ['filters' => ['kelompok' => ['value' => 'bimbingan']]]))
            ->assertOk()->assertSee('Minimal bimbingan TA')->assertDontSee('Format nomor SK');
    }
}
