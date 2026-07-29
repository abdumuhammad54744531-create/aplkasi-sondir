<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','pemeriksa']);
require __DIR__.'/_report.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$projectId=(int)($_POST['proyek_id']??0);$decision=(string)($_POST['keputusan']??'');$note=trim((string)($_POST['catatan']??''));
 $newStatus=['setujui'=>'disetujui','revisi'=>'perlu_revisi'][$decision]??null;
 if(!$projectId||!$newStatus){flash('danger','Keputusan tidak valid.');redirect('laporan/pengesahan.php');}
 $q=$pdo->prepare("SELECT id FROM titik_sondir WHERE proyek_id=? AND status IN('menunggu_pemeriksaan','perlu_revisi','disetujui')");$q->execute([$projectId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
 if(!$ids){flash('warning','Tidak ada titik yang dapat disahkan.');redirect('laporan/pengesahan.php');}
 $pdo->beginTransaction();
 try{$review=$pdo->prepare('INSERT INTO pemeriksaan(titik_sondir_id,pemeriksa_id,status,catatan) VALUES(?,?,?,?)');$update=$pdo->prepare('UPDATE titik_sondir SET status=?,updated_at=NOW() WHERE id=?');foreach($ids as $id){$review->execute([$id,(int)$_SESSION['user']['id'],$newStatus,$note]);$update->execute([$newStatus,$id]);}audit($pdo,'Pengesahan laporan proyek','proyek',$projectId,null,['status'=>$newStatus,'catatan'=>$note]);$pdo->commit();flash('success',$newStatus==='disetujui'?'Laporan berhasil disahkan.':'Laporan dikembalikan untuk revisi.');}
 catch(Throwable $e){$pdo->rollBack();error_log('[PENGESAHAN] '.$e->getMessage());flash('danger','Pengesahan gagal disimpan.');}
 redirect('laporan/pengesahan.php');
}
$rows=$pdo->query("SELECT p.id,p.kode_proyek,p.nama_proyek,k.nama_klien,COUNT(t.id) jumlah_titik,GROUP_CONCAT(DISTINCT t.status ORDER BY t.status) status_titik,MAX(t.updated_at) updated_at FROM proyek p JOIN klien k ON k.id=p.klien_id JOIN titik_sondir t ON t.proyek_id=p.id GROUP BY p.id,p.kode_proyek,p.nama_proyek,k.nama_klien ORDER BY MAX(t.updated_at) DESC")->fetchAll();
foreach($rows as &$row){$statuses=array_filter(explode(',',(string)$row['status_titik']));$row['report_status']=report_status(array_map(fn($status)=>['status'=>$status],$statuses));}unset($row);
$pageTitle='Pengesahan Laporan';require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Persetujuan Elektronik</span><h2>Pengesahan Laporan</h2><p>Periksa dan sahkan seluruh titik sondir dalam satu laporan proyek.</p></div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Proyek</th><th>Klien</th><th>Titik</th><th>Status</th><th>Pembaruan</th><th class="text-end">Aksi</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><b><?=e($row['kode_proyek'])?></b><small class="d-block"><?=e($row['nama_proyek'])?></small></td><td><?=e($row['nama_klien'])?></td><td><?=(int)$row['jumlah_titik']?> titik</td><td><?=status_badge($row['report_status'])?></td><td><?=e(tanggal_id($row['updated_at'],true))?></td><td class="text-end"><a target="_blank" class="btn btn-sm btn-outline-primary" href="<?=url('laporan/cetak-pdf.php?proyek_id='.$row['id'])?>">Lihat laporan</a><?php if(in_array($row['report_status'],['menunggu_pemeriksaan','perlu_revisi'],true)):?> <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approve<?=$row['id']?>">Pengesahan</button><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php foreach($rows as $row):if(!in_array($row['report_status'],['menunggu_pemeriksaan','perlu_revisi'],true))continue;?><div class="modal fade" id="approve<?=$row['id']?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?=csrf_field()?><input type="hidden" name="proyek_id" value="<?=$row['id']?>"><div class="modal-header"><h5 class="modal-title">Pengesahan <?=e($row['kode_proyek'])?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Keputusan berlaku untuk seluruh <b><?=(int)$row['jumlah_titik']?> titik</b>.</p><label class="form-label">Catatan pemeriksa</label><textarea class="form-control" name="catatan" rows="4"></textarea></div><div class="modal-footer"><button class="btn btn-outline-danger" name="keputusan" value="revisi">Perlu revisi</button><button class="btn btn-success" name="keputusan" value="setujui">Sahkan laporan</button></div></form></div></div><?php endforeach;?>
<?php require APP_ROOT.'/includes/footer.php';?>
