<?php
require __DIR__.'/config/bootstrap.php';
require_once APP_ROOT.'/includes/password_reset.php';
if(!empty($_SESSION['user']))redirect('dashboard.php');

password_reset_ensure($pdo);
$token=trim((string)($_POST['token']??$_GET['token']??''));
$validFormat=(bool)preg_match('/^[a-f0-9]{64}$/',$token);
$reset=null;$error='';$success=false;
if($validFormat){
    $q=$pdo->prepare("SELECT pr.id,pr.user_id,u.username FROM password_reset_tokens pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>=NOW() AND u.status='aktif' LIMIT 1");
    $q->execute([hash('sha256',$token)]);$reset=$q->fetch();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $password=(string)($_POST['password']??'');$confirmation=(string)($_POST['confirmation']??'');
    if(!$reset){$error='Tautan reset tidak valid atau sudah kedaluwarsa.';}
    elseif(strlen($password)<8){$error='Password baru minimal 8 karakter.';}
    elseif(strlen($password)>255){$error='Password baru terlalu panjang.';}
    elseif($password!==$confirmation){$error='Konfirmasi password tidak sama.';}
    else{
        $pdo->beginTransaction();
        try{
            $lock=$pdo->prepare('SELECT id,user_id FROM password_reset_tokens WHERE id=? AND used_at IS NULL AND expires_at>=NOW() FOR UPDATE');
            $lock->execute([$reset['id']]);$active=$lock->fetch();
            if(!$active)throw new RuntimeException('Token tidak aktif.');
            $pdo->prepare('UPDATE users SET password=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$active['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$active['user_id']]);
            $pdo->commit();
            try{audit($pdo,'Mereset password melalui tautan pemulihan','users',(int)$active['user_id'],null,['password'=>'[DIUBAH]']);}catch(Throwable $ignored){}
            $success=true;$reset=null;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('[SONDIR PASSWORD RESET] '.$e->getMessage());
            $error='Password belum dapat diubah. Muat ulang halaman dan coba kembali.';
        }
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password · <?=e(APP_NAME)?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=url('assets/css/style.css')?>" rel="stylesheet"></head>
<body class="login-page"><div class="login-shell w-100" style="max-width:560px"><div class="login-owner-identity text-center text-white mb-3"><strong class="d-block"><?=e(APP_OWNER_NAME)?></strong><span class="d-block small text-white-50"><?=e(APP_OWNER_UNIVERSITY)?></span></div><div class="login-card"><div class="login-form"><div class="stat-icon mb-3"><i class="bi bi-shield-lock"></i></div><h2 class="fw-bold">Buat password baru</h2>
<?php if($success):?><div class="alert alert-success">Password berhasil diubah. Semua tautan reset lama sudah dinonaktifkan.</div><a class="btn btn-primary btn-lg w-100" href="<?=url('login.php')?>">Masuk sekarang</a><?php elseif(!$reset):?><div class="alert alert-danger">Tautan reset tidak valid, sudah digunakan, atau kedaluwarsa.</div><a class="btn btn-outline-primary w-100" href="<?=url('lupa-password.php')?>">Minta tautan baru</a><?php else:?><p class="text-secondary">Akun: <strong><?=e($reset['username'])?></strong></p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><div class="mb-3"><label class="form-label required" for="reset-password">Password baru</label><input class="form-control form-control-lg" id="reset-password" type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required autofocus></div><div class="mb-3"><label class="form-label required" for="reset-confirmation">Ulangi password baru</label><input class="form-control form-control-lg" id="reset-confirmation" type="password" name="confirmation" minlength="8" maxlength="255" autocomplete="new-password" required></div><button class="btn btn-primary btn-lg w-100">Simpan password baru</button></form><?php endif;?><a class="btn btn-link w-100 mt-3" href="<?=url('login.php')?>"><i class="bi bi-arrow-left me-1"></i>Kembali ke halaman masuk</a></div></div></div></body></html>
