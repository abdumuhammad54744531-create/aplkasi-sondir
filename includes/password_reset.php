<?php
declare(strict_types=1);

function password_reset_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        request_ip_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_reset_user (user_id),
        INDEX idx_password_reset_expires (expires_at),
        INDEX idx_password_reset_ip_created (request_ip_hash, created_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function password_reset_is_local(): bool
{
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $host=preg_replace('/:\d+$/','',$host)??$host;
    return $host==='localhost'||$host==='127.0.0.1'||$host==='::1'||str_ends_with($host,'.test');
}

function password_reset_absolute_url(string $path): string
{
    $scheme=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off'?'https':'http';
    $host=(string)($_SERVER['HTTP_HOST']??'localhost');
    if(!preg_match('/^[a-z0-9.\-:\[\]]+$/i',$host))$host='localhost';
    return $scheme.'://'.$host.url($path);
}

function password_reset_send_email(string $email,string $name,string $resetUrl): bool
{
    $subject='Reset password '.APP_NAME;
    $message="Halo {$name},\n\n".
        "Kami menerima permintaan untuk mengatur ulang password akun Anda.\n".
        "Buka tautan berikut dalam 30 menit:\n{$resetUrl}\n\n".
        "Jika Anda tidak meminta reset password, abaikan email ini.\n";

    $host=strtolower((string)($_SERVER['HTTP_HOST']??'localhost'));
    $host=preg_replace('/:\d+$/','',$host)??$host;
    if(!preg_match('/^[a-z0-9.-]+$/',$host))$host='localhost';
    $from=getenv('SONDIR_MAIL_FROM')?:'no-reply@'.$host;
    $headers=[
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: '.APP_NAME.' <'.$from.'>',
    ];

    return function_exists('mail')&&@mail($email,$subject,$message,implode("\r\n",$headers));
}
