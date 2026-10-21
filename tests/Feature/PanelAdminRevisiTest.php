<?php

namespace Tests\Feature;

use App\Enums\StatusSidangTa;
use App\Enums\StatusSuratPengantar;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Filament\Resources\Pengaturans\Pages\ListPengaturans;
use App\Filament\Resources\Pengaturans\PengaturanResource;
use App\Filament\Resources\Persyaratans\PersyaratanResource;
use App\Filament\Resources\SidangTas\Pages\ListSidangTas;
use App\Filament\Resources\SidangTas\SidangTaResource;
use App\Filament\Resources\SuratPengantars\Pages\ListSuratPengantars;
use App\Filament\Resources\SuratPengantars\SuratPengantarResource;
use App\Filament\Widgets\RingkasanTa;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\SidangTa;
use App\Models\SuratKeputusan;
use App\Models\SuratPengantar;
use App\Models\User;
use App\Services\SidangService;
use App\Services\SuratPengantarService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Pekerjaan Admin di panel /admin: surat pengantar, pencatatan SK offline, dan penjadwalan sidang. */
class PanelAdminRevisiTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();
        Pengaturan::simpan('bimbingan.minimal_ta', null);
        $this->admin = $this->buatAdmin();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_halaman_baru_di_panel_admin_dapat_dibuka_dan_operator_tidak_bisa_masuk(): void
    {
        foreach ([SuratPengantarResource::class, SidangTaResource::class, PengajuanResource::class, PersyaratanResource::class] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }
        $this->get(PengaturanResource::getUrl('index'))->assertOk();
        // Pengaturan baru muncul per kelompok: jalur SK, hak akses tugas, kartu, sidang.
        foreach (['sk' => 'Jalur penerbitan SK', 'akses' => 'Pelaku:', 'kartu' => 'Kartu konsultasi', 'sidang' => 'Jumlah minimal penguji', 'ta' => 'Pembimbing 2 wajib'] as $kelompok => $teks) {
            Livewire::test(ListPengaturans::class)->filterTable('kelompok', $kelompok)->assertSee($teks);
        }
        Livewire::test(RingkasanTa::class)->assertSee('Surat pengantar');

        $this->actingAs($this->buatOperator());
        $this->get(SuratPengantarResource::getUrl('index'))->assertForbidden();
        $this->get(SidangTaResource::getUrl('index'))->assertForbidden();
    }

    public function test_admin_memproses_surat_pengantar_dari_panel(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs);
        $service = app(SuratPengantarService::class);

        $surat = $this->kirimSuratPengantar($pengajuan, $mhs);
        $this->assertSame(StatusSuratPengantar::Diajukan, $surat->status);

        // Belum lengkap menurut Operator: Admin belum bisa memproses.
        Livewire::test(ListSuratPengantars::class)
            ->assertTableActionHidden('proses', $surat)
            ->assertTableActionHidden('selesai', $surat);

        $service->periksa($surat, $operator);

        Livewire::test(ListSuratPengantars::class)
            ->assertTableActionVisible('proses', $surat)
            ->callTableAction('proses', $surat, data: ['nomor_surat' => 'SP/123/2026'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(StatusSuratPengantar::Diproses, $surat->fresh()->status);

        Livewire::test(ListSuratPengantars::class)
            ->assertTableActionHidden('proses', $surat)
            ->callTableAction('selesai', $surat);
        $this->assertSame(StatusSuratPengantar::Selesai, $surat->fresh()->status);
    }

    public function test_tindakan_surat_pengantar_hilang_bila_tugas_dialihkan_ke_operator(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $pengajuan = $this->pengajuanTa($mhs);
        $surat = app(SuratPengantarService::class)->periksa($this->kirimSuratPengantar($pengajuan, $mhs), $operator);

        Pengaturan::simpan('akses.proses_surat_pengantar', 'operator_akademik');

        Livewire::test(ListSuratPengantars::class)->assertTableActionHidden('proses', $surat);
    }

    public function test_admin_mencatat_sk_offline_dari_panel_dengan_unggahan_salinan(): void
    {
        $operator = $this->buatOperator();
        $mhs = $this->buatMahasiswa();
        $pengajuan = $this->pengajuanTa($mhs, 'disetujui', null, $operator);

        // Mode website: tindakan tidak muncul.
        Livewire::test(ListPengajuans::class)->assertTableActionHidden('catatSk', $pengajuan);

        Pengaturan::simpan('sk.mode', 'offline');
        $salinan = UploadedFile::fake()->createWithContent('sk-resmi.pdf', "%PDF-1.4\n%resmi\n");

        Livewire::test(ListPengajuans::class)
            ->assertTableActionVisible('catatSk', $pengajuan)
            ->callTableAction('catatSk', $pengajuan, data: ['nomor' => '045/UN9/2026', 'tanggal' => now()->toDateString(), 'salinan' => $salinan])
            ->assertHasNoTableActionErrors();

        $sk = SuratKeputusan::firstOrFail();
        $this->assertSame('045/UN9/2026', $sk->nomor);
        $this->assertSame('offline', $sk->sumber);
        Storage::disk('local')->assertExists($sk->file_path);
        $this->assertSame('selesai', $pengajuan->fresh()->status->value);

        // Sesudah tercatat, tindakannya hilang.
        Livewire::test(ListPengajuans::class)->assertTableActionHidden('catatSk', $pengajuan);
    }

    public function test_tindakan_catat_sk_tidak_muncul_untuk_pengajuan_yang_belum_siap(): void
    {
        $operator = $this->buatOperator();
        $diajukan = $this->pengajuanTa($this->buatMahasiswa(), 'diajukan');
        $disetujui = $this->pengajuanTa($this->buatMahasiswa(), 'disetujui', null, $operator);
        Pengaturan::simpan('sk.mode', 'offline');
        // Surat balasan salah satu pengajuan dibuat tidak valid: gerbang menutup tindakan.
        $disetujui->suratBalasan()->update(['status' => 'tidak_valid']);

        Livewire::test(ListPengajuans::class)
            ->assertTableActionHidden('catatSk', $diajukan)
            ->assertTableActionHidden('catatSk', $disetujui);
    }

    public function test_admin_menjadwalkan_dan_menyelesaikan_sidang_dari_panel(): void
    {
        Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'Transkrip', 'wajib' => true, 'aktif' => true, 'urutan' => 1]);
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $ta = $this->taDenganSk($mhs, $a, $b);
        $operator = $this->buatOperator();
        $penguji = $this->buatDospem();

        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $service->unggahDokumen($sidang, Persyaratan::where('jenis', 'ta_sidang')->first(), $this->berkas('transkrip.pdf'), $mhs->user);
        $service->kirim($sidang, $mhs->user);

        // Sebelum disetujui pembimbing, sidang belum masuk antrean penjadwalan Admin.
        Livewire::test(ListSidangTas::class)->assertCanNotSeeTableRecords([$sidang]);

        $service->verifikasi($sidang, $operator);
        $service->setujui($sidang, $a->user);
        $service->setujui($sidang, $b->user);

        $mulai = now()->addDays(5)->setTime(10, 0);
        Livewire::test(ListSidangTas::class)
            ->assertCanSeeTableRecords([$sidang])
            ->assertTableActionVisible('jadwalkan', $sidang)
            ->assertTableActionHidden('selesai', $sidang)
            ->callTableAction('jadwalkan', $sidang, data: [
                'jadwal_mulai' => $mulai->format('Y-m-d H:i:s'),
                'durasi_menit' => 90,
                'ruang' => 'Ruang Sidang 2',
                'penguji' => [$penguji->id],
            ])
            ->assertHasNoTableActionErrors();

        $sidang->refresh();
        $this->assertSame(StatusSidangTa::Dijadwalkan, $sidang->status);
        $this->assertSame('Ruang Sidang 2', $sidang->ruang);
        $this->assertSame([$penguji->id], $sidang->penguji->pluck('dosen_id')->all());

        Livewire::test(ListSidangTas::class)
            ->assertTableActionVisible('selesai', $sidang)
            ->callTableAction('selesai', $sidang);
        $this->assertSame(StatusSidangTa::Selesai, $sidang->fresh()->status);
    }

    public function test_penjadwalan_di_panel_menolak_pembimbing_sebagai_penguji(): void
    {
        Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'Transkrip', 'wajib' => true, 'aktif' => true, 'urutan' => 1]);
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $ta = $this->taDenganSk($mhs, $a, $b);
        $operator = $this->buatOperator();

        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $service->unggahDokumen($sidang, Persyaratan::where('jenis', 'ta_sidang')->first(), $this->berkas('transkrip.pdf'), $mhs->user);
        $service->kirim($sidang, $mhs->user);
        $service->verifikasi($sidang, $operator);
        $service->setujui($sidang, $a->user);
        $service->setujui($sidang, $b->user);

        // Pembimbing tidak ada di opsi penguji, dan data yang dipaksakan tetap ditolak service.
        Livewire::test(ListSidangTas::class)
            ->callTableAction('jadwalkan', $sidang, data: [
                'jadwal_mulai' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'durasi_menit' => 90,
                'ruang' => 'R1',
                'penguji' => [$a->id],
            ]);

        $this->assertSame(StatusSidangTa::Disetujui, $sidang->fresh()->status);
    }

    public function test_persyaratan_dokumen_sidang_dan_pengantar_dapat_dikelola_admin(): void
    {
        $this->get(PersyaratanResource::getUrl('create'))->assertOk();

        Livewire::test(\App\Filament\Resources\Persyaratans\Pages\CreatePersyaratan::class)
            ->fillForm(['jenis' => 'ta_sidang', 'nama' => 'SK TA terbit', 'wajib' => true, 'aktif' => true, 'urutan' => 1, 'tautan_otomatis' => Persyaratan::TAUTAN_SK_TA])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('persyaratans', ['jenis' => 'ta_sidang', 'nama' => 'SK TA terbit', 'tautan_otomatis' => 'sk_ta']);
    }
}
