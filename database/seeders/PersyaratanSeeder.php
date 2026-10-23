<?php

namespace Database\Seeders;

use App\Enums\JenisPengajuan;
use App\Enums\Role;
use App\Models\Persyaratan;
use Illuminate\Database\Seeder;

class PersyaratanSeeder extends Seeder
{
    /**
     * Contoh persyaratan dokumen. Sesuaikan dengan ketentuan fakultas lewat
     * menu Admin > Pengaturan Persyaratan; mahasiswa akan langsung melihat perubahannya.
     */
    public function run(): void
    {
        $semua = [
            // Kerja Praktik
            [JenisPengajuan::Kp, 'Surat Permohonan KP', 'Surat permohonan KP bertanda tangan.', Role::Operator, true, 1, 'pdf'],
            [JenisPengajuan::Kp, 'Transkrip Nilai Sementara', 'Transkrip nilai terbaru dari sistem akademik.', Role::Operator, true, 2, 'pdf'],
            [JenisPengajuan::Kp, 'KRS Semester Berjalan', 'Kartu Rencana Studi semester berjalan.', Role::Operator, true, 3, 'pdf'],
            [JenisPengajuan::Kp, 'Surat Penerimaan dari Instansi', 'Surat balasan atau persetujuan dari instansi tujuan KP.', Role::Operator, true, 4, 'pdf'],
            [JenisPengajuan::Kp, 'Proposal Kerja Praktik', 'Proposal KP yang akan diperiksa dosen pembimbing.', Role::Dosen, true, 5, 'pdf,doc,docx'],

            // Tugas Akhir
            [JenisPengajuan::Ta, 'Transkrip Nilai', 'Transkrip nilai terbaru dari sistem akademik.', Role::Operator, true, 1, 'pdf'],
            [JenisPengajuan::Ta, 'KRS Semester Berjalan', 'Kartu Rencana Studi semester berjalan.', Role::Operator, true, 2, 'pdf'],
            [JenisPengajuan::Ta, 'Bukti Pembayaran UKT', 'Bukti pembayaran UKT semester berjalan.', Role::Operator, true, 3, 'pdf,jpg,jpeg,png'],
            [JenisPengajuan::Ta, 'Proposal Tugas Akhir', 'Proposal TA yang akan diperiksa dosen pembimbing.', Role::Dosen, true, 4, 'pdf,doc,docx'],
            [JenisPengajuan::Ta, 'Lembar Persetujuan Topik', 'Opsional. Lembar persetujuan topik dari calon pembimbing.', Role::Dosen, false, 5, 'pdf'],
        ];

        foreach ($semua as [$jenis, $nama, $deskripsi, $pemeriksa, $wajib, $urutan, $ekstensi]) {
            Persyaratan::firstOrCreate(
                ['jenis_pengajuan' => $jenis->value, 'nama' => $nama],
                [
                    'deskripsi' => $deskripsi,
                    'pemeriksa' => $pemeriksa->value,
                    'wajib' => $wajib,
                    'urutan' => $urutan,
                    'ukuran_maks_kb' => 5120,
                    'ekstensi' => $ekstensi,
                    'aktif' => true,
                ],
            );
        }
    }
}
