<?php

namespace Tests\Feature;

use App\Enums\StatusSidangTa;
use App\Exceptions\WorkflowException;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Persyaratan;
use App\Models\SidangTa;
use App\Models\SuratKeputusan;
use App\Models\User;
use App\Services\BimbinganService;
use App\Services\SidangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanData;
use Tests\TestCase;

/** Modul sidang TA: gerbang SK, dokumen, verifikasi, persetujuan pembimbing, penjadwalan, dan pembatalan. */
class SidangTaTest extends TestCase
{
    use MenyiapkanData, RefreshDatabase;

    private Persyaratan $dokSk;

    private Persyaratan $dokKartu;

    private Persyaratan $dokTranskrip;

    private Persyaratan $dokLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLingkungan();
        $this->persyaratanProposal();

        $this->dokSk = Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'SK TA', 'wajib' => true, 'aktif' => true, 'urutan' => 1, 'tautan_otomatis' => Persyaratan::TAUTAN_SK_TA]);
        $this->dokKartu = Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'Kartu konsultasi', 'wajib' => true, 'aktif' => true, 'urutan' => 2, 'tautan_otomatis' => Persyaratan::TAUTAN_KARTU_KONSULTASI]);
        $this->dokTranskrip = Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'Transkrip nilai', 'wajib' => true, 'aktif' => true, 'urutan' => 3]);
        // Migrasi awal mengisi bimbingan.minimal_ta = 12; sebagian besar tes di sini bukan tentang batas itu.
        Pengaturan::simpan('bimbingan.minimal_ta', null);

        $this->dokLain = Persyaratan::create(['jenis' => 'ta_sidang', 'nama' => 'Bukti artikel', 'wajib' => false, 'aktif' => true, 'urutan' => 4]);
    }

    /** @return array{0: Mahasiswa, 1: Pengajuan, 2: Dosen, 3: Dosen} */
    private function taBersk(): array
    {
        $a = $this->buatDospem();
        $b = $this->buatDospem();
        $mhs = $this->buatMahasiswa();
        $ta = $this->taDenganSk($mhs, $a, $b);

        return [$mhs, $ta, $a, $b];
    }

    private function lengkapiDokumen(SidangTa $sidang, Mahasiswa $mhs): void
    {
        app(SidangService::class)->unggahDokumen($sidang, $this->dokTranskrip, $this->berkas('transkrip.pdf'), $mhs->user);
    }

    private function catatBimbinganTervalidasi(Mahasiswa $mhs, Dosen $dospem, int $jumlah, int $mulaiHari = 1): void
    {
        $service = app(BimbinganService::class);
        for ($i = $mulaiHari; $i < $mulaiHari + $jumlah; $i++) {
            $b = $service->catat($mhs, \App\Enums\JenisPengajuan::Ta, ['tanggal' => now()->subDays($i)->toDateString(), 'topik' => "Bab {$i}"], null, $mhs->user);
            $service->validasi($b, $dospem->user, true, null);
        }
    }

    /** Sidang yang sudah disetujui kedua pembimbing dan siap dijadwalkan. */
    private function sidangDisetujui(): array
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);

        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $service->verifikasi($sidang, $operator);
        $service->setujui($sidang, $a->user);
        $sidang = $service->setujui($sidang, $b->user);

        return [$sidang, $mhs, $ta, $a, $b, $operator];
    }

    private function waktu(int $hariKeDepan = 7, string $jam = '09:00'): string
    {
        return now()->addDays($hariKeDepan)->format('Y-m-d').' '.$jam;
    }

    // ------------------------------------------------------------------

    public function test_sidang_hanya_dapat_dibuat_setelah_sk_terbit_dan_satu_yang_aktif_per_ta(): void
    {
        $mhs = $this->buatMahasiswa();
        $operator = $this->buatOperator();
        $ta = $this->pengajuanTa($mhs, 'disetujui', null, $operator);

        // SK belum terbit: ditolak lewat web maupun service.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.store', $ta))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('sidang_tas', 0);

        $this->konfigurasiSk();
        app(\App\Services\SkService::class)->terbitkan($ta, $operator);

        $this->actingAs($this->buatMahasiswa()->user)->post(route('mahasiswa.sidang.store', $ta))->assertForbidden();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.store', $ta))->assertRedirect();
        $this->assertDatabaseCount('sidang_tas', 1);

        // Hanya satu pengajuan sidang aktif per TA.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.store', $ta))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('sidang_tas', 1);
    }

    public function test_dokumen_sidang_tautan_otomatis_tidak_diunggah_dan_kelengkapan_diperiksa(): void
    {
        [$mhs, $ta] = $this->taBersk();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);

        // SK dan kartu ditautkan otomatis oleh sistem, jadi hanya transkrip yang masih kurang.
        $this->assertTrue($service->tautanTersedia($sidang, $this->dokSk));
        $this->assertTrue($service->tautanTersedia($sidang, $this->dokKartu));
        $this->assertSame(['Transkrip nilai'], $service->dokumenKurang($sidang)->pluck('nama')->all());

        // Mahasiswa tidak dapat mengunggah dokumen yang ditautkan otomatis.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.unggah', [$sidang, $this->dokSk]), ['berkas' => $this->berkas()])->assertSessionHasErrors('workflow');

        // Kirim tanpa kelengkapan ditolak.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.kirim', $sidang))->assertSessionHasErrors('workflow');
        $this->assertSame(StatusSidangTa::Draft, $sidang->fresh()->status);

        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.unggah', [$sidang, $this->dokTranskrip]), ['berkas' => $this->berkas('transkrip.pdf')])->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.kirim', $sidang))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Diajukan, $sidang->fresh()->status);

        // Setelah dikirim, dokumen terkunci sampai dikembalikan.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.unggah', [$sidang, $this->dokLain]), ['berkas' => $this->berkas()])->assertSessionHasErrors('workflow');
    }

    public function test_tautan_sk_yang_hilang_menahan_pengajuan_sidang(): void
    {
        [$mhs, $ta] = $this->taBersk();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);

        SuratKeputusan::query()->delete();
        $this->assertFalse($service->tautanTersedia($sidang, $this->dokSk));

        $this->expectException(WorkflowException::class);
        $service->kirim($sidang, $mhs->user);
    }

    public function test_alur_penuh_sidang_lewat_http(): void
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $admin = $this->buatAdmin();
        $penguji1 = $this->buatDospem();
        $penguji2 = $this->buatDospem();

        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.store', $ta));
        $sidang = SidangTa::firstOrFail();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.unggah', [$sidang, $this->dokTranskrip]), ['berkas' => $this->berkas('transkrip.pdf')])->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.kirim', $sidang))->assertSessionHasNoErrors();

        // Hanya pemeriksa kelengkapan (Operator) yang memverifikasi; Admin dan Dospem tidak.
        $this->actingAs($admin)->post(route('operator.sidang.verifikasi', $sidang))->assertForbidden();
        $this->actingAs($a->user)->post(route('operator.sidang.verifikasi', $sidang))->assertForbidden();
        $this->actingAs($mhs->user)->post(route('operator.sidang.verifikasi', $sidang))->assertForbidden();
        $this->actingAs($operator)->post(route('operator.sidang.verifikasi', $sidang))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Terverifikasi, $sidang->fresh()->status);

        // Persetujuan akademik: kedua pembimbing wajib; Operator dan penguji tidak boleh menyetujui.
        $this->actingAs($operator)->post(route('dospem.sidang.setujui', $sidang))->assertForbidden();
        $this->actingAs($penguji1->user)->post(route('dospem.sidang.setujui', $sidang))->assertForbidden();
        $this->actingAs($a->user)->post(route('dospem.sidang.setujui', $sidang))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Terverifikasi, $sidang->fresh()->status);
        $this->actingAs($b->user)->post(route('dospem.sidang.setujui', $sidang))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Disetujui, $sidang->fresh()->status);

        // Penjadwalan: tugas Admin (bawaan). Operator tidak.
        $payload = ['jadwal_mulai' => $this->waktu(), 'durasi_menit' => 90, 'ruang' => 'Ruang 1', 'penguji' => [$penguji1->id, $penguji2->id]];
        $this->actingAs($operator)->post(route('operator.sidang.jadwalkan', $sidang), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('operator.sidang.jadwalkan', $sidang), array_merge($payload, ['penguji' => []]))->assertSessionHasErrors('penguji');
        $this->actingAs($admin)->post(route('operator.sidang.jadwalkan', $sidang), $payload)->assertSessionHasNoErrors();

        $sidang->refresh();
        $this->assertSame(StatusSidangTa::Dijadwalkan, $sidang->status);
        $this->assertSame('Ruang 1', $sidang->ruang);
        $this->assertSame([$penguji1->id, $penguji2->id], $sidang->penguji->pluck('dosen_id')->all());

        // Mahasiswa, pembimbing, dan penguji mendapat notifikasi jadwal.
        foreach ([$mhs->user, $a->user, $b->user, $penguji1->user, $penguji2->user] as $penerima) {
            $this->assertTrue(Notifikasi::where('user_id', $penerima->id)->where('judul', 'Sidang dijadwalkan')->exists(), "Notifikasi jadwal untuk {$penerima->name}");
        }

        // Konfirmasi selesai: tugas Admin.
        $this->actingAs($operator)->post(route('operator.sidang.selesai', $sidang))->assertForbidden();
        $this->actingAs($admin)->post(route('operator.sidang.selesai', $sidang))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Selesai, $sidang->fresh()->status);

        // Setelah selesai, tidak bisa dijadwalkan atau dibatalkan lagi.
        $this->actingAs($admin)->post(route('operator.sidang.jadwalkan', $sidang), array_merge($payload, ['jadwal_mulai' => $this->waktu(9)]))->assertSessionHasErrors('workflow');
        $this->actingAs($mhs->user)->post(route('sidang.batalkan', $sidang), ['alasan' => 'x'])->assertSessionHasErrors('workflow');

        // Jejak lengkap tercatat di riwayat.
        foreach (['sidang_dibuat', 'sidang_diajukan', 'sidang_diverifikasi', 'sidang_dijadwalkan', 'sidang_selesai'] as $aksi) {
            $this->assertDatabaseHas('log_aktivitas', ['aksi' => $aksi]);
        }
    }

    public function test_persetujuan_sidang_menunggu_minimal_bimbingan_tervalidasi(): void
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $service->verifikasi($sidang, $operator);

        // Minimum belum diatur kampus: tidak ada batas [K-05].
        $this->assertNull($service->ringkasanBimbingan($sidang)['minimal']);

        Pengaturan::simpan('bimbingan.minimal_ta', '3');
        $this->catatBimbinganTervalidasi($mhs, $a, 2);

        try {
            $service->setujui($sidang, $a->user);
            $this->fail('Persetujuan seharusnya tertahan minimal bimbingan.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('2 dari minimal 3', $e->getMessage());
        }
        $this->actingAs($b->user)->post(route('dospem.sidang.setujui', $sidang))->assertSessionHasErrors('workflow');
        $this->assertNull($sidang->fresh()->setuju_dospem1_pada);

        // Bimbingan yang belum divalidasi tidak dihitung.
        app(BimbinganService::class)->catat($mhs, \App\Enums\JenisPengajuan::Ta, ['tanggal' => now()->subDays(20)->toDateString(), 'topik' => 'Belum divalidasi'], null, $mhs->user);
        $this->assertSame(2, $service->ringkasanBimbingan($sidang)['divalidasi']);
        $this->assertSame(3, $service->ringkasanBimbingan($sidang)['tercatat']);

        // Setelah bimbingan ketiga divalidasi, kedua pembimbing dapat menyetujui.
        $this->catatBimbinganTervalidasi($mhs, $a, 1, 30);
        $service->setujui($sidang, $a->user);
        $sidang = $service->setujui($sidang, $b->user);
        $this->assertSame(StatusSidangTa::Disetujui, $sidang->status);
    }

    public function test_batas_bawaan_dari_migrasi_awal_tetap_berlaku_sampai_admin_mengubahnya(): void
    {
        [$mhs, $ta, $a] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $service->verifikasi($sidang, $operator);

        Pengaturan::simpan('bimbingan.minimal_ta', '12');
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('0 dari minimal 12');
        $service->setujui($sidang, $a->user);
    }

    public function test_persetujuan_sidang_berhasil_setelah_minimal_bimbingan_terpenuhi(): void
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $service->verifikasi($sidang, $operator);

        Pengaturan::simpan('bimbingan.minimal_ta', '2');
        $this->catatBimbinganTervalidasi($mhs, $a, 2);

        $service->setujui($sidang, $a->user);
        $sidang = $service->setujui($sidang, $b->user);
        $this->assertSame(StatusSidangTa::Disetujui, $sidang->status);
    }

    public function test_pengembalian_dan_revisi_mengulang_persetujuan_dari_awal(): void
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);
        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);

        // Operator mengembalikan (catatan wajib).
        $this->actingAs($operator)->post(route('operator.sidang.kembalikan', $sidang), ['catatan' => ''])->assertSessionHasErrors('catatan');
        $this->actingAs($operator)->post(route('operator.sidang.kembalikan', $sidang), ['catatan' => 'Transkrip buram'])->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::PerluRevisi, $sidang->fresh()->status);

        // Mahasiswa memperbaiki dan mengirim ulang.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.unggah', [$sidang, $this->dokTranskrip]), ['berkas' => $this->berkas('transkrip-baru.pdf')])->assertSessionHasNoErrors();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.kirim', $sidang))->assertSessionHasNoErrors();
        $service->verifikasi($sidang, $operator);

        // Pembimbing 1 setuju, lalu Pembimbing 2 meminta revisi: persetujuan Pembimbing 1 ikut diulang.
        $service->setujui($sidang, $a->user);
        $this->assertNotNull($sidang->fresh()->setuju_dospem1_pada);
        $this->actingAs($b->user)->post(route('dospem.sidang.revisi', $sidang), ['catatan' => ''])->assertSessionHasErrors('catatan');
        $this->actingAs($b->user)->post(route('dospem.sidang.revisi', $sidang), ['catatan' => 'Lengkapi bukti artikel'])->assertSessionHasNoErrors();

        $sidang->refresh();
        $this->assertSame(StatusSidangTa::PerluRevisi, $sidang->status);
        $this->assertNull($sidang->setuju_dospem1_pada);
        $this->assertNull($sidang->setuju_dospem2_pada);
    }

    public function test_aturan_penjadwalan_dan_penguji(): void
    {
        [$sidang, $mhs, $ta, $a, $b] = $this->sidangDisetujui();
        $admin = $this->buatAdmin();
        $service = app(SidangService::class);
        $penguji = $this->buatDospem();
        $lain = $this->buatDospem();
        $nonaktif = $this->buatDospem();
        $nonaktif->update(['aktif' => false]);

        $coba = function (array $ids, ?string $jam = null, ?string $pesan = null) use ($service, $sidang, $admin) {
            try {
                $service->jadwalkan($sidang, $admin, $jam ?? $this->waktu(), 'R1', $ids);
                $this->fail('Penjadwalan seharusnya ditolak.');
            } catch (WorkflowException $e) {
                if ($pesan !== null) {
                    $this->assertStringContainsString($pesan, $e->getMessage());
                }
            }
            $this->assertSame(StatusSidangTa::Disetujui, $sidang->fresh()->status);
        };

        $coba([$a->id], null, 'pembimbing TA ini');             // pembimbing tidak boleh menguji
        $coba([$b->id], null, 'pembimbing TA ini');
        $coba([$penguji->id, $penguji->id], null, 'lebih dari sekali');
        $coba([$nonaktif->id], null, 'dosen aktif');
        $coba([], null, 'minimal');
        $coba([$penguji->id], now()->subHour()->format('Y-m-d H:i'), 'masa depan');
        $coba([$penguji->id], 'bukan tanggal', 'tidak valid');

        // Jumlah minimal penguji mengikuti pengaturan [K-10].
        Pengaturan::simpan('sidang.minimal_penguji', '2');
        $coba([$penguji->id], null, 'minimal 2');
        $sidang = $service->jadwalkan($sidang, $admin, $this->waktu(), 'R1', [$penguji->id, $lain->id]);
        $this->assertSame(StatusSidangTa::Dijadwalkan, $sidang->status);

        // Penjadwalan ulang menggantikan penguji lama.
        $sidang = $service->jadwalkan($sidang, $admin, $this->waktu(8), 'R2', [$lain->id, $penguji->id]);
        $this->assertSame([$lain->id, $penguji->id], $sidang->penguji->pluck('dosen_id')->all());
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'sidang_dijadwalkan_ulang']);
        $this->assertSame(2, \App\Models\SidangTaPenguji::count());
    }

    public function test_jadwal_bentrok_dosen_atau_ruang_ditolak(): void
    {
        [$sidang1, , , , , $operator] = $this->sidangDisetujui();
        [$sidang2] = $this->sidangDisetujui();
        $admin = $this->buatAdmin();
        $service = app(SidangService::class);
        $pengujiX = $this->buatDospem();
        $pengujiY = $this->buatDospem();
        $pengujiZ = $this->buatDospem();

        $service->jadwalkan($sidang1, $admin, $this->waktu(7, '09:00'), 'Ruang A', [$pengujiX->id]);

        // Penguji yang sama pada waktu beririsan.
        try {
            $service->jadwalkan($sidang2, $admin, $this->waktu(7, '10:00'), 'Ruang B', [$pengujiX->id]);
            $this->fail('Bentrok penguji seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('bentrok', $e->getMessage());
            $this->assertStringContainsString($pengujiX->nama, $e->getMessage());
        }

        // Ruang yang sama (tanpa membedakan huruf besar/kecil) pada waktu beririsan.
        try {
            $service->jadwalkan($sidang2, $admin, $this->waktu(7, '10:00'), 'ruang a', [$pengujiY->id]);
            $this->fail('Bentrok ruang seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('ruang', $e->getMessage());
        }
        $this->assertSame(StatusSidangTa::Disetujui, $sidang2->fresh()->status);

        // Pembimbing sidang lain yang sedang bersidang tidak boleh menjadi penguji pada waktu yang sama.
        $pembimbing1 = $sidang1->pengajuan->dospem;
        try {
            $service->jadwalkan($sidang2, $admin, $this->waktu(7, '09:30'), 'Ruang C', [$pembimbing1->id]);
            $this->fail('Bentrok pembimbing seharusnya ditolak.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('bentrok', $e->getMessage());
        }

        // Waktu tidak beririsan (setelah durasi 90 menit) dan ruang berbeda: boleh. Jadwal ulang diri sendiri juga boleh.
        $service->jadwalkan($sidang2, $admin, $this->waktu(7, '10:30'), 'Ruang A', [$pengujiX->id]);
        $this->assertSame(StatusSidangTa::Dijadwalkan, $sidang2->fresh()->status);
        $service->jadwalkan($sidang2, $admin, $this->waktu(7, '10:30'), 'Ruang A', [$pengujiX->id, $pengujiZ->id]);
        $this->assertCount(2, $sidang2->fresh()->penguji);
    }

    public function test_pembatalan_melepas_jadwal_dan_pengajuan_ulang_menyalin_dokumen(): void
    {
        [$sidang, $mhs, $ta, $a, $b, $operator] = $this->sidangDisetujui();
        $admin = $this->buatAdmin();
        $service = app(SidangService::class);
        $penguji = $this->buatDospem();
        $service->jadwalkan($sidang, $admin, $this->waktu(), 'R1', [$penguji->id]);

        // Pihak luar tidak boleh membatalkan; alasan wajib.
        $this->actingAs($this->buatMahasiswa()->user)->post(route('sidang.batalkan', $sidang), ['alasan' => 'x'])->assertForbidden();
        $this->actingAs($a->user)->post(route('sidang.batalkan', $sidang), ['alasan' => 'x'])->assertForbidden();
        $this->actingAs($mhs->user)->post(route('sidang.batalkan', $sidang), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($mhs->user)->post(route('sidang.batalkan', $sidang), ['alasan' => 'Menunda sidang'])->assertSessionHasNoErrors();

        $sidang->refresh();
        $this->assertSame(StatusSidangTa::Dibatalkan, $sidang->status);
        $this->assertNull($sidang->jadwal_mulai);
        $this->assertSame(0, $sidang->penguji()->count());
        $this->assertSame('Menunda sidang', $sidang->alasan_batal);

        // Pengajuan ulang: yang lama Digantikan, yang baru draft berisi salinan dokumen unggahan.
        $this->actingAs($this->buatMahasiswa()->user)->post(route('mahasiswa.sidang.ajukan-ulang', $sidang))->assertForbidden();
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.ajukan-ulang', $sidang))->assertRedirect();

        $baru = SidangTa::where('id', '!=', $sidang->id)->firstOrFail();
        $this->assertSame(StatusSidangTa::Draft, $baru->status);
        $this->assertSame(StatusSidangTa::Digantikan, $sidang->fresh()->status);
        $this->assertSame($baru->id, $sidang->fresh()->digantikan_oleh_id);
        $this->assertSame(1, $baru->dokumens()->count());
        Storage::disk('local')->assertExists($baru->dokumens->first()->file_path);
        $this->assertNotSame($sidang->dokumens->first()->file_path, $baru->dokumens->first()->file_path);

        // Tidak ada pengajuan ganda: yang lama tidak bisa diajukan ulang dua kali.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.ajukan-ulang', $sidang))->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('sidang_tas', 2);

        // Kirim ulang hasil pengajuan ulang tanpa mengunggah apa pun lagi.
        $this->actingAs($mhs->user)->post(route('mahasiswa.sidang.kirim', $baru))->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Diajukan, $baru->fresh()->status);
    }

    public function test_draft_tidak_bisa_dibatalkan_dan_pembatalan_oleh_operator_memberi_tahu_mahasiswa(): void
    {
        [$mhs, $ta] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);
        $sidang = $service->buat($ta, $mhs->user);

        $this->actingAs($mhs->user)->post(route('sidang.batalkan', $sidang), ['alasan' => 'x'])->assertSessionHasErrors('workflow');

        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $this->actingAs($operator)->post(route('sidang.batalkan', $sidang), ['alasan' => 'Dokumen tidak sesuai ketentuan'])->assertSessionHasNoErrors();

        $this->assertSame(StatusSidangTa::Dibatalkan, $sidang->fresh()->status);
        $this->assertTrue(Notifikasi::where('user_id', $mhs->user_id)->where('judul', 'Pengajuan sidang dibatalkan')->exists());
    }

    public function test_hak_akses_dan_visibilitas_sidang_per_peran(): void
    {
        [$sidang, $mhs, $ta, $a, $b, $operator] = $this->sidangDisetujui();
        $admin = $this->buatAdmin();
        $lain = $this->buatMahasiswa();
        $dospemLain = $this->buatDospem();
        $operatorSi = $this->buatOperator(['SI']);

        // Yang boleh melihat.
        foreach ([$mhs->user, $a->user, $b->user, $operator] as $pengguna) {
            $this->actingAs($pengguna)->get(route('sidang.show', $sidang))->assertOk();
        }
        // Yang tidak boleh: mahasiswa lain, dospem lain, operator prodi lain, Admin (bukan pemeriksa kelengkapan) lewat web.
        foreach ([$lain->user, $dospemLain->user, $operatorSi, $admin] as $pengguna) {
            $this->actingAs($pengguna)->get(route('sidang.show', $sidang))->assertForbidden();
        }

        // Daftar sidang tersaring sesuai kewenangan.
        $this->actingAs($mhs->user)->get(route('sidang.index'))->assertOk();
        $this->actingAs($operator)->get(route('sidang.index'))->assertOk()->assertSee($mhs->nama);
        $this->actingAs($operatorSi)->get(route('sidang.index'))->assertOk()->assertDontSee($mhs->nama);
        $this->actingAs($dospemLain->user)->get(route('sidang.index'))->assertOk()->assertDontSee($mhs->nama);

        // Berkas dokumen sidang hanya untuk pihak yang boleh melihat sidangnya.
        $dokumen = $sidang->dokumens()->firstOrFail();
        $this->actingAs($mhs->user)->get(route('sidang.dokumen.unduh', $dokumen))->assertOk();
        $this->actingAs($operator)->get(route('sidang.dokumen.unduh', $dokumen))->assertOk();
        $this->actingAs($lain->user)->get(route('sidang.dokumen.unduh', $dokumen))->assertForbidden();
        $this->actingAs($dospemLain->user)->get(route('sidang.dokumen.unduh', $dokumen))->assertForbidden();

        // Mahasiswa lain tidak boleh mengubah dokumen maupun mengirim.
        $this->actingAs($lain->user)->post(route('mahasiswa.sidang.kirim', $sidang))->assertForbidden();
        $this->actingAs($lain->user)->delete(route('mahasiswa.sidang.dokumen.hapus', $dokumen))->assertForbidden();
    }

    public function test_pelaku_tugas_sidang_dapat_dialihkan_lewat_pengaturan(): void
    {
        [$sidang, , , , , $operator] = $this->sidangDisetujui();
        $admin = $this->buatAdmin();
        $penguji = $this->buatDospem();
        Pengaturan::simpan('akses.jadwalkan_sidang', 'operator_akademik');
        $payload = ['jadwal_mulai' => $this->waktu(), 'ruang' => 'R1', 'penguji' => [$penguji->id]];

        $this->actingAs($admin)->post(route('operator.sidang.jadwalkan', $sidang), $payload)->assertForbidden();
        $this->actingAs($operator)->post(route('operator.sidang.jadwalkan', $sidang), $payload)->assertSessionHasNoErrors();
        $this->assertSame(StatusSidangTa::Dijadwalkan, $sidang->fresh()->status);

        // Konfirmasi selesai tetap milik Admin karena pengaturannya tidak diubah.
        $this->actingAs($operator)->post(route('operator.sidang.selesai', $sidang))->assertForbidden();
        $this->actingAs($admin)->post(route('operator.sidang.selesai', $sidang))->assertSessionHasNoErrors();
    }

    public function test_halaman_sidang_tampil_untuk_setiap_tahap(): void
    {
        [$mhs, $ta, $a, $b] = $this->taBersk();
        $operator = $this->buatOperator();
        $service = app(SidangService::class);

        $sidang = $service->buat($ta, $mhs->user);
        $this->actingAs($mhs->user)->get(route('sidang.show', $sidang))->assertOk()->assertSee('Transkrip nilai');
        $this->actingAs($mhs->user)->get(route('pengajuan.show', $ta))->assertOk();

        $this->lengkapiDokumen($sidang, $mhs);
        $service->kirim($sidang, $mhs->user);
        $this->actingAs($operator)->get(route('sidang.show', $sidang))->assertOk();
        $service->verifikasi($sidang, $operator);
        $this->actingAs($a->user)->get(route('sidang.show', $sidang))->assertOk();
        $this->actingAs($a->user)->get(route('dospem.dashboard'))->assertOk();
        $this->actingAs($operator)->get(route('operator.dashboard'))->assertOk();
        $this->actingAs($mhs->user)->get(route('mahasiswa.dashboard'))->assertOk();
    }
}
