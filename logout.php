<?php
require __DIR__.'/config/app.php';
session_unset(); session_destroy(); session_start();
$_SESSION['flash']=['type'=>'success','message'=>'Anda berhasil keluar.'];
header('Location: '.BASE_URL.'/login.php'); exit;
