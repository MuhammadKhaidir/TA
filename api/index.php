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

/*
| Berkas statis (aset Vite di public/build, gambar, css). Berkas hasil `composer vercel` ada di bundel
| fungsi tetapi tidak otomatis disajikan CDN Vercel, jadi disajikan di sini sebelum Laravel menyala.
| Hanya berkas di dalam public/ dengan ekstensi yang diizinkan; berkas .php dan dotfile tidak pernah.
*/
(static function (): void {
    $metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (! in_array($metode, ['GET', 'HEAD'], true)) {
        return;
    }

    $jalur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (! is_string($jalur) || $jalur === '/') {
        return;
    }

    $jalur = rawurldecode($jalur);

    if (str_contains($jalur, "\0") || str_contains($jalur, '..') || preg_match('#(^|/)\.#', $jalur) === 1) {
        return;
    }

    $tipe = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'map' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'txt' => 'text/plain; charset=utf-8',
    ];

    $ekstensi = strtolower(pathinfo($jalur, PATHINFO_EXTENSION));

    if (! isset($tipe[$ekstensi])) {
        return;
    }

    $akar = realpath(__DIR__.'/../public');
    $berkas = $akar === false ? false : realpath($akar.$jalur);

    if ($berkas === false || ! str_starts_with($berkas, $akar.DIRECTORY_SEPARATOR) || ! is_file($berkas)) {
        return;
    }

    // Nama berkas di build/assets memuat hash isinya, jadi aman di-cache selamanya.
    $abadi = str_starts_with($jalur, '/build/assets/');
    $umur = $abadi ? 31536000 : 3600;

    header('Content-Type: '.$tipe[$ekstensi]);
    header('X-Content-Type-Options: nosniff');
    header("Cache-Control: public, max-age={$umur}, s-maxage={$umur}".($abadi ? ', immutable' : ''));
    header('Content-Length: '.filesize($berkas));

    if ($metode === 'GET') {
        readfile($berkas);
    }

    exit;
})();

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
