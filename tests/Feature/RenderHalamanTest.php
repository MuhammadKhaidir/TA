<?php

namespace Tests\Feature;

use App\Enums\JenisPengajuan;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Models\Arsip;
use App\Models\DokumenPengajuan;
use App\Models\DokumenResmi;
use App\Models\Pengajuan;
use App\Models\Persyaratan;
use App\Models\ProgramStudi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuka setiap halaman (termasuk form tambah/ubah dan halaman detail untuk
 * semua status pengajuan) memakai data dari seeder, untuk menangkap error
 * tampilan, relasi yang kosong, dan variabel yang tidak terdefinisi.
 */
class RenderHalamanTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_akun_contoh_dan_aman_dijalankan_berulang(): void
    {
        $this->seed(DatabaseSeeder::class);
        $jumlah = [User::count(), ProgramStudi::count(), Persyaratan::count()];

        $this->assertSame(6, $jumlah[0]);
        $this->assertSame(3, $jumlah[1]);
        $this->assertGreaterThan(5, $jumlah[2]);

        // Perubahan admin tidak ditimpa seeder.
        ProgramStudi::where('kode', 'TI')->update(['nama' => 'Nama Diubah Admin']);
        User::where('email', 'dosen1@fasilkom.test')->update(['name' => 'Nama Dosen Diubah']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($jumlah, [User::count(), ProgramStudi::count(), Persyaratan::count()]);
        $this->assertSame('Nama Diubah Admin', ProgramStudi::where('kode', 'TI')->value('nama'));
        $this->assertSame('Nama Dosen Diubah', User::where('email', 'dosen1@fasilkom.test')->value('name'));

        // Setiap akun contoh dapat login dan diarahkan ke dashboard role masing-masing.
        foreach (['admin', 'operator', 'dosen1', 'dosen2', 'mahasiswa1', 'mahasiswa2'] as $akun) {
            $this->post('/login', ['login' => "{$akun}@fasilkom.test", 'password' => 'password'])->assertRedirect(route('dashboard'));
            $this->get('/dashboard')->assertRedirect();
            $this->post('/logout');
        }
    }

    public function test_semua_halaman_tampil_untuk_setiap_role_pada_semua_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@fasilkom.test')->firstOrFail();
        $operator = User::where('email', 'operator@fasilkom.test')->firstOrFail();
        $dosen = User::where('email', 'dosen1@fasilkom.test')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa1@fasilkom.test')->firstOrFail();

        // Satu pengajuan per status, bergantian KP dan TA, lengkap dengan dokumen.
        $semua = [];
        foreach (StatusPengajuan::cases() as $i => $status) {
            $jenis = $i % 2 === 0 ? JenisPengajuan::Kp : JenisPengajuan::Ta;
            // Setiap status pembimbing (belum, menunggu, diterima, ditolak, ditetapkan) ikut tampil di semua halaman.
            $pembimbing = StatusPembimbing::cases()[$i % count(StatusPembimbing::cases())];
            $p = Pengajuan::create([
                'mahasiswa_id' => $mahasiswa->id,
                'pembimbing_id' => $pembimbing->aktif() ? $dosen->id : null,
                'pembimbing_pilihan_id' => $pembimbing === StatusPembimbing::Belum || $pembimbing === StatusPembimbing::Ditetapkan ? null : $dosen->id,
                'status_pembimbing' => $pembimbing,
                'jenis' => $jenis,
                'judul' => "Judul {$status->value}", 'deskripsi' => 'Deskripsi contoh.', 'instansi' => 'Instansi Contoh',
                'status' => $status, 'diajukan_at' => $status === StatusPengajuan::Draft ? null : now(),
            ]);
            $p->riwayatPembimbing()->create([
                'dosen_id' => $dosen->id, 'user_id' => $mahasiswa->id, 'status' => $pembimbing, 'catatan' => 'Catatan uji pembimbing',
            ]);

            foreach (Persyaratan::aktif()->untuk($jenis)->get() as $syarat) {
                foreach ([1, 2] as $versi) {
                    DokumenPengajuan::create([
                        'pengajuan_id' => $p->id, 'persyaratan_id' => $syarat->id, 'diunggah_oleh' => $mahasiswa->id,
                        'versi' => $versi, 'terbaru' => $versi === 2, 'nama_asli' => "{$syarat->id}-v{$versi}.pdf",
                        'path' => "pengajuan/{$p->id}/{$syarat->id}-v{$versi}.pdf", 'mime' => 'application/pdf', 'ukuran' => 120_000,
                        'status' => collect(StatusDokumen::cases())[($i + $versi) % 3],
                    ]);
                }
            }

            $p->riwayat()->create([
                'user_id' => $mahasiswa->id, 'status_lama' => null, 'status_baru' => StatusPengajuan::Draft, 'catatan' => null,
            ]);

            if (in_array($status, [StatusPengajuan::Disetujui, StatusPengajuan::Diarsipkan], true)) {
                DokumenResmi::create([
                    'pengajuan_id' => $p->id, 'jenis' => array_key_first($jenis->jenisDokumenResmi()), 'nomor' => "00{$i}/UN9",
                    'tanggal' => now()->toDateString(), 'diunggah_oleh' => $operator->id,
                    'nama_asli' => 'resmi.pdf', 'path' => "resmi/{$p->id}/resmi.pdf", 'mime' => 'application/pdf', 'ukuran' => 5000,
                ]);
            }
            if ($status === StatusPengajuan::Diarsipkan) {
                Arsip::create(['pengajuan_id' => $p->id, 'diarsipkan_oleh' => $operator->id, 'diarsipkan_at' => now(), 'catatan' => 'Lengkap']);
            }

            $semua[] = $p;
        }

        $prodi = ProgramStudi::firstOrFail();
        $syarat = Persyaratan::firstOrFail();

        $halaman = [
            'mahasiswa' => [
                route('mahasiswa.dashboard'), route('mahasiswa.status.index'), route('mahasiswa.berkas.index'),
                route('mahasiswa.pengajuan.index', 'kp'), route('mahasiswa.pengajuan.index', 'ta'),
                route('profil.show'),
            ],
            'dosen' => [
                route('dosen.dashboard'), route('dosen.pemeriksaan.index'), route('dosen.bimbingan.index'),
                route('dosen.riwayat.index'), route('profil.show'),
            ],
            'operator' => [
                route('operator.dashboard'), route('operator.verifikasi.index'), route('operator.pengajuan.index'),
                route('operator.dokumen-resmi.index'), route('operator.arsip.index'), route('profil.show'),
                route('operator.pengajuan.index', ['q' => 'Judul']), route('operator.pengajuan.index', ['status' => 'diarsipkan']),
                route('operator.pengajuan.index', ['jenis' => 'ta']), route('operator.arsip.index', ['q' => 'Judul']),
            ],
            'admin' => [
                route('admin.dashboard'), route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $mahasiswa),
                route('admin.users.edit', $dosen), route('admin.users.edit', $admin),
                route('admin.program-studi.index'), route('admin.program-studi.create'), route('admin.program-studi.edit', $prodi),
                route('admin.persyaratan.index'), route('admin.persyaratan.create'), route('admin.persyaratan.edit', $syarat),
                route('admin.log.index'), route('profil.show'),
            ],
        ];

        foreach ($semua as $p) {
            $halaman['mahasiswa'][] = route('mahasiswa.pengajuan.show', $p);
            if ($p->status->bisaDiubahMahasiswa()) {
                $halaman['mahasiswa'][] = route('mahasiswa.pengajuan.edit', $p);
            }
            $halaman['dosen'][] = route('dosen.pemeriksaan.show', $p);
            $halaman['operator'][] = route('operator.verifikasi.show', $p);
            $halaman['operator'][] = route('operator.pengajuan.show', $p);
            $halaman['operator'][] = route('operator.dokumen-resmi.show', $p);
            $halaman['operator'][] = route('operator.arsip.show', $p);
        }
        $halaman['mahasiswa'][] = route('mahasiswa.pengajuan.create', 'ta');

        $pengguna = ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'operator' => $operator, 'admin' => $admin];

        foreach ($halaman as $role => $daftar) {
            foreach ($daftar as $url) {
                $respons = $this->actingAs($pengguna[$role]->fresh())->get($url);

                // Beberapa halaman sengaja menolak status tertentu (mis. arsip untuk pengajuan belum diarsipkan);
                // yang tidak boleh terjadi adalah error server atau halaman kosong.
                $this->assertContains($respons->status(), [200, 302, 403, 404], "{$role}: {$url} -> {$respons->status()}");
                $this->assertLessThan(500, $respons->status(), "{$role}: {$url}");
                if ($respons->status() === 200) {
                    $respons->assertSee('Logout');
                    $this->assertStringNotContainsString('Undefined', $respons->getContent(), "{$role}: {$url}");
                }
            }
        }

        // Halaman utama tiap role harus benar-benar 200.
        foreach (['mahasiswa', 'dosen', 'operator', 'admin'] as $role) {
            $this->actingAs($pengguna[$role]->fresh())->get(route("{$role}.dashboard"))->assertOk();
        }
    }
}
