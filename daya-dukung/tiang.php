<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();
require __DIR__.'/_common.php';

extract(foundation_context($pdo));
$sizes=[.20,.25,.30,.35];
$pageTitle='Daya Dukung Pondasi Tiang';
require APP_ROOT.'/includes/header.php';
?>
<link rel="stylesheet" href="<?=url('assets/css/foundation.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/foundation.css')?>">

<div class="page-heading">
    <div><span class="eyebrow">Daya Dukung Pondasi</span><h2>Pondasi Tiang</h2><p>Daya dukung izin satu tiang berdasarkan nilai qc dan Tf setiap titik sondir.</p></div>
    <a class="btn btn-outline-primary" href="<?=url('pengujian/index.php')?>"><i class="bi bi-table me-1"></i> Data Pengujian</a>
</div>

<?php foundation_project_bar($projects,$projectId,'tiang'); ?>
<?php foundation_tabs($points,$activePoint,'tiang',$projectId); ?>

<?php if(!$activePoint):?>
    <div class="card"><div class="empty-state"><i class="bi bi-building"></i><strong>Belum ada titik sondir</strong><span>Buat titik sondir dan isi data pengujian terlebih dahulu.</span></div></div>
<?php else:?>
<div class="card foundation-card">
    <?php foundation_point_summary($activePoint); ?>
    <?php if(!$rows):?>
        <div class="empty-state"><i class="bi bi-table"></i><strong>Data pengujian belum tersedia</strong><span>Isi dan simpan data qc serta Tf pada tab Sondir ini.</span></div>
    <?php else:?>
    <div class="table-responsive foundation-table-wrap">
        <table class="table table-bordered foundation-table foundation-pile-table mb-0">
            <thead>
                <tr>
                    <th rowspan="3"><span>Df</span><small>m</small></th>
                    <th rowspan="3"><span>qc</span><small>kg/cm²</small></th>
                    <th rowspan="3"><span>Tf</span><small>kg/cm</small></th>
                    <th colspan="8" class="foundation-title">Daya dukung izin satu tiang (kN)</th>
                </tr>
                <tr>
                    <th colspan="4" class="foundation-group mini-group">Mini Pile kotak (m)</th>
                    <th colspan="4" class="foundation-group strauss-group">Strauss Pile (m)</th>
                </tr>
                <tr>
                    <?php foreach($sizes as $size):?><th><?=foundation_number($size,2)?></th><?php endforeach;?>
                    <?php foreach($sizes as $size):?><th><?=foundation_number($size,2)?></th><?php endforeach;?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $row): $qc=(float)$row['qc'];$tf=(float)$row['tf'];?>
                <tr>
                    <td><?=foundation_number((float)$row['df'],2)?></td>
                    <td><?=foundation_number($qc,2)?></td>
                    <td><?=foundation_number($tf,2)?></td>
                    <?php foreach($sizes as $size):?><td class="capacity-cell mini-cell"><?=number_format(foundation_capacity($qc,$tf,$size,false),2,',','.')?></td><?php endforeach;?>
                    <?php foreach($sizes as $size):?><td class="capacity-cell strauss-cell"><?=number_format(foundation_capacity($qc,$tf,$size,true),2,',','.')?></td><?php endforeach;?>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <div class="foundation-note"><i class="bi bi-info-circle"></i><span><strong>Mini pile:</strong> penampang kotak. <strong>Strauss pile:</strong> penampang lingkaran. Faktor keamanan tahanan ujung = 3 dan tahanan selimut = 5.</span></div>
    <?php endif;?>
</div>
<?php endif;?>

<?php require APP_ROOT.'/includes/footer.php'; ?>
