<?php $pageTitle=$pageTitle??APP_NAME; ?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($pageTitle)?> · <?=e(APP_NAME)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?=url('assets/css/style.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/style.css')?>" rel="stylesheet">
<link href="<?=url('assets/css/owner.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/owner.css')?>" rel="stylesheet">
</head><body>
<?php if(!empty($_SESSION['user'])): ?>
<?php require APP_ROOT.'/includes/sidebar.php'; ?>
<main class="app-main"><nav class="navbar bg-white border-bottom sticky-top px-3">
<button class="btn btn-light d-lg-none" id="sidebarToggle" aria-label="Buka menu"><i class="bi bi-list"></i></button>
<div><div class="fw-semibold"><?=e($pageTitle)?></div><small class="text-secondary"><?=tanggal_id(date('Y-m-d'))?></small></div>
<div class="page-owner-identity"><strong><?=e(APP_OWNER_NAME)?></strong><small><?=e(APP_OWNER_UNIVERSITY)?></small></div>
<div class="dropdown"><button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?=e($_SESSION['user']['nama_lengkap'])?></button>
<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?=url('auth/profile.php')?>">Profil</a></li><li><a class="dropdown-item" href="<?=url('auth/change-password.php')?>">Ganti password</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?=url('logout.php')?>">Keluar</a></li></ul></div>
</nav><div class="container-fluid p-3 p-lg-4">
<?php if($f=$_SESSION['flash']??null): unset($_SESSION['flash']); ?><div data-flash="<?=e($f['type'])?>" data-message="<?=e($f['message'])?>"></div><?php endif; ?>
<?php else: ?><main><?php endif; ?>
