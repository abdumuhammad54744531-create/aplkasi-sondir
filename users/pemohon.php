<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','admin_lab']);
require APP_ROOT.'/permohonan/_common.php';
permohonan_ensure($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'status');

    if($action==='reset_password'){
        $password=(string)($_POST['password_baru']??'');
        if(strlen($password)<8){
            flash('error','Password baru minimal 8 karakter.');
            redirect('users/pemohon.php');
        }
        $q=$pdo->prepare('UPDATE akun_pemohon SET password=?,updated_at=NOW() WHERE id=?');
        $q->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        audit($pdo,'Mereset password akun pemohon','akun_pemohon',$id,null,['password'=>'[DIUBAH]']);
        flash('success','Password akun pemohon berhasil diubah. Berikan password baru tersebut langsung kepada pemohon.');
    }else{
        $status=($_POST['status']??'')==='aktif'?'aktif':'diblokir';
        $q=$pdo->prepare('UPDATE akun_pemohon SET status=?,updated_at=NOW() WHERE id=?');
        $q->execute([$status,$id]);
        audit($pdo,'Mengubah status akun pemohon','akun_pemohon',$id,null,['status'=>$status]);
        flash('success',$status==='aktif'?'Akun pemohon diaktifkan.':'Akun pemohon diblokir.');
    }
    redirect('users/pemohon.php');
}

$search=trim((string)($_GET['q']??''));
$sql="SELECT a.*,
      COUNT(r.id) jumlah_permohonan,
      SUM(r.status='diajukan') jumlah_diajukan,
      SUM(r.status='diterima') jumlah_diterima
      FROM akun_pemohon a LEFT JOIN permohonan r ON r.pemohon_id=a.id";
$args=[];
if($search!==''){
    $sql.=" WHERE a.nama_lengkap LIKE ? OR a.username LIKE ? OR a.email LIKE ?";
    $args=array_fill(0,3,'%'.$search.'%');
}
$sql.=" GROUP BY a.id ORDER BY a.id DESC";
$q=$pdo->prepare($sql);
$q->execute($args);
$rows=$q->fetchAll();
$pageTitle='Akun Pemohon';
require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading">
 <div><span class="eyebrow">Pengguna Eksternal</span><h2>Akun Pemohon</h2><p>Kelola akun masyarakat atau klien yang mendaftar melalui Portal Permohonan Sondir.</p></div>
 <a target="_blank" class="btn btn-outline-primary" href="<?=url('permohonan/index.php')?>"><i class="bi bi-box-arrow-up-right me-1"></i>Buka portal pemohon</a>
</div>
<div class="card data-card">
 <div class="toolbar">
  <form class="d-flex gap-2 w-100">
   <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Cari nama, username, atau email"></div>
   <button class="btn btn-outline-primary">Cari</button>
   <?php if($search):?><a class="btn btn-light" href="pemohon.php">Reset</a><?php endif;?>
  </form>
 </div>
 <div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Nama Pemohon</th><th>Username</th><th>Password</th><th>Kontak</th><th>Permohonan</th><th>Status</th><th>Terdaftar</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $row):?>
   <tr>
    <td><b><?=e($row['nama_lengkap'])?></b></td>
    <td><code><?=e($row['username'])?></code></td>
    <td><span class="text-secondary">••••••••</span><small class="d-block text-secondary">Tersimpan terenkripsi</small></td>
    <td><?=e($row['email'])?><small class="d-block text-secondary"><?=e($row['whatsapp']?:'-')?></small></td>
    <td><b><?=(int)$row['jumlah_permohonan']?></b> total<small class="d-block text-secondary"><?=(int)$row['jumlah_diajukan']?> diajukan · <?=(int)$row['jumlah_diterima']?> diterima</small></td>
    <td><span class="badge text-bg-<?=$row['status']==='aktif'?'success':'danger'?>"><?=e(ucwords($row['status']))?></span></td>
    <td><?=e(tanggal_id($row['created_at'],true))?></td>
    <td class="text-end"><div class="d-flex justify-content-end gap-1">
     <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resetPassword<?=$row['id']?>"><i class="bi bi-key me-1"></i>Reset Password</button>
     <form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?=$row['status']==='aktif'?'diblokir':'aktif'?>"><button class="btn btn-sm btn-outline-<?=$row['status']==='aktif'?'danger':'success'?>"><i class="bi bi-<?=$row['status']==='aktif'?'person-lock':'person-check'?> me-1"></i><?=$row['status']==='aktif'?'Blokir':'Aktifkan'?></button></form>
    </div></td>
   </tr>
  <?php endforeach;?>
  <?php if(!$rows):?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-vcard"></i><strong>Belum ada akun pemohon</strong><span>Akun yang dibuat melalui portal akan muncul di sini.</span></div></td></tr><?php endif;?>
  </tbody>
 </table></div>
</div>
<?php foreach($rows as $row):?>
<div class="modal fade" id="resetPassword<?=$row['id']?>" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="reset_password">
  <div class="modal-header"><h5 class="modal-title">Reset Password Pemohon</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
  <div class="modal-body"><p class="mb-3">Username: <strong><?=e($row['username'])?></strong></p><label class="form-label">Password baru</label><input type="text" class="form-control" name="password_baru" minlength="8" autocomplete="new-password" required><div class="form-text">Minimal 8 karakter. Salin dan berikan kepada pemohon setelah disimpan.</div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-key me-1"></i>Simpan Password Baru</button></div>
 </form></div>
</div>
<?php endforeach;?>
<?php require APP_ROOT.'/includes/footer.php';?>
