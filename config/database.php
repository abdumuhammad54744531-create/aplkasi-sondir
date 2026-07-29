<?php
declare(strict_types=1);

$host = getenv('SONDIR_DB_HOST') ?: '127.0.0.1';
$port = getenv('SONDIR_DB_PORT') ?: '3306';
$dbname = getenv('SONDIR_DB_NAME') ?: 'db_sondir';
$username = getenv('SONDIR_DB_USER') ?: 'root';
$password = getenv('SONDIR_DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('[SONDIR DB] ' . $e->getMessage());
    http_response_code(503);
    exit('Koneksi database gagal. Pastikan MySQL Laragon aktif dan database db_sondir sudah diimpor.');
}

