<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();
require __DIR__.'/_report.php';
require_once APP_ROOT.'/includes/ReportReadinessService.php';
$readinessService=new ReportReadinessService($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_role(['super_admin','pemeriksa']);
    verify_csrf();
    $projectId=max(0,(int)($_POST['proyek_id']??0));
    $decision=(string)($_POST['keputusan']??'');
    $note=trim((string)($_POST['catatan']??''));
    $newStatus=['setujui'=>'disetujui','revisi'=>'perlu_revisi'][$decision]??null;
    if(!$projectId||!$newStatus)exit('Keputusan tidak valid');
    if($newStatus==='disetujui'){$readiness=$readinessService->evaluate($projectId);if($readiness['errors']>0){flash('danger','Laporan belum dapat disahkan: terdapat '.$readiness['errors'].' pemeriksaan fatal.');redirect('laporan/index.php');}}

    $q=$pdo->prepare("SELECT id FROM titik_sondir WHERE proyek_id=? AND status IN('menunggu_pemeriksaan','perlu_revisi','disetujui')");
    $q->execute([$projectId]);
    $pointIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    if(!$pointIds){
        flash('warning','Tidak ada titik proyek yang dapat diperiksa.');
        redirect('laporan/index.php');
    }

    $pdo->beginTransaction();
    try{
        $review=$pdo->prepare('INSERT INTO pemeriksaan(titik_sondir_id,pemeriksa_id,status,catatan) VALUES(?,?,?,?)');
        $update=$pdo->prepare('UPDATE titik_sondir SET status=?,updated_at=NOW() WHERE id=?');
        foreach($pointIds as $pointId){
            $review->execute([$pointId,$_SESSION['user']['id'],$newStatus,$note]);
            $update->execute([$newStatus,$pointId]);
        }
        audit($pdo,'Pemeriksaan laporan proyek','proyek',$projectId,null,['status'=>$newStatus,'jumlah_titik'=>count($pointIds),'catatan'=>$note]);
        $pdo->commit();
        flash('success','Keputusan disimpan untuk seluruh '.count($pointIds).' titik dalam proyek.');
    }catch(Throwable $error){
        $pdo->rollBack();
        error_log('[LAPORAN PROYEK] '.$error->getMessage());
        flash('danger','Keputusan laporan proyek gagal disimpan.');
    }
    redirect('laporan/index.php');
}

$rows=$pdo->query(
    "SELECT p.id,p.kode_proyek,p.nama_proyek,p.alamat_lokasi,p.updated_at,k.nama_klien,
            COUNT(DISTINCT t.id) jumlah_titik,COUNT(h.id) jumlah_baris,
            MAX(h.qc) qc_max,MAX(h.kedalaman) kedalaman_max,
            GROUP_CONCAT(DISTINCT t.status ORDER BY t.status SEPARATOR ',') status_titik
     FROM proyek p
     JOIN klien k ON k.id=p.klien_id
     JOIN titik_sondir t ON t.proyek_id=p.id
     LEFT JOIN hasil_sondir h ON h.titik_sondir_id=t.id
     GROUP BY p.id,p.kode_proyek,p.nama_proyek,p.alamat_lokasi,p.updated_at,k.nama_klien
     ORDER BY COALESCE(p.updated_at,p.created_at) DESC,p.id DESC"
)->fetchAll();
foreach($rows as &$row){
    $statuses=array_filter(explode(',',(string)$row['status_titik']));
    $row['report_status']=report_status(array_map(fn($status)=>['status'=>$status],$statuses));
    $row['readiness']=$readinessService->evaluate((int)$row['id']);
}
unset($row);

