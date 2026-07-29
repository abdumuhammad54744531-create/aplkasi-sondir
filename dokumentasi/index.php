<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','operator','pemeriksa']);

$projectId=max(0,(int)($_GET['proyek_id']??$_POST['proyek_id']??0));
$pointId=max(0,(int)($_GET['titik_id']??$_POST['titik_id']??0));
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(isset($_POST['delete_id'])){
        $q=$pdo->prepare('SELECT nama_file FROM dokumentasi_sondir WHERE id=?');
        $q->execute([(int)$_POST['delete_id']]);
        $name=$q->fetchColumn();
        if($name){
            $pdo->prepare('DELETE FROM dokumentasi_sondir WHERE id=?')->execute([(int)$_POST['delete_id']]);
            $path=APP_ROOT.'/uploads/'.ltrim((string)$name,'/\\');
            if(is_file($path))unlink($path);
            audit($pdo,'Menghapus dokumentasi titik sondir','dokumentasi_sondir',(int)$_POST['delete_id']);
            flash('success','Dokumentasi berhasil dihapus.');
        }
        redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
    }

    if($pointId<1||!isset($_FILES['foto'])||($_FILES['foto']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
        flash('danger','Pilih titik sondir dan foto yang valid.');
        redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
    }
    if((int)$_FILES['foto']['size']>8*1024*1024){
        flash('danger','Ukuran foto maksimal 8 MB.');
        redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
    }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['foto']['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext){
        flash('danger','Format foto harus JPG, PNG, atau WebP.');
        redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
    }
    $dir=APP_ROOT.'/uploads/dokumentasi';
    if(!is_dir($dir))mkdir($dir,0775,true);
    $filename='titik-'.$pointId.'-'.bin2hex(random_bytes(8)).'.'.$ext;
    if(!move_uploaded_file($_FILES['foto']['tmp_name'],$dir.'/'.$filename)){
        flash('danger','Foto tidak dapat disimpan.');
        redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
    }
    $q=$pdo->prepare('SELECT latitude,longitude,proyek_id FROM titik_sondir WHERE id=?');
    $q->execute([$pointId]);$coordinate=$q->fetch();
    if(!$coordinate||(int)$coordinate['proyek_id']!==$projectId){
        flash('danger','Titik sondir tidak termasuk dalam proyek yang dipilih.');
        redirect('dokumentasi/index.php?proyek_id='.$projectId);
    }
    $pdo->prepare('INSERT INTO dokumentasi_sondir(titik_sondir_id,jenis_foto,judul,keterangan,nama_file,urutan,latitude,longitude,tanggal_foto) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute([$pointId,trim((string)($_POST['jenis_foto']??'Pelaksanaan')),trim((string)($_POST['judul']??'')),trim((string)($_POST['keterangan']??'')),'dokumentasi/'.$filename,max(0,(int)($_POST['urutan']??0)),$coordinate['latitude']??null,$coordinate['longitude']??null,$_POST['tanggal_foto']?:date('Y-m-d')]);
    audit($pdo,'Menambahkan dokumentasi titik sondir','dokumentasi_sondir',(int)$pdo->lastInsertId(),null,['titik_sondir_id'=>$pointId,'file'=>$filename]);
    flash('success','Dokumentasi berhasil ditambahkan dan akan masuk ke lampiran laporan.');
    redirect('dokumentasi/index.php?proyek_id='.$projectId.'&titik_id='.$pointId);
}

$projects=$pdo->query("SELECT p.id,p.kode_proyek,p.nama_proyek,COUNT(t.id) jumlah_titik FROM proyek p LEFT JOIN titik_sondir t ON t.proyek_id=p.id GROUP BY p.id,p.kode_proyek,p.nama_proyek HAVING jumlah_titik>0 ORDER BY p.nama_proyek")->fetchAll();
if(!$projectId&&$projects)$projectId=(int)$projects[0]['id'];
$q=$pdo->prepare("SELECT id,kode_titik,nama_titik,nomor_urut FROM titik_sondir WHERE proyek_id=? ORDER BY nomor_urut,id");
$q->execute([$projectId]);$points=$q->fetchAll();
if($pointId&&!in_array($pointId,array_map(fn($point)=>(int)$point['id'],$points),true))$pointId=0;
if(!$pointId&&$points)$pointId=(int)$points[0]['id'];
$q=$pdo->prepare('SELECT d.*,t.kode_titik,t.nomor_urut,p.nama_proyek FROM dokumentasi_sondir d JOIN titik_sondir t ON t.id=d.titik_sondir_id JOIN proyek p ON p.id=t.proyek_id WHERE d.titik_sondir_id=? ORDER BY d.urutan,d.id');
$q->execute([$pointId]);$docs=$q->fetchAll();
$pageTitle='Dokumentasi Titik Sondir';
require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Foto Lapangan</span><h2>Dokumentasi Titik Sondir</h2><p>Unggah foto untuk setiap titik; data otomatis ditampilkan pada lampiran laporan proyek.</p></div></div>
<div class="row g-3">
 <div class="col-lg-4"><div class="card"><div class="card-header bg-white py-3"><b>Input dokumentasi</b></div><div class="card-body">
  <form method="get" class="mb-3">
   <label class="form-label required">1. Pilih proyek</label><select class="form-select mb-3" name="proyek_id" onchange="this.form.elements.titik_id.value='';this.form.submit()"><option value="">Pilih proyek</option><?php foreach($projects as $project):?><option value="<?=$project['id']?>" <?=$projectId===(int)$project['id']?'selected':''?>><?=e($project['kode_proyek'].' — '.$project['nama_proyek'].' ('.$project['jumlah_titik'].' titik)')?></option><?php endforeach;?></select>
   <label class="form-label required">2. Pilih titik sondir</label><select class="form-select" name="titik_id" onchange="this.form.submit()" <?=$points?'':'disabled'?>><?php if(!$points):?><option>Belum ada titik pada proyek</option><?php endif;?><?php foreach($points as $point):?><option value="<?=$point['id']?>" <?=$pointId===(int)$point['id']?'selected':''?>><?=e('S'.$point['nomor_urut'].' — '.($point['nama_titik']?:$point['kode_titik']))?></option><?php endforeach;?></select>
  </form>
  <form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="proyek_id" value="<?=$projectId?>"><input type="hidden" name="titik_id" value="<?=$pointId?>">
   <div class="mb-3"><label class="form-label required">Foto</label><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/*" capture="environment" required><div class="form-text">JPG, PNG, atau WebP; maksimal 8 MB.</div></div>
   <div class="row g-2"><div class="col-7"><label class="form-label">Jenis foto</label><select class="form-select" name="jenis_foto"><option>Persiapan</option><option selected>Pelaksanaan</option><option>Peralatan</option><option>Lokasi</option><option>Hasil</option></select></div><div class="col-5"><label class="form-label">Urutan</label><input class="form-control" type="number" min="0" name="urutan" value="<?=count($docs)+1?>"></div></div>
   <div class="mt-3"><label class="form-label required">Judul</label><input class="form-control" name="judul" required placeholder="Contoh: Pelaksanaan sondir S1"></div>
   <div class="mt-3"><label class="form-label">Tanggal foto</label><input class="form-control" type="date" name="tanggal_foto" value="<?=date('Y-m-d')?>"></div>
   <div class="mt-3"><label class="form-label">Keterangan</label><textarea class="form-control" name="keterangan" rows="3"></textarea></div>
   <button class="btn btn-primary w-100 mt-3" <?=$pointId?'':'disabled'?>><i class="bi bi-cloud-arrow-up me-1"></i>Simpan dokumentasi</button>
  </form>
 </div></div></div>
 <div class="col-lg-8"><div class="card"><div class="card-header bg-white py-3 d-flex justify-content-between"><b>Foto pada titik aktif</b><span class="badge text-bg-primary"><?=count($docs)?> foto</span></div><div class="card-body">
 <?php if(!$docs):?><div class="empty-state"><i class="bi bi-images"></i><strong>Belum ada dokumentasi</strong><span><?=$pointId?'Unggah foto pertama untuk titik sondir ini.':'Pilih proyek dan titik sondir terlebih dahulu.'?></span></div>
 <?php else:?><div class="row g-3"><?php foreach($docs as $doc):?><div class="col-md-6"><div class="border rounded-3 overflow-hidden h-100"><img src="<?=url('uploads/'.ltrim($doc['nama_file'],'/'))?>" alt="<?=e($doc['judul'])?>" style="width:100%;height:220px;object-fit:cover"><div class="p-3"><div class="d-flex justify-content-between gap-2"><div><b><?=e($doc['judul'])?></b><div class="small text-secondary"><?=e($doc['jenis_foto'].' · '.tanggal_id($doc['tanggal_foto']))?></div></div><form method="post" onsubmit="return confirm('Hapus dokumentasi ini?')"><?=csrf_field()?><input type="hidden" name="proyek_id" value="<?=$projectId?>"><input type="hidden" name="titik_id" value="<?=$pointId?>"><input type="hidden" name="delete_id" value="<?=$doc['id']?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div><p class="small mb-0 mt-2"><?=e($doc['keterangan'])?></p></div></div></div><?php endforeach;?></div><?php endif;?>
 </div></div></div>
</div>
<?php require APP_ROOT.'/includes/footer.php';?>
