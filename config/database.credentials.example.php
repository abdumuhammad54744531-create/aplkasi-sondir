<?php
declare(strict_types=1);

// Salin menjadi database.credentials.php hanya pada server tujuan.
// File database.credentials.php diabaikan Git agar rahasia tidak ikut repository.
return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'nama_database_hosting',
    'username' => 'nama_user_database_hosting',
    'password' => 'password_database_hosting',
    'charset' => 'utf8mb4',
];
