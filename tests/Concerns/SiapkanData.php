<?php

namespace Tests\Concerns;

use App\Enums\JenisPengajuan;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;
use Database\Seeders\PersyaratanSeeder;
use Database\Seeders\ProgramStudiSeeder;
use Illuminate\Http\UploadedFile;

trait SiapkanData
{
    /** Mengisi program studi dan persyaratan contoh dari seeder. */
    protected function siapkanMaster(): void
    {
        $this->seed([ProgramStudiSeeder::class, PersyaratanSeeder::class]);
    }

    protected function pdf(string $nama = 'dokumen.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'application/pdf');
    }

    protected function buatPengajuan(
        User $mahasiswa,
        ?User $dosen = null,
        StatusPengajuan $status = StatusPengajuan::Draft,
        JenisPengajuan $jenis = JenisPengajuan::Kp,
    ): Pengajuan {
        return Pengajuan::create([
            'mahasiswa_id' => $mahasiswa->id,
            'pembimbing_id' => $dosen?->id,
            'jenis' => $jenis,
            'judul' => 'Sistem Informasi Contoh',
            'instansi' => 'PT Contoh Nusantara',
            'status' => $status,
        ]);
    }
}
