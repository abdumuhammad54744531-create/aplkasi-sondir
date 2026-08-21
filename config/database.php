<?php
declare(strict_types=1);

$credentialFile = __DIR__ . '/database.credentials.php';
$credentials = [];
if (is_file($credentialFile)) {
    $loadedCredentials = require $credentialFile;
    if (is_array($loadedCredentials)) {
        $credentials = $loadedCredentials;
    }
}

$setting = static function (string $environment, string $key, string $default) use ($credentials): string {
    $environmentValue = getenv($environment);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }
    $fileValue = $credentials[$key] ?? null;
    return is_string($fileValue) && $fileValue !== '' ? $fileValue : $default;
};

$host = $setting('SONDIR_DB_HOST', 'host', '127.0.0.1');
$port = $setting('SONDIR_DB_PORT', 'port', '3306');
$dbname = $setting('SONDIR_DB_NAME', 'database', 'db_sondir');
$username = $setting('SONDIR_DB_USER', 'username', 'root');
$password = $setting('SONDIR_DB_PASS', 'password', '');
$charset = $setting('SONDIR_DB_CHARSET', 'charset', 'utf8mb4');

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('[SONDIR DB] ' . $e->getMessage());
    http_response_code(503);
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocalRequest = PHP_SAPI === 'cli'
        || $requestHost === 'localhost'
        || str_starts_with($requestHost, '127.0.0.1')
        || str_contains($requestHost, '.test');
    exit($isLocalRequest
        ? 'Koneksi database lokal gagal. Pastikan MySQL Laragon aktif dan database db_sondir sudah diimpor.'
        : 'Koneksi database sementara bermasalah. Silakan hubungi administrator.');
}
