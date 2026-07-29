<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php'; require_login();
$definitions=[
'klien'=>['title'=>'Klien','table'=>'klien','roles'=>['super_admin','admin_lab'],'columns'=>['kode_klien'=>'Kode','nama_klien'=>'Nama klien','nama_perusahaan'=>'Perusahaan','nama_kontak'=>'Kontak','whatsapp'=>'WhatsApp','email'=>'Email','alamat'=>'Alamat','kabupaten'=>'Kabupaten/Kota','provinsi'=>'Provinsi','npwp'=>'NPWP','catatan'=>'Catatan'],'required'=>['nama_klien'],'code'=>['kode_klien','KL-']],
'alat'=>['title'=>'Alat Sondir','table'=>'alat_sondir','roles'=>['super_admin'],'columns'=>['kode_alat'=>'Kode alat','nama_alat'=>'Nama alat','jenis_alat'=>'Jenis alat','merek'=>'Merek','model'=>'Model','nomor_seri'=>'Nomor seri','kapasitas_maksimum'=>'Kapasitas maksimum','satuan_kapasitas'=>'Satuan kapasitas','diameter_piston'=>'Diameter piston (cm)','diameter_konus'=>'Diameter konus (cm)','diameter_selimut'=>'Diameter selimut (cm)','panjang_selimut_geser'=>'Panjang selimut geser (cm)','luas_piston'=>'Luas piston Api (cm²)','luas_konus'=>'Luas konus Ac (cm²)','luas_selimut'=>'Luas selimut As (cm²)','faktor_kalibrasi_konus'=>'Faktor kalibrasi konus','faktor_kalibrasi_total'=>'Faktor kalibrasi total','interval_standar'=>'Interval standar (m)','nomor_sertifikat'=>'Nomor sertifikat','tanggal_kalibrasi'=>'Tanggal kalibrasi','tanggal_kedaluwarsa'=>'Tanggal kedaluwarsa','lembaga_kalibrasi'=>'Lembaga kalibrasi','kondisi'=>'Kondisi alat','status'=>'Status','catatan'=>'Catatan'],'required'=>['nama_alat','diameter_piston','diameter_konus','diameter_selimut','panjang_selimut_geser','luas_piston','luas_konus','luas_selimut'],'code'=>['kode_alat','ALT-'],'select'=>['jenis_alat'=>['Konus ganda mekanis','Konus tunggal mekanis'],'satuan_kapasitas'=>['kPa/100','kPa','MPa','kg/cm²'],'status'=>['aktif','tidak_aktif','perbaikan']],'numeric'=>['kapasitas_maksimum','diameter_piston','diameter_konus','diameter_selimut','panjang_selimut_geser','luas_piston','luas_konus','luas_selimut','faktor_kalibrasi_konus','faktor_kalibrasi_total','interval_standar'],'dates'=>['tanggal_kalibrasi','tanggal_kedaluwarsa']],
'users'=>['title'=>'Pengguna','table'=>'users','roles'=>['super_admin'],'columns'=>['nama_lengkap'=>'Nama lengkap','username'=>'Username','email'=>'Email','whatsapp'=>'WhatsApp','level'=>'Level','jabatan'=>'Jabatan','nomor_identitas'=>'Nomor identitas','status'=>'Status'],'required'=>['nama_lengkap','username','level'],'select'=>['level'=>['super_admin','admin_lab','operator','pemeriksa'],'status'=>['aktif','tidak_aktif']],'password'=>true],
];
$def=$definitions[$entity]??null;if(!$def){http_response_code(404);exit('Modul tidak ditemukan');}
$canEdit=can($def['roles']); $id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_role($def['roles']); verify_csrf(); $action=$_POST['action']??'save'; $id=(int)($_POST['id']??0);
 if($action==='delete'){
   if($def['table']==='users'&&$id===(int)$_SESSION['user']['id']){flash('danger','Anda tidak dapat menghapus akun sendiri.');redirect($entity.'/index.php');}
   if($def['table']==='users'){ $q=$pdo->prepare("SELECT level FROM users WHERE id=?");$q->execute([$id]);if($q->fetchColumn()==='super_admin'&&(int)$pdo->query("SELECT COUNT(*) FROM users WHERE level='super_admin'")->fetchColumn()<=1){flash('danger','Super Admin terakhir tidak dapat dihapus.');redirect($entity.'/index.php');}}
   try{$q=$pdo->prepare("DELETE FROM {$def['table']} WHERE id=?");$q->execute([$id]);audit($pdo,'Menghapus '.$def['title'],$def['table'],$id);flash('success','Data berhasil dihapus.');}
   catch(PDOException $e){error_log($e->getMessage());flash('danger','Data tidak dapat dihapus karena masih digunakan oleh data lain.');}
   redirect($entity.'/index.php');
 }
 $data=[];foreach($def['columns'] as $field=>$label){$data[$field]=trim((string)($_POST[$field]??''));}
 foreach($def['required'] as $field)if($data[$field]===''){flash('danger',$def['columns'][$field].' wajib diisi.');redirect($entity.'/index.php'.($id?'?id='.$id:''));}
 if(isset($def['code'])&&$data[$def['code'][0]]==='')$data[$def['code'][0]]=next_code($pdo,$def['table'],$def['code'][0],$def['code'][1]);
 if(!empty($def['password'])&&!empty($_POST['password']))$data['password']=password_hash($_POST['password'],PASSWORD_DEFAULT);
 elseif(!empty($def['password'])&&!$id)$data['password']=password_hash('admin123',PASSWORD_DEFAULT);
 $clean=array_filter($data,fn($v)=>$v!=='',ARRAY_FILTER_USE_BOTH);
 try{
  if($id){$old=$pdo->prepare("SELECT * FROM {$def['table']} WHERE id=?");$old->execute([$id]);$before=$old->fetch();$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($clean)));$q=$pdo->prepare("UPDATE {$def['table']} SET $set,updated_at=NOW() WHERE id=?");$q->execute([...array_values($clean),$id]);audit($pdo,'Mengubah '.$def['title'],$def['table'],$id,$before,$clean);}
  else{$cols=array_keys($clean);$q=$pdo->prepare("INSERT INTO {$def['table']}(".implode(',',$cols).") VALUES(".implode(',',array_fill(0,count($cols),'?')).")");$q->execute(array_values($clean));$id=(int)$pdo->lastInsertId();audit($pdo,'Menambah '.$def['title'],$def['table'],$id,null,$clean);}
  flash('success','Data berhasil disimpan.');
 }catch(PDOException $e){error_log($e->getMessage());flash('danger','Data gagal disimpan. Pastikan kode, username, dan email tidak duplikat.');}
 redirect($entity.'/index.php');
}
$edit=null;if($id){$q=$pdo->prepare("SELECT * FROM {$def['table']} WHERE id=?");$q->execute([$id]);$edit=$q->fetch();}
$search=trim($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$requestedPer=(int)($_GET['per']??10);$per=in_array($requestedPer,[10,25,50,100],true)?$requestedPer:10;
$searchCols=array_slice(array_keys($def['columns']),0,4);$where='';$args=[];if($search!==''){$where=' WHERE '.implode(' OR ',array_map(fn($c)=>"$c LIKE ?",$searchCols));$args=array_fill(0,count($searchCols),"%$search%");}
$q=$pdo->prepare("SELECT COUNT(*) FROM {$def['table']}$where");$q->execute($args);$total=(int)$q->fetchColumn();$offset=($page-1)*$per;
$q=$pdo->prepare("SELECT * FROM {$def['table']}$where ORDER BY id DESC LIMIT $per OFFSET $offset");$q->execute($args);$rows=$q->fetchAll();
$pageTitle=$def['title'];require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading">
  <div><span class="eyebrow">Master Data</span><h2>Data <?=e($def['title'])?></h2><p><?=$total?> data tersimpan dan siap digunakan.</p></div>
  <?php if($canEdit):?><button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#formModal"><i class="bi bi-plus-lg"></i><span>Tambah <?=e($def['title'])?></span></button><?php endif;?>
</div>
<div class="card data-card">
  <div class="toolbar">
    <form class="row g-2 align-items-center w-100">
      <div class="col"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Cari <?=strtolower(e($def['title']))?>..."></div></div>
      <div class="col-auto"><select class="form-select" name="per" onchange="this.form.submit()" aria-label="Jumlah data per halaman"><?php foreach([10,25,50,100] as $n):?><option value="<?=$n?>" <?=$per===$n?'selected':''?>><?=$n?> / halaman</option><?php endforeach;?></select></div>
      <div class="col-auto"><button class="btn btn-outline-primary">Terapkan</button></div>
      <?php if($search!==''):?><div class="col-auto"><a href="index.php" class="btn btn-light">Reset</a></div><?php endif;?>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="text-center" style="width:64px">No</th><?php foreach(array_slice($def['columns'],0,6,true) as $label):?><th><?=e($label)?></th><?php endforeach;?><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php if(!$rows):?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><strong>Belum ada data</strong><span>Gunakan tombol tambah untuk membuat data pertama.</span></div></td></tr><?php endif;?>
  <?php foreach($rows as $i=>$r):?><tr><td class="text-center text-secondary"><?=$offset+$i+1?></td><?php foreach(array_slice($def['columns'],0,6,true) as $field=>$label):?><td><?=str_contains($field,'status')||$field==='level'?status_badge($r[$field]):e($r[$field]?:'-')?></td><?php endforeach;?><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?id=<?=$r['id']?>" title="Edit"><i class="bi bi-pencil-square"></i><span class="d-none d-xl-inline ms-1">Edit</span></a><?php if($canEdit):?> <form method="post" class="d-inline" data-confirm="Hapus data ini?"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash3"></i><span class="d-none d-xl-inline ms-1">Hapus</span></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
  <div class="card-footer list-footer"><span>Menampilkan <strong><?=count($rows)?></strong> dari <strong><?=$total?></strong> data</span><?=paginate($total,$page,$per)?></div>
</div>
<?php if($canEdit):?><div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="post" class="modal-content"><?=csrf_field()?><input type="hidden" name="id" value="<?=$edit['id']??0?>"><div class="modal-header"><div><span class="eyebrow"><?=$edit?'Perbarui data':'Data baru'?></span><h5 class="modal-title"><?=$edit?'Edit':'Tambah'?> <?=e($def['title'])?></h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="form-section"><div class="form-section-title"><i class="bi bi-card-list"></i><span>Informasi <?=e($def['title'])?></span></div><div class="row g-3"><?php foreach($def['columns'] as $field=>$label):?><div class="<?=$field==='alamat'||$field==='catatan'?'col-12':'col-md-6 col-xl-4'?>"><label class="form-label <?=in_array($field,$def['required'],true)?'required':''?>"><?=e($label)?></label><?php if(isset($def['select'][$field])):?><select class="form-select" name="<?=$field?>"><?php foreach($def['select'][$field] as $option):?><option value="<?=e($option)?>" <?=($edit[$field]??'')===$option?'selected':''?>><?=e(ucwords(str_replace('_',' ',$option)))?></option><?php endforeach;?></select><?php elseif($field==='catatan'||$field==='alamat'):?><textarea class="form-control" name="<?=$field?>" rows="3"><?=e($edit[$field]??'')?></textarea><?php else:?><input class="form-control" type="<?=in_array($field,$def['dates']??[],true)?'date':(in_array($field,$def['numeric']??[],true)?'number':'text')?>" <?=in_array($field,$def['numeric']??[],true)?'step="any"':''?> name="<?=$field?>" value="<?=e($edit[$field]??'')?>" <?=in_array($field,$def['required'],true)?'required':''?>><?php endif;?></div><?php endforeach;?><?php if(!empty($def['password'])):?><div class="col-md-6 col-xl-4"><label class="form-label">Password <?=$edit?'baru':'awal'?></label><input type="password" name="password" class="form-control" placeholder="<?=$edit?'Kosongkan jika tidak diubah':'Default: admin123'?>"></div><?php endif;?></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i>Simpan</button></div></form></div></div><?php endif;?>
<?php if($edit):?><script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal('#formModal').show())</script><?php endif;?>
<?php if($entity==='alat'):?><script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('#formModal form'); if(!form)return;
 const input=name=>form.elements.namedItem(name);
 const calculate=()=>{
  const dp=parseFloat(input('diameter_piston').value)||0;
  const dk=parseFloat(input('diameter_konus').value)||0;
  const ds=parseFloat(input('diameter_selimut').value)||0;
  const ps=parseFloat(input('panjang_selimut_geser').value)||0;
  if(dp)input('luas_piston').value=(Math.PI*dp*dp/4).toFixed(6);
  if(dk)input('luas_konus').value=(Math.PI*dk*dk/4).toFixed(6);
  if(ds&&ps)input('luas_selimut').value=(Math.PI*ds*ps).toFixed(6);
 };
 ['diameter_piston','diameter_konus','diameter_selimut','panjang_selimut_geser'].forEach(name=>input(name)?.addEventListener('input',calculate));
});
</script><?php endif;?>
<?php require APP_ROOT.'/includes/footer.php';
