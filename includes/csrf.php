<?php
declare(strict_types=1);
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $token=$_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if($token && hash_equals((string)($_SESSION['csrf']??''),(string)$token))return;

    unset($_SESSION['csrf']);
    csrf_token();
    http_response_code(419);
    $expectsJson=str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json')
        ||isset($_SERVER['HTTP_X_CSRF_TOKEN']);
    if($expectsJson){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message'=>'Sesi formulir berubah. Muat ulang halaman lalu coba kembali.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['flash']=['type'=>'warning','message'=>'Sesi formulir telah diperbarui. Silakan periksa kembali lalu tekan Simpan.'];
    $target=(string)($_SERVER['REQUEST_URI']??url('dashboard.php'));
    if(!str_starts_with($target,'/'))$target=url('dashboard.php');
    header('Location: '.$target, true, 303);
    exit;
}
