<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\PengajuanTa;
use App\Models\Sidang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin','mahasiswa','dosen_penguji','sekdep_koor_prodi',
            'penata','pengelola_layanan','pengadministrasi_perkantoran',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@ta.test'],
            ['name' => 'Administrator', 'password' => Hash::make('password')]
        );
        $admin->syncRoles(['admin']);

        $studentUser = User::firstOrCreate(
            ['email' => 'mahasiswa@ta.test'],
            ['name' => 'Mahasiswa Demo', 'password' => Hash::make('password')]
        );
        $studentUser->syncRoles(['mahasiswa']);

        $student = Mahasiswa::firstOrCreate(
            ['user_id' => $studentUser->id],
            ['nim'=>'09010000000001','nama'=>'Mahasiswa Demo','prodi'=>'Teknik Informatika','angkatan'=>'2024']
        );

        $dosenUser = User::firstOrCreate(
            ['email' => 'dosen@ta.test'],
            ['name' => 'Dosen Penguji Demo', 'password' => Hash::make('password')]
        );
        $dosenUser->syncRoles(['dosen_penguji']);

        $dosen = Dosen::firstOrCreate(
            ['user_id' => $dosenUser->id],
            ['nip'=>'19800101202601001','nama'=>'Dosen Penguji Demo','prodi'=>'Teknik Informatika']
        );

        foreach ([
            ['sekdep@ta.test','SekDep/Koor Prodi Demo','sekdep_koor_prodi'],
            ['penata@ta.test','Penata Demo','penata'],
            ['pengelola@ta.test','Pengelola Layanan Demo','pengelola_layanan'],
            ['administrasi@ta.test','Pengadministrasi Perkantoran Demo','pengadministrasi_perkantoran'],
        ] as [$email,$name,$role]) {
            $u = User::firstOrCreate(['email'=>$email], ['name'=>$name,'password'=>Hash::make('password')]);
            $u->syncRoles([$role]);
        }

        $pengajuan = PengajuanTa::firstOrCreate(
            ['mahasiswa_id'=>$student->id],
            [
                'jenis_ujian'=>'komprehensif',
                'judul_ta'=>'Prototype Sistem Pendaftaran Ujian Akhir Program',
                'nilai_usep'=>80, 'nilai_dkn'=>82,
                'status'=>'diajukan', 'tanggal_pengajuan'=>now()->toDateString()
            ]
        );

        $sidang = Sidang::firstOrCreate(
            ['pengajuan_ta_id'=>$pengajuan->id],
            ['status'=>'belum_dijadwalkan']
        );

        $sidang->pengujis()->firstOrCreate(
            ['dosen_id'=>$dosen->id],
            ['peran'=>'anggota']
        );
    }
}
