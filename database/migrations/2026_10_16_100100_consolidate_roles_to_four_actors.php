<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyederhanakan 7 role lama menjadi 4 aktor: mahasiswa, dospem, admin,
 * operator_akademik. Penugasan role lama dipindahkan (bukan dihapus begitu saja)
 * sehingga tidak ada pengguna yang kehilangan akses secara tidak sengaja.
 *
 *   dosen_penguji                                  -> dospem
 *   sekdep_koor_prodi, penata, pengelola_layanan,
 *   pengadministrasi_perkantoran                   -> operator_akademik (semua prodi)
 *
 * Operator hasil migrasi diberi `semua_prodi = true` agar cakupan kerjanya sama
 * dengan sebelumnya; Admin dapat mempersempitnya per prodi.
 * Role lain di luar empat aktor dihapus. Migrasi ini tidak dapat dibalik.
 */
return new class extends Migration
{
    private const AKTOR = ['mahasiswa', 'dospem', 'admin', 'operator_akademik'];

    private const PETA = [
        'dosen_penguji' => 'dospem',
        'sekdep_koor_prodi' => 'operator_akademik',
        'penata' => 'operator_akademik',
        'pengelola_layanan' => 'operator_akademik',
        'pengadministrasi_perkantoran' => 'operator_akademik',
    ];

    public function up(): void
    {
        $nama = config('permission.table_names');
        $kolom = config('permission.column_names');
        $morphKey = $kolom['model_morph_key'] ?? 'model_id';
        $now = now();

        foreach (self::AKTOR as $role) {
            if (! DB::table($nama['roles'])->where('name', $role)->where('guard_name', 'web')->exists()) {
                DB::table($nama['roles'])->insert([
                    'name' => $role, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $id = fn (string $role) => DB::table($nama['roles'])->where('name', $role)->where('guard_name', 'web')->value('id');

        foreach (self::PETA as $lama => $baru) {
            $idLama = $id($lama);
            if (! $idLama) {
                continue;
            }
            $idBaru = $id($baru);

            foreach (DB::table($nama['model_has_roles'])->where('role_id', $idLama)->get() as $baris) {
                $sudah = DB::table($nama['model_has_roles'])
                    ->where('role_id', $idBaru)
                    ->where('model_type', $baris->model_type)
                    ->where($morphKey, $baris->{$morphKey})
                    ->exists();

                if (! $sudah) {
                    DB::table($nama['model_has_roles'])->insert([
                        'role_id' => $idBaru, 'model_type' => $baris->model_type, $morphKey => $baris->{$morphKey},
                    ]);
                }

                if ($baru === 'operator_akademik' && $baris->model_type === 'App\\Models\\User') {
                    DB::table('users')->where('id', $baris->{$morphKey})->update(['semua_prodi' => true]);
                }
            }
        }

        // Hapus semua role di luar empat aktor beserta penugasannya.
        $lebih = DB::table($nama['roles'])->whereNotIn('name', self::AKTOR)->pluck('id');
        DB::table($nama['model_has_roles'])->whereIn('role_id', $lebih)->delete();
        DB::table($nama['role_has_permissions'])->whereIn('role_id', $lebih)->delete();
        DB::table($nama['roles'])->whereIn('id', $lebih)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja kosong: penugasan role lama sudah digabung dan tidak dapat dipulihkan otomatis.
    }
};
