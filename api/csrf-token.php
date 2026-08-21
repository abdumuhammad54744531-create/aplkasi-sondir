<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(['csrf_token'=>csrf_token()],JSON_UNESCAPED_SLASHES);
