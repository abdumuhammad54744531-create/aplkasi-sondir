<?php
require __DIR__.'/config/app.php';
header('Location: '.BASE_URL.'/'.(!empty($_SESSION['user'])?'dashboard.php':'login.php'));
exit;

