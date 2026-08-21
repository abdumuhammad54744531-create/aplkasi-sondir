<?php
require __DIR__.'/config/bootstrap.php';
if(!empty($_SESSION['user'])) redirect('dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $username=trim($_POST['username']??''); $password=$_POST['password']??'';
    $key='login_'.hash('sha256',($_SERVER['REMOTE_ADDR']??'').$username);
    $attempt=$_SESSION[$key]??['count'=>0,'at'=>0];
    $q=$pdo->prepare("SELECT * FROM users WHERE username=? AND status='aktif' LIMIT 1");$q->execute([$username]);$user=$q->fetch();
    $ok=$user&&password_verify($password,$user['password']);
    if(!$ok&&$attempt['count']>=MAX_LOGIN_ATTEMPTS && time()-$attempt['at']<900){$error='Terlalu banyak percobaan. Coba lagi dalam 15 menit.';}
    else{
        $log=$pdo->prepare('INSERT INTO login_log(user_id,username,status,alamat_ip,user_agent) VALUES(?,?,?,?,?)');
        $log->execute([$user['id']??null,$username,$ok?'berhasil':'gagal',$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,1000)]);
        if($ok){session_regenerate_id(true);$_SESSION['user']=array_intersect_key($user,array_flip(['id','nama_lengkap','username','email','level','foto']));$_SESSION[$key]=['count'=>0,'at'=>time()];$pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);redirect('dashboard.php');}
        $_SESSION[$key]=['count'=>$attempt['count']+1,'at'=>time()];$error='Username atau password salah.';
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk · <?=e(APP_NAME)?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=url('assets/css/style.css')?>" rel="stylesheet"></head>
<body class="login-page">
<div class="login-shell w-100" style="max-width:920px">
<div class="login-owner-identity text-center text-white mb-3"><strong class="d-block"><?=e(APP_OWNER_NAME)?></strong><span class="d-block small text-white-50"><?=e(APP_OWNER_UNIVERSITY)?></span></div>
<div class="login-card"><div class="row g-0"><div class="col-lg-6 login-side d-none d-lg-flex flex-column justify-content-between"><div><span class="badge rounded-pill bg-white text-primary mb-4">Laboratorium Teknik Sipil</span><h1 class="display-5 fw-bold">Data sondir yang rapi, hasil yang dapat dipercaya.</h1><p class="text-white-50 mt-3">Kelola proyek, pengujian, pemeriksaan, grafik, dan laporan dalam satu sistem.</p></div><small>Zona waktu Asia/Makassar · Sistem internal laboratorium</small></div><div class="col-lg-6 login-form"><div class="mb-4"><div class="stat-icon mb-3"><i class="bi bi-cone-striped"></i></div><h2 class="fw-bold">Selamat datang</h2><p class="text-secondary">Masuk untuk melanjutkan pekerjaan.</p></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="mb-3"><label class="form-label required">Username</label><input class="form-control form-control-lg" name="username" autocomplete="username" required autofocus></div><div class="mb-3"><label class="form-label required">Password</label><input type="password" class="form-control form-control-lg" name="password" autocomplete="current-password" required></div><button class="btn btn-primary btn-lg w-100">Masuk <i class="bi bi-arrow-right ms-1"></i></button></form><a class="btn btn-outline-primary w-100 mt-3" href="<?=url('permohonan/index.php')?>"><i class="bi bi-send me-1"></i>Ajukan permohonan sondir</a><p class="small text-secondary mt-4 mb-0">Akun awal: admin / admin123. Segera ganti password setelah masuk.</p></div></div></div>
</div>
</body></html>
