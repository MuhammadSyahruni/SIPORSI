<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SIPORSI Application Configuration
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'SIPORSI');
define('APP_ENV', 'development');
define('BASE_URL', '/siporsi/public');
define('APP_ROOT', dirname(__DIR__, 2));

date_default_timezone_set('Asia/Makassar');

/*
|--------------------------------------------------------------------------
| Session Security Configuration
|--------------------------------------------------------------------------
*/

// Pastikan konfigurasi dilakukan sebelum session dimulai.
if (session_status() === PHP_SESSION_NONE) {

    // Gunakan cookie saja untuk menyimpan session ID.
    ini_set('session.use_only_cookies', '1');

    // Tolak session ID yang tidak dibuat oleh server.
    ini_set('session.use_strict_mode', '1');

    // Cegah session ID dikirim melalui URL.
    ini_set('session.use_trans_sid', '0');

    // Deteksi HTTPS untuk lingkungan lokal maupun hosting.
    $isSecure = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    // Atur keamanan cookie session.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
