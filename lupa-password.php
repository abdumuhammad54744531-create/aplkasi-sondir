<?php
require __DIR__.'/config/bootstrap.php';
require_once APP_ROOT.'/includes/password_reset.php';
if(!empty($_SESSION['user']))redirect('dashboard.php');

password_reset_ensure($pdo);
$success='';$error='';$localResetUrl='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $identity=trim((string)($_POST['identity']??''));
    $lastRequest=(int)($_SESSION['password_reset_last_request']??0);
    if($identity===''||strlen($identity)>150){
        $error='Masukkan username atau email yang valid.';
    }elseif(time()-$lastRequest<60){
        $error='Tunggu satu menit sebelum meminta tautan reset kembali.';
    }else{
        $_SESSION['password_reset_last_request']=time();
        $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??''));
        $rate=$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE request_ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');
        $rate->execute([$ipHash]);
        if((int)$rate->fetchColumn()<5){
            $q=$pdo->prepare("SELECT id,nama_lengkap,email FROM users WHERE status='aktif' AND (username=? OR email=?) LIMIT 1");
            $q->execute([$identity,$identity]);$user=$q->fetch();
            if($user&&filter_var($user['email'],FILTER_VALIDATE_EMAIL)){
                $token=bin2hex(random_bytes(32));
                $tokenHash=hash('sha256',$token);
                $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
                $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,request_ip_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$user['id'],$tokenHash,$ipHash]);
                $resetUrl=password_reset_absolute_url('reset-password.php?token='.rawurlencode($token));
                $sent=password_reset_send_email((string)$user['email'],(string)$user['nama_lengkap'],$resetUrl);
                if(!$sent)error_log('[SONDIR PASSWORD RESET] Email gagal dikirim untuk user_id='.(int)$user['id'].' URL='.$resetUrl);
                if(password_reset_is_local())$localResetUrl=$resetUrl;
            }
        }
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY) OR used_at<DATE_SUB(NOW(),INTERVAL 1 DAY)')->execute();
        $success='Jika akun ditemukan dan memiliki email aktif, petunjuk reset password telah dikirim. Periksa juga folder spam.';
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lupa Password · <?=e(APP_NAME)?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=url('assets/css/style.css')?>" rel="stylesheet"></head>
<body class="login-page"><div class="login-shell w-100" style="max-width:560px"><div class="login-owner-identity text-center text-white mb-3"><strong class="d-block"><?=e(APP_OWNER_NAME)?></strong><span class="d-block small text-white-50"><?=e(APP_OWNER_UNIVERSITY)?></span></div><div class="login-card"><div class="login-form"><div class="stat-icon mb-3"><i class="bi bi-key"></i></div><h2 class="fw-bold">Lupa password</h2><p class="text-secondary">Masukkan username atau email akun internal. Tautan reset berlaku selama 30 menit.</p>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<?php if($localResetUrl):?><div class="alert alert-warning"><strong>Mode lokal:</strong> email tidak diperlukan saat pengujian Laragon.<div class="mt-2"><a class="btn btn-sm btn-warning" href="<?=e($localResetUrl)?>">Buka tautan reset</a></div></div><?php endif;?>
<form method="post"><?=csrf_field()?><div class="mb-3"><label class="form-label required" for="reset-identity">Username atau email</label><input class="form-control form-control-lg" id="reset-identity" name="identity" maxlength="150" autocomplete="username" required autofocus></div><button class="btn btn-primary btn-lg w-100"><i class="bi bi-envelope me-1"></i> Kirim tautan reset</button></form><a class="btn btn-link w-100 mt-3" href="<?=url('login.php')?>"><i class="bi bi-arrow-left me-1"></i>Kembali ke halaman masuk</a></div></div></div></body></html>
