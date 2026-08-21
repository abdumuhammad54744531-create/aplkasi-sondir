<aside class="sidebar" id="sidebar">
<div class="brand"><span class="brand-mark"><i class="bi bi-cone-striped"></i></span><span><strong>SONDIR</strong><small>Laboratorium Teknik Sipil</small></span></div>
<nav class="nav flex-column">
<a href="<?=url('dashboard.php')?>"><i class="bi bi-grid"></i>Dashboard</a>
<?php $userScript=$_SERVER['SCRIPT_NAME']??'';$userMenuOpen=str_contains($userScript,'/users/'); ?>
<?php if(can(['super_admin','admin_lab'])):?><details class="sidebar-menu-group" <?=$userMenuOpen?'open':''?>>
<summary><i class="bi bi-people"></i><span>Pengguna</span><i class="bi bi-chevron-down sidebar-menu-chevron"></i></summary>
<div class="sidebar-submenu">
<?php if(can('super_admin')):?><a class="<?=str_ends_with($userScript,'/users/index.php')?'active':''?>" href="<?=url('users/index.php')?>"><i class="bi bi-person-badge"></i>Pengguna Internal</a><?php endif;?>
<a class="<?=str_ends_with($userScript,'/users/pemohon.php')?'active':''?>" href="<?=url('users/pemohon.php')?>"><i class="bi bi-person-vcard"></i>Akun Pemohon</a>
</div></details><?php endif;?>
<?php if(can('super_admin')):?><a href="<?=url('laboratorium/index.php')?>"><i class="bi bi-building"></i>Laboratorium</a><?php endif;?>
<a href="<?=url('klien/index.php')?>"><i class="bi bi-person-vcard"></i>Klien</a>
<a href="<?=url('proyek/index.php')?>"><i class="bi bi-briefcase"></i>Proyek</a>
<a href="<?=url('alat/index.php')?>"><i class="bi bi-tools"></i>Alat & Kalibrasi</a>
<a href="<?=url('titik-sondir/index.php')?>"><i class="bi bi-geo-alt"></i>Titik Sondir</a>
<a href="<?=url('dokumentasi/index.php')?>"><i class="bi bi-camera"></i>Dokumentasi Titik</a>
<?php if(can(['super_admin','admin_lab'])):?><a href="<?=url('permohonan/admin.php')?>"><i class="bi bi-inbox"></i>Permohonan</a><?php endif;?>
<a href="<?=url('pengujian/index.php')?>"><i class="bi bi-table"></i>Pengujian</a>
<?php $foundationScript=$_SERVER['SCRIPT_NAME']??'';$foundationMenuOpen=str_contains($foundationScript,'/daya-dukung/'); ?>
<details class="sidebar-menu-group" <?=$foundationMenuOpen?'open':''?>>
<summary><i class="bi bi-building-gear"></i><span>Daya Dukung Pondasi</span><i class="bi bi-chevron-down sidebar-menu-chevron"></i></summary>
<div class="sidebar-submenu">
<a class="<?=str_ends_with($foundationScript,'/tiang.php')?'active':''?>" href="<?=url('daya-dukung/tiang.php')?>"><i class="bi bi-columns-gap"></i>Pondasi Tiang</a>
<a class="<?=str_ends_with($foundationScript,'/dangkal.php')?'active':''?>" href="<?=url('daya-dukung/dangkal.php')?>"><i class="bi bi-bounding-box"></i>Pondasi Dangkal</a>
</div>
</details>
<?php $reportScript=$_SERVER['SCRIPT_NAME']??'';$reportMenuOpen=str_contains($reportScript,'/laporan/')||str_ends_with($reportScript,'/pengaturan/laporan.php'); ?>
<details class="sidebar-menu-group" <?=$reportMenuOpen?'open':''?>>
<summary><i class="bi bi-file-earmark-pdf"></i><span>Laporan</span><i class="bi bi-chevron-down sidebar-menu-chevron"></i></summary>
<div class="sidebar-submenu">
<a class="<?=str_ends_with($reportScript,'/laporan/index.php')?'active':''?>" href="<?=url('laporan/index.php')?>"><i class="bi bi-journals"></i>Daftar Laporan</a>
<?php if(can(['super_admin','pemeriksa'])):?><a class="<?=str_ends_with($reportScript,'/laporan/pengesahan.php')?'active':''?>" href="<?=url('laporan/pengesahan.php')?>"><i class="bi bi-patch-check"></i>Pengesahan</a><?php endif;?>
<?php if(can(['super_admin','pemeriksa'])):?><a class="<?=str_ends_with($reportScript,'/pengaturan/laporan.php')?'active':''?>" href="<?=url('pengaturan/laporan.php')?>"><i class="bi bi-sliders"></i>Pengaturan Laporan</a><?php endif;?>
</div>
</details>
<?php if(can(['super_admin','pemeriksa'])):?><a href="<?=url('pengaturan/rumus.php')?>"><i class="bi bi-calculator"></i>Rumus & Pengaturan</a><?php endif;?>
<?php if(can(['super_admin','pemeriksa'])):?><a href="<?=url('pengaturan/referensi.php')?>"><i class="bi bi-journal-bookmark"></i>Metode & Referensi</a><?php endif;?>
<?php if(can('super_admin')):?><a href="<?=url('pengaturan/audit-log.php')?>"><i class="bi bi-clock-history"></i>Audit Log</a><a href="<?=url('pengaturan/backup.php')?>"><i class="bi bi-database-down"></i>Backup</a><?php endif;?>
</nav><div class="sidebar-user"><small>Masuk sebagai</small><strong><?=e(ucwords(str_replace('_',' ',$_SESSION['user']['level'])))?></strong></div>
</aside>