$pageTitle='Laporan Proyek';
require APP_ROOT.'/includes/header.php';
?>
<style>
.report-project-card{border:1px solid #dce5ed;border-radius:14px;overflow:hidden}
.report-project-card thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#52677b;background:#f3f7fa}
.report-title-cell b{display:block;color:#173b61}.report-title-cell small{display:block;color:#718294;margin-top:3px}
.report-metric{font-weight:700;color:#173b61}.report-metric small{display:block;font-weight:400;color:#718294}
.report-scope{background:#e9f3ff;border:1px solid #bed8f5;color:#174b7a;border-radius:12px;padding:13px 16px;margin-bottom:16px}
</style>
<div class="page-heading">
  <div><span class="eyebrow">Dokumen Hasil Penyelidikan</span><h2>Laporan per Proyek</h2><p>Satu PDF memuat seluruh titik sondir, data, grafik, analisis pondasi, pengesahan, dan lampiran proyek.</p></div>
</div>
<div class="report-scope"><i class="bi bi-journals me-2"></i><b>Satu proyek = satu laporan.</b> Jumlah bagian Sondir di dalam PDF mengikuti jumlah titik yang tersimpan pada proyek.</div>
<div class="card report-project-card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Proyek</th><th>Pemohon</th><th>Cakupan laporan</th><th>Data maksimum</th><th>Kesiapan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
<tbody>
<?php foreach($rows as $row):?>
<tr>
  <td class="report-title-cell"><b><?=e($row['kode_proyek'].' — '.$row['nama_proyek'])?></b><small><i class="bi bi-geo-alt me-1"></i><?=e($row['alamat_lokasi']?:'Alamat belum diisi')?></small></td>
  <td><?=e($row['nama_klien'])?></td>
  <td><span class="report-metric"><?=(int)$row['jumlah_titik']?> titik sondir</span><small class="d-block text-secondary"><?=(int)$row['jumlah_baris']?> baris data · seluruh grafik dan tabel</small></td>
  <td><span class="report-metric"><?=report_number((float)$row['kedalaman_max'],2)?> m</span><small>qc <?=report_number((float)$row['qc_max'],0)?> kg/cm²</small></td>
  <td><div class="progress" style="height:8px;min-width:110px"><div class="progress-bar <?=$row['readiness']['errors']?'bg-danger':($row['readiness']['warnings']?'bg-warning':'bg-success')?>" style="width:<?=$row['readiness']['score']?>%"></div></div><small><b><?=$row['readiness']['score']?>%</b> · <?=e($row['readiness']['category'])?></small></td>
  <td><?=status_badge((string)$row['report_status'])?></td>
  <td class="text-end text-nowrap">
    <a class="btn btn-sm btn-primary" target="_blank" href="<?=url('laporan/cetak-pdf.php?proyek_id='.(int)$row['id'])?>"><i class="bi bi-file-earmark-pdf me-1"></i> Laporan lengkap</a>
    <?php if(can(['super_admin','pemeriksa'])&&in_array($row['report_status'],['menunggu_pemeriksaan','perlu_revisi'],true)):?>
      <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#review<?=(int)$row['id']?>" aria-controls="review<?=(int)$row['id']?>"><i class="bi bi-check2-square me-1"></i> Setujui laporan</button>
    <?php endif;?>
  </td>
</tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-earmark-text"></i><strong>Belum ada proyek dengan titik sondir</strong><span>Tambahkan titik dan data pengujian terlebih dahulu.</span></div></td></tr><?php endif;?>
</tbody></table></div></div>
<?php foreach($rows as $row):?>
<?php if(can(['super_admin','pemeriksa'])&&in_array($row['report_status'],['menunggu_pemeriksaan','perlu_revisi'],true)):?>
<div class="modal fade" id="review<?=(int)$row['id']?>" tabindex="-1" aria-labelledby="reviewTitle<?=(int)$row['id']?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
    <?=csrf_field()?><input type="hidden" name="proyek_id" value="<?=(int)$row['id']?>">
    <div class="modal-header"><div><span class="eyebrow">Seluruh titik proyek</span><h5 class="modal-title" id="reviewTitle<?=(int)$row['id']?>">Persetujuan <?=e($row['kode_proyek'])?></h5></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
      <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Keputusan diterapkan kepada <b><?=(int)$row['jumlah_titik']?> titik sondir</b> dalam laporan proyek ini.</div>
      <label class="form-label" for="reviewNote<?=(int)$row['id']?>">Catatan pemeriksa</label>
      <textarea class="form-control" id="reviewNote<?=(int)$row['id']?>" name="catatan" rows="4" placeholder="Isi catatan pemeriksaan atau alasan revisi..."></textarea>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-outline-danger" name="keputusan" value="revisi"><i class="bi bi-arrow-counterclockwise me-1"></i>Perlu revisi</button><button type="submit" class="btn btn-success" name="keputusan" value="setujui"><i class="bi bi-check2-circle me-1"></i>Setujui laporan</button></div>
  </form></div>
</div>
<?php endif;?>
<?php endforeach;?>
<?php require APP_ROOT.'/includes/footer.php';?>
