<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();

$search=trim($_GET['q']??'');
$status=$_GET['status']??'';
$where=['t.parent_id IS NULL'];
$args=[];
if($search!==''){
    $like='%'.$search.'%';
    $where[]='(t.kode_titik LIKE ? OR t.nama_titik LIKE ? OR p.nama_proyek LIKE ? OR EXISTS(
        SELECT 1 FROM titik_sondir child
        WHERE child.parent_id=t.id AND (child.kode_titik LIKE ? OR child.nama_titik LIKE ?)
    ))';
    $args=[$like,$like,$like,$like,$like];
}
if($status!==''){
    $where[]='t.status=?';
    $args[]=$status;
}

$sql="SELECT t.*,p.nama_proyek,u.nama_lengkap operator,
        (SELECT COUNT(*) FROM titik_sondir point WHERE point.id=t.id OR point.parent_id=t.id) jumlah_titik,
        (SELECT COUNT(*) FROM hasil_sondir h
         JOIN titik_sondir point ON point.id=h.titik_sondir_id
         WHERE point.id=t.id OR point.parent_id=t.id) jumlah,
        (SELECT GROUP_CONCAT(DISTINCT h.zona_sbt ORDER BY h.zona_sbt SEPARATOR ',') FROM hasil_sondir h
         JOIN titik_sondir point ON point.id=h.titik_sondir_id
         WHERE (point.id=t.id OR point.parent_id=t.id) AND h.zona_sbt IS NOT NULL) zona_sbt_list,
        (SELECT COUNT(*) FROM hasil_sondir h
         JOIN titik_sondir point ON point.id=h.titik_sondir_id
         WHERE (point.id=t.id OR point.parent_id=t.id) AND h.zona_sbt IS NULL) zona_di_luar
      FROM titik_sondir t
      JOIN proyek p ON p.id=t.proyek_id
      JOIN users u ON u.id=t.operator_id
      WHERE ".implode(' AND ',$where).'
      ORDER BY t.created_at DESC';
$stmt=$pdo->prepare($sql);
$stmt->execute($args);
$rows=$stmt->fetchAll();

$graphPoints=[];
if($rows){
    $masterIds=array_map('intval',array_column($rows,'id'));
    $placeholders=implode(',',array_fill(0,count($masterIds),'?'));
    $pointStmt=$pdo->prepare(
        "SELECT id,parent_id,kode_titik,nama_titik,nomor_urut,status
         FROM titik_sondir
         WHERE id IN ($placeholders) OR parent_id IN ($placeholders)
         ORDER BY COALESCE(parent_id,id),nomor_urut,id"
    );
    $pointStmt->execute([...$masterIds,...$masterIds]);
    foreach($pointStmt->fetchAll() as $point){
        $masterId=(int)($point['parent_id']?:$point['id']);
        $graphPoints[$masterId][]=$point;
    }
}

$pageTitle='Pengujian Sondir';
require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">Pengujian Lapangan</span><h2>Pengujian Sondir</h2><p>Input, hitung, periksa, dan lihat grafik setiap titik sondir.</p></div>
    <a class="btn btn-primary btn-action" href="<?=url('titik-sondir/index.php')?>"><i class="bi bi-plus-lg"></i> Titik baru</a>
</div>

<div class="card data-card">
    <div class="card-body border-bottom">
        <form class="row g-2">
            <div class="col-md-5"><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Cari titik atau proyek"></div>
            <div class="col-md-3"><select class="form-select" name="status"><option value="">Semua status</option><?php foreach(['draft','sedang_diuji','menunggu_pemeriksaan','perlu_revisi','disetujui','diterbitkan','dibatalkan'] as $item):?><option value="<?=$item?>" <?=$status===$item?'selected':''?>><?=ucwords(str_replace('_',' ',$item))?></option><?php endforeach;?></select></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Titik</th><th>Proyek</th><th>Tanggal</th><th>Operator</th><th>Data</th><th>Hasil Zonasi SBT</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row):?>
                <tr>
                    <td><b><?=e($row['kode_titik'])?></b><br><small><?=e($row['nama_titik'])?></small></td>
                    <td><?=e($row['nama_proyek'])?></td>
                    <td><?=tanggal_id($row['tanggal_pengujian'])?></td>
                    <td><?=e($row['operator'])?></td>
                    <td><strong><?=(int)$row['jumlah_titik']?> titik</strong><br><small class="text-secondary"><?=(int)$row['jumlah']?> baris data</small></td>
                    <td><?php $zones=array_filter(explode(',',(string)$row['zona_sbt_list']));?>
                        <?php if($zones):?><div class="d-flex flex-wrap gap-1"><?php foreach($zones as $zone):?><span class="badge text-bg-info">Zona <?=e($zone)?></span><?php endforeach;?></div><?php endif;?>
                        <?php if((int)$row['zona_di_luar']>0):?><small class="d-block text-secondary mt-1"><?=(int)$row['zona_di_luar']?> data di luar diagram</small><?php elseif(!$zones):?><span class="text-secondary">Belum ada hasil</span><?php endif;?>
                    </td>
                    <td><?=status_badge($row['status'])?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-primary" href="input.php?id=<?=(int)$row['id']?>"><i class="bi bi-table"></i> Input</a>
                            <?php foreach($graphPoints[(int)$row['id']]??[] as $point):?>
                                <a class="btn btn-sm btn-outline-secondary" href="grafik.php?id=<?=(int)$point['id']?>" title="Grafik Sondir <?=e($point['nomor_urut'])?>">
                                    <i class="bi bi-graph-up"></i> S<?=e($point['nomor_urut'])?>
                                </a>
                            <?php endforeach;?>
                        </div>
                    </td>
                </tr>
            <?php endforeach;?>
            <?php if(!$rows):?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-table"></i><strong>Belum ada titik pengujian</strong><span>Buat titik sondir untuk mulai merekam data.</span></div></td></tr><?php endif;?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT.'/includes/footer.php'; ?>
