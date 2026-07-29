<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Makassar');

define('APP_NAME', 'Sistem Informasi Pengujian Sondir');
define('APP_OWNER_NAME', 'MUHAMMAD ABDU, S.T., M.T');
define('APP_OWNER_UNIVERSITY', 'UNIVERSITAS MUHAMMADIYAH BUTON');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('SESSION_TIMEOUT', 1800);
define('MAX_LOGIN_ATTEMPTS', 5);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = str_contains(strtolower($scriptName), '/sondir/') ? '/Sondir' : '';
define('BASE_URL', rtrim($basePath, '/'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SONDIRSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Sesi berakhir. Silakan masuk kembali.'];
}
$_SESSION['last_activity'] = time();
