<?php

use Illuminate\Http\Request;

/*
| Pintu masuk Laravel di Vercel (runtime vercel-php). Dipakai bersama vercel.json; tidak dipakai saat
| pengembangan lokal (lokal tetap lewat public/index.php dan php artisan serve).
|
| Filesystem fungsi Vercel read-only kecuali /tmp, sedangkan Laravel perlu menulis cache, view
| terkompilasi, log, dan berkas unggahan. Semuanya diarahkan ke /tmp. Isi /tmp hanya hidup selama
| instance fungsi berjalan dan tidak dibagi antar-instance.
|
| Nilai bawaan di bawah hanya dipakai bila belum diisi di Environment Variables Vercel (yang diisi
| di sana selalu menang). Satu-satunya yang wajib diisi di Vercel: APP_KEY.
|
| Mode demo (DB_CONNECTION kosong atau sqlite): database/database.sqlite disalin ke /tmp, sesi
| memakai cookie, cache memakai berkas. Perubahan data hilang saat instance berganti, jadi cukup untuk
| demo atau presentasi. Untuk data permanen, isi DB_CONNECTION (mysql/pgsql) beserta kredensialnya
| di Vercel; mode demo otomatis tidak dipakai dan sesi/cache memakai database.
*/

define('LARAVEL_START', microtime(true));

$ambil = static function (string $kunci): ?string {
    foreach ([$_SERVER[$kunci] ?? null, $_ENV[$kunci] ?? null, getenv($kunci)] as $nilai) {
        if (is_string($nilai) && $nilai !== '') {
            return $nilai;
        }
    }

    return null;
};

$bawaan = static function (string $kunci, string $nilai) use ($ambil): void {
    if ($ambil($kunci) === null) {
        putenv("{$kunci}={$nilai}");
        $_ENV[$kunci] = $nilai;
        $_SERVER[$kunci] = $nilai;
    }
};

$tmp = rtrim(sys_get_temp_dir(), '/');
$storage = $tmp.'/storage';

foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $folder) {
    $jalur = "{$storage}/{$folder}";

    if (! is_dir($jalur) && ! mkdir($jalur, 0775, true) && ! is_dir($jalur)) {
        throw new RuntimeException("Tidak dapat membuat folder {$jalur}");
    }
}

$bawaan('VERCEL', '1');
$bawaan('APP_NAME', 'Arsip Berkas KP dan TA');
$bawaan('APP_ENV', 'production');
$bawaan('APP_DEBUG', 'false');
$bawaan('LOG_CHANNEL', 'stderr');
$bawaan('SESSION_SECURE_COOKIE', 'true');
$bawaan('VIEW_COMPILED_PATH', "{$storage}/framework/views");
$bawaan('APP_PACKAGES_CACHE', "{$tmp}/packages.php");
$bawaan('APP_SERVICES_CACHE', "{$tmp}/services.php");

if (($ambil('DB_CONNECTION') ?? 'sqlite') === 'sqlite') {
    $bawaan('SESSION_DRIVER', 'cookie');
    $bawaan('CACHE_STORE', 'file');
    $bawaan('QUEUE_CONNECTION', 'sync');

    if ($ambil('DB_DATABASE') === null) {
        $sumber = __DIR__.'/../database/database.sqlite';
        $tujuan = "{$tmp}/database.sqlite";

        // Kunci supaya request bersamaan pada saat instance baru menyala tidak saling menimpa salinan.
        $kunci = fopen("{$tujuan}.lock", 'c');
        flock($kunci, LOCK_EX);

        if (! is_file($tujuan) && ! copy($sumber, $tujuan)) {
            flock($kunci, LOCK_UN);
            throw new RuntimeException('Tidak dapat menyalin database.sqlite ke '.$tujuan);
        }

        flock($kunci, LOCK_UN);
        fclose($kunci);

        $bawaan('DB_DATABASE', $tujuan);
    }
}

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->useStoragePath($storage);

$app->handleRequest(Request::capture());
