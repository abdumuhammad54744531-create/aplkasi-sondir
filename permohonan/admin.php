<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','admin_lab']);
require __DIR__.'/_common.php';
permohonan_ensure($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');$note=trim((string)($_POST['catatan_admin']??''));
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare("SELECT * FROM permohonan WHERE id=? FOR UPDATE");$q->execute([$id]);$request=$q->fetch();
        if(!$request||$request['status']!=='diajukan')throw new RuntimeException('Permohonan sudah diproses atau tidak ditemukan.');
        if($action==='tolak'){
            $pdo->prepare("UPDATE permohonan SET status='ditolak',catatan_admin=?,diproses_oleh=?,diproses_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$note,(int)$_SESSION['user']['id'],$id]);
            audit($pdo,'Menolak permohonan','permohonan',$id,null,['status'=>'ditolak','catatan'=>$note]);
            $pdo->commit();flash('success','Permohonan ditolak.');redirect('permohonan/admin.php');
        }
        if($action!=='terima')throw new RuntimeException('Keputusan tidak valid.');
        $clientId=0;
        if($request['email']){
            $find=$pdo->prepare('SELECT id FROM klien WHERE email=? LIMIT 1');$find->execute([$request['email']]);$clientId=(int)$find->fetchColumn();
        }
        if(!$clientId){
            $clientCode=next_code($pdo,'klien','kode_klien','KL-');
            $pdo->prepare('INSERT INTO klien(kode_klien,nama_klien,nama_perusahaan,nama_kontak,whatsapp,email,alamat,kabupaten,provinsi,npwp,catatan) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$clientCode,$request['nama_klien'],$request['nama_perusahaan'],$request['nama_kontak'],$request['whatsapp'],$request['email'],$request['alamat_klien'],$request['kabupaten_klien'],$request['provinsi_klien'],$request['npwp'],'Dibuat otomatis dari '.$request['nomor_permohonan']]);
            $clientId=(int)$pdo->lastInsertId();
        }
        $next=(int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM proyek')->fetchColumn();
        $projectCode='PRJ-'.date('Y').'-'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO proyek(klien_id,kode_proyek,nama_proyek,nama_pekerjaan,pemilik_pekerjaan,alamat_lokasi,desa,kecamatan,kabupaten,provinsi,tanggal_mulai,jumlah_titik_rencana,status,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$clientId,$projectCode,$request['nama_proyek'],$request['nama_pekerjaan'],$request['pemilik_pekerjaan'],$request['alamat_lokasi'],$request['desa'],$request['kecamatan'],$request['kabupaten'],$request['provinsi'],$request['tanggal_rencana'],max(1,(int)$request['jumlah_titik_rencana']),'draft','Dibuat otomatis dari '.$request['nomor_permohonan'].($note?'. '.$note:''),(int)$_SESSION['user']['id']]);
        $projectId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE permohonan SET status='diterima',catatan_admin=?,klien_id=?,proyek_id=?,diproses_oleh=?,diproses_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$note,$clientId,$projectId,(int)$_SESSION['user']['id'],$id]);
        audit($pdo,'Menerima permohonan dan membuat klien/proyek','permohonan',$id,null,['klien_id'=>$clientId,'proyek_id'=>$projectId]);
        $pdo->commit();flash('success','Permohonan diterima. Data klien dan proyek otomatis dibuat.');redirect('permohonan/admin.php');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[PERMOHONAN] '.$e->getMessage());flash('danger',$e->getMessage());redirect('permohonan/admin.php');}
}

$status=in_array($_GET['status']??'',['diajukan','diterima','ditolak'],true)?$_GET['status']:'';
$sql="SELECT r.*,a.nama_lengkap nama_pemohon,a.email email_akun,u.nama_lengkap diproses_nama FROM permohonan r JOIN akun_pemohon a ON a.id=r.pemohon_id LEFT JOIN users u ON u.id=r.diproses_oleh";
$args=[];if($status){$sql.=' WHERE r.status=?';$args[]=$status;}$sql.=' ORDER BY r.id DESC';$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();
$counts=$pdo->query("SELECT status,COUNT(*) jumlah FROM permohonan GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pageTitle='Permohonan Pemohon';require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Portal Pemohon</span><h2>Permohonan Pengujian</h2><p>Periksa permohonan masuk. Saat diterima, data Klien dan Proyek dibuat otomatis.</p></div><a target="_blank" class="btn btn-outline-primary" href="<?=url('permohonan/index.php')?>"><i class="bi bi-box-arrow-up-right me-1"></i>Buka web pemohon</a></div>
<div class="row g-3 mb-3"><?php foreach(['diajukan'=>'warning','diterima'=>'success','ditolak'=>'danger'] as $key=>$color):?><div class="col-md-4"><a class="card text-decoration-none" href="?status=<?=$key?>"><div class="card-body d-flex justify-content-between"><span><?=ucwords($key)?></span><b class="text-<?=$color?>"><?=(int)($counts[$key]??0)?></b></div></a></div><?php endforeach;?></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nomor/Pemohon</th><th>Klien</th><th>Proyek</th><th>Lokasi/Titik</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><b><?=e($row['nomor_permohonan'])?></b><small class="d-block text-secondary"><?=e($row['nama_pemohon'].' · '.$row['email_akun'])?></small></td><td><?=e($row['nama_klien'])?><small class="d-block text-secondary"><?=e($row['nama_perusahaan'])?></small></td><td><b><?=e($row['nama_proyek'])?></b><small class="d-block text-secondary"><?=e($row['nama_pekerjaan'])?></small></td><td><?=e(trim($row['kecamatan'].' '.$row['kabupaten']))?><small class="d-block text-secondary"><?=(int)$row['jumlah_titik_rencana']?> titik rencana</small></td><td><span class="badge text-bg-<?=$row['status']==='diterima'?'success':($row['status']==='ditolak'?'danger':'warning')?>"><?=e(ucwords($row['status']))?></span></td><td class="text-end"><?php if($row['status']==='diajukan'):?><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#process<?=$row['id']?>">Periksa</button><?php elseif($row['proyek_id']):?><a class="btn btn-sm btn-outline-primary" href="<?=url('proyek/index.php?id='.$row['proyek_id'])?>">Lihat proyek</a><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><strong>Belum ada permohonan</strong><span>Permohonan dari web pemohon akan muncul di sini.</span></div></td></tr><?php endif;?></tbody></table></div></div>
<?php foreach($rows as $row):if($row['status']!=='diajukan')continue;?><div class="modal fade" id="process<?=$row['id']?>" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" class="modal-content"><?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><div class="modal-header"><div><span class="eyebrow"><?=e($row['nomor_permohonan'])?></span><h5 class="modal-title"><?=e($row['nama_proyek'])?></h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><b>Data klien</b><p><?=e($row['nama_klien'])?><br><?=e($row['nama_perusahaan'])?><br><?=e($row['whatsapp'].' · '.$row['email'])?><br><?=e($row['alamat_klien'])?></p></div><div class="col-md-6"><b>Data proyek</b><p><?=e($row['nama_proyek'])?><br><?=e($row['nama_pekerjaan'])?><br><?=e($row['alamat_lokasi'])?><br><?=(int)$row['jumlah_titik_rencana']?> titik</p></div><div class="col-12"><label class="form-label">Catatan admin</label><textarea class="form-control" name="catatan_admin" rows="3"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-outline-danger" name="action" value="tolak">Tolak</button><button class="btn btn-success" name="action" value="terima">Terima & buat Klien/Proyek</button></div></form></div></div><?php endforeach;?>
<?php require APP_ROOT.'/includes/footer.php';?>
