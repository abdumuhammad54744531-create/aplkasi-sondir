<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();
require __DIR__.'/_common.php';

extract(foundation_context($pdo));
$widths=[];
for($i=5;$i<=20;$i++)$widths[]=$i/10;
$methodDefinitions=[
    'meyerhof'=>[
        'name'=>'Meyerhof',
        'year'=>'1956',
        'description'=>'Korelasi langsung daya dukung izin dari nilai rata-rata tahanan konus qc.',
        'class'=>'theory-meyerhof',
    ],
    'schmertmann'=>[
        'name'=>'Schmertmann',
        'year'=>'1978',
        'description'=>'Korelasi qc untuk pondasi bujur sangkar pada lempung/lanau atau pasir.',
        'class'=>'theory-schmertmann',
    ],
];
$submitted=isset($_GET['tampilkan']);
$requestedMethods=array_values(array_intersect(array_keys($methodDefinitions),(array)($_GET['metode']??[])));
$selectedMethods=$submitted?$requestedMethods:array_keys($methodDefinitions);
$calculationQuery=['tampilkan'=>1,'metode'=>$selectedMethods];
$pageTitle='Daya Dukung Pondasi Dangkal';
require APP_ROOT.'/includes/header.php';
?>
<link rel="stylesheet" href="<?=url('assets/css/foundation.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/foundation.css')?>">

<div class="page-heading">
    <div><span class="eyebrow">Daya Dukung Pondasi</span><h2>Pondasi Dangkal</h2><p>Perhitungan berbasis data sondir dengan korelasi Meyerhof dan Schmertmann.</p></div>
    <a class="btn btn-outline-primary" href="<?=url('pengujian/index.php')?>"><i class="bi bi-table me-1"></i> Data Pengujian</a>
</div>

<?php foundation_project_bar($projects,$projectId,'dangkal',$calculationQuery); ?>
<?php foundation_tabs($points,$activePoint,'dangkal',$projectId,$calculationQuery); ?>

<?php if(!$activePoint):?>
    <div class="card"><div class="empty-state"><i class="bi bi-bounding-box"></i><strong>Belum ada titik sondir</strong><span>Buat titik sondir dan isi data pengujian terlebih dahulu.</span></div></div>
<?php else:?>
<div class="card foundation-method-config mb-3">
    <div class="card-header bg-white py-3"><span class="eyebrow">Metode Perhitungan</span><h5 class="mb-0">Pilih korelasi data sondir</h5></div>
    <div class="card-body">
        <form method="get">
            <input type="hidden" name="tampilkan" value="1">
            <input type="hidden" name="proyek_id" value="<?=$projectId?>">
            <input type="hidden" name="id" value="<?=(int)$activePoint['id']?>">
            <div class="foundation-method-grid foundation-method-grid-two">
                <?php foreach($methodDefinitions as $key=>$method):?>
                <label class="foundation-method-option <?=$method['class']?>">
                    <input class="form-check-input" type="checkbox" name="metode[]" value="<?=$key?>" <?=in_array($key,$selectedMethods,true)?'checked':''?>>
                    <span><strong><?=$method['name']?> (<?=$method['year']?>)</strong><small><?=$method['description']?></small></span>
                </label>
                <?php endforeach;?>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                <small class="text-secondary"><i class="bi bi-database-check me-1"></i>Seluruh nilai dihitung otomatis dari qc dan jenis tanah pada tab Sondir aktif.</small>
                <button class="btn btn-primary px-4"><i class="bi bi-eye me-1"></i> Tampilkan tabel</button>
            </div>
        </form>
    </div>
</div>

<div class="card foundation-card mb-3">
    <?php foundation_point_summary($activePoint); ?>
    <?php if(!$rows):?>
        <div class="empty-state"><i class="bi bi-table"></i><strong>Data pengujian belum tersedia</strong><span>Isi dan simpan data qc serta jenis tanah pada tab Sondir ini.</span></div>
    <?php elseif(!$selectedMethods):?>
        <div class="empty-state"><i class="bi bi-ui-checks-grid"></i><strong>Belum ada metode dipilih</strong><span>Centang sedikitnya satu metode kemudian klik Tampilkan tabel.</span></div>
    <?php else:?>
        <div class="foundation-assumption-bar"><i class="bi bi-bounding-box me-1"></i>Pondasi bujur sangkar B = L. Hasil akhir berupa beban izin dalam kN dan dihitung untuk lebar 0,50–2,00 m.</div>
    <?php endif;?>
</div>

<?php if($rows&&$selectedMethods):?>
    <?php foreach($selectedMethods as $methodKey): $method=$methodDefinitions[$methodKey];?>
    <section class="card foundation-theory-card <?=$method['class']?> mb-4">
        <div class="foundation-theory-header foundation-formula-header">
            <div><span class="eyebrow">Korelasi <?=$method['year']?></span><h4 class="mb-1"><?=$method['name']?></h4><p class="mb-0"><?=$method['description']?></p></div>
            <?php if($methodKey==='meyerhof'):?>
            <div class="foundation-formula-list" aria-label="Rumus Meyerhof 1956">
                <div><span>B ≤ 1,20 m</span><strong>q<sub>a</sub> = q̄<sub>c</sub> / 30</strong></div>
                <div><span>B &gt; 1,20 m</span><strong>q<sub>a</sub> = q̄<sub>c</sub> / 50 × ((B + 0,30) / B)²</strong></div>
            </div>
            <?php else:?>
            <div class="foundation-formula-list" aria-label="Rumus Schmertmann 1978">
                <div><span>Lempung / lanau</span><strong>q<sub>u</sub> = 5 + 0,34 q<sub>c</sub></strong></div>
                <div><span>Pasir</span><strong>q<sub>u</sub> = 48 − 0,009(300 − q<sub>c</sub>)<sup>1,5</sup></strong></div>
                <div><span>Izin dan syarat</span><strong>q<sub>a</sub> = q<sub>u</sub> / 3; Df/B ≤ 1,5</strong></div>
            </div>
            <?php endif;?>
        </div>
        <?php if($methodKey==='meyerhof'):?>
            <div class="foundation-method-explanation"><i class="bi bi-info-circle"></i>q̄c adalah rata-rata qc pada zona 0,50 m di atas sampai 1,00 m di bawah dasar pondasi. qa dalam kg/cm² dikonversi menjadi beban izin kN.</div>
        <?php else:?>
            <div class="foundation-method-explanation"><i class="bi bi-info-circle"></i>Klasifikasi lempung mencakup Lempung dan Lanau. Baris berarsir dengan tanda “—” tidak memenuhi syarat Df/B ≤ 1,5.</div>
        <?php endif;?>
        <div class="table-responsive foundation-table-wrap">
        <table class="table table-bordered foundation-table foundation-shallow-table mb-0">
            <thead>
                <tr><th colspan="<?=($methodKey==='schmertmann'?3:2)+count($widths)?>" class="foundation-sondir-title"><?=$method['name']?> (<?=$method['year']?>) · SONDIR <?=e($activePoint['nomor_urut'])?> · <?=e($activePoint['kode_titik'])?></th></tr>
                <tr>
                    <th rowspan="2"><span>Df</span><small>m</small></th>
                    <?php if($methodKey==='meyerhof'):?>
                        <th rowspan="2"><span>q̄c</span><small>kg/cm²</small></th>
                    <?php else:?>
                        <th rowspan="2"><span>qc</span><small>kg/cm²</small></th>
                        <th rowspan="2"><span>Jenis</span><small>tanah</small></th>
                    <?php endif;?>
                    <th colspan="<?=count($widths)?>" class="foundation-title">P izin (kN) untuk lebar pondasi B = L (m)</th>
                </tr>
                <tr><?php foreach($widths as $width):?><th><?=foundation_number($width,1)?></th><?php endforeach;?></tr>
            </thead>
            <tbody>
                <?php foreach($rows as $row): $depth=(float)$row['df'];$qc=(float)$row['qc'];?>
                <tr>
                    <td><?=foundation_number($depth,2)?></td>
                    <?php if($methodKey==='meyerhof'): $average=foundation_average_qc($rows,$depth);?>
                        <td title="<?=e($average['count'].' data, zona '.foundation_number($average['from'],2).'–'.foundation_number($average['to'],2).' m')?>"><?=foundation_number($average['qc'],2)?></td>
                        <?php foreach($widths as $width): $capacity=foundation_meyerhof_1956($average['qc'],$width);?>
                            <td class="capacity-cell"><?=number_format($capacity['p_allow'],2,',','.')?></td>
                        <?php endforeach;?>
                    <?php else: $soilGroup=foundation_soil_group((string)$row['jenis_tanah']);?>
                        <td><?=foundation_number($qc,2)?></td>
                        <td class="soil-group-cell"><span class="badge <?=$soilGroup==='lempung'?'text-bg-primary':'text-bg-warning'?>"><?=ucfirst($soilGroup)?></span></td>
                        <?php foreach($widths as $width): $capacity=foundation_schmertmann_1978($qc,(string)$row['jenis_tanah'],$depth,$width,3);?>
                            <td class="capacity-cell <?=$capacity['valid']?'':'capacity-invalid'?>" title="<?=$capacity['valid']?'Df/B = '.foundation_number((float)$capacity['ratio'],2):'Tidak memenuhi Df/B ≤ 1,5'?>"><?=$capacity['p_allow']===null?'—':number_format($capacity['p_allow'],2,',','.')?></td>
                        <?php endforeach;?>
                    <?php endif;?>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
        </div>
        <div class="foundation-note"><i class="bi bi-journal-check"></i><span>
            <?php if($methodKey==='meyerhof'):?>
                Meyerhof (1956): korelasi CPT untuk daya dukung izin berdasarkan qc rata-rata dan ukuran pondasi.
            <?php else:?>
                Schmertmann (1978): korelasi qc untuk pondasi bujur sangkar; faktor keamanan yang digunakan adalah 3.
            <?php endif;?>
        </span></div>
    </section>
    <?php endforeach;?>
<?php endif;?>
<?php endif;?>

<?php require APP_ROOT.'/includes/footer.php'; ?>
