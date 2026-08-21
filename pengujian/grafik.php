<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();

function chart_number(float $value, int $decimals=2): string
{
    $formatted=number_format($value,$decimals,',','.');
    return $decimals?rtrim(rtrim($formatted,'0'),','):$formatted;
}

function chart_nice_max(float $value): float
{
    if($value<=0)return 1;
    $power=10**floor(log10($value));
    $fraction=$value/$power;
    $nice=match(true){$fraction<=1=>1,$fraction<=2=>2,$fraction<=5=>5,default=>10};
    return $nice*$power;
}

function render_profile_chart(array $rows, string $key, string $label, string $unit, string $color, int $decimals): void
{
    $width=720;$height=470;$left=72;$right=28;$top=54;$bottom=42;
    $plotWidth=$width-$left-$right;
    $plotHeight=$height-$top-$bottom;
    $maximumValue=chart_nice_max(max(array_map(fn($row)=>(float)$row[$key],$rows)));
    $deepestPoint=max(array_map(fn($row)=>(float)$row['kedalaman'],$rows));
    $maximumDepth=max(1.0,ceil($deepestPoint));
    $depthTickCount=max(5,min(10,(int)$maximumDepth));
    $points=[];
    foreach($rows as $row){
        $value=(float)$row[$key];
        $depth=(float)$row['kedalaman'];
        $x=$left+($value/$maximumValue)*$plotWidth;
        $y=$top+($depth/$maximumDepth)*$plotHeight;
        $points[]=compact('x','y','value','depth');
    }
    ?>
    <svg class="sondir-chart-svg" viewBox="0 0 <?=$width?> <?=$height?>" role="img" aria-label="<?=e($label)?> terhadap kedalaman">
        <rect x="<?=$left?>" y="<?=$top?>" width="<?=$plotWidth?>" height="<?=$plotHeight?>" class="chart-plot-background"/>
        <?php for($i=0;$i<=5;$i++): $x=$left+$plotWidth*$i/5;$value=$maximumValue*$i/5;?>
            <line x1="<?=$x?>" y1="<?=$top?>" x2="<?=$x?>" y2="<?=$top+$plotHeight?>" class="chart-grid-line"/>
            <text x="<?=$x?>" y="<?=$top-12?>" class="chart-tick-label" text-anchor="middle"><?=e(chart_number($value,$decimals))?></text>
        <?php endfor;?>
        <?php for($i=0;$i<=$depthTickCount;$i++): $y=$top+$plotHeight*$i/$depthTickCount;$depth=$maximumDepth*$i/$depthTickCount;?>
            <line x1="<?=$left?>" y1="<?=$y?>" x2="<?=$left+$plotWidth?>" y2="<?=$y?>" class="chart-grid-line"/>
            <text x="<?=$left-11?>" y="<?=$y+4?>" class="chart-tick-label" text-anchor="end"><?=e(chart_number($depth,2))?></text>
        <?php endfor;?>
        <line x1="<?=$left?>" y1="<?=$top?>" x2="<?=$left+$plotWidth?>" y2="<?=$top?>" class="chart-axis-line"/>
        <line x1="<?=$left?>" y1="<?=$top?>" x2="<?=$left?>" y2="<?=$top+$plotHeight?>" class="chart-axis-line"/>
        <text x="<?=$left+$plotWidth/2?>" y="20" class="chart-axis-title" text-anchor="middle"><?=e($label.' ('.$unit.')')?></text>
        <text x="18" y="<?=$top+$plotHeight/2?>" class="chart-axis-title" text-anchor="middle" transform="rotate(-90 18 <?=$top+$plotHeight/2?>)">Kedalaman (m)</text>
        <polyline points="<?=e(implode(' ',array_map(fn($point)=>number_format($point['x'],2,'.','').','.number_format($point['y'],2,'.',''),$points)))?>" fill="none" stroke="<?=e($color)?>" class="chart-profile-line"/>
        <?php foreach($points as $point):?>
            <circle cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="3.5" fill="#fff" stroke="<?=e($color)?>" class="chart-profile-point">
                <title>Kedalaman <?=e(chart_number($point['depth'],3))?> m · <?=e($label)?> <?=e(chart_number($point['value'],$decimals).' '.$unit)?></title>
            </circle>
        <?php endforeach;?>
    </svg>
    <?php
}

function render_combined_qc_tf_chart(array $rows): void
{
    $width=720;$height=470;$left=72;$right=28;$top=64;$bottom=56;
    $plotWidth=$width-$left-$right;
    $plotHeight=$height-$top-$bottom;
    $maximumQc=chart_nice_max(max(array_map(fn($row)=>(float)$row['qc'],$rows)));
    $maximumTf=chart_nice_max(max(array_map(fn($row)=>(float)$row['jhp'],$rows)));
    $deepestPoint=max(array_map(fn($row)=>(float)$row['kedalaman'],$rows));
    $maximumDepth=max(1.0,ceil($deepestPoint));
    $depthTickCount=max(5,min(10,(int)$maximumDepth));
    $qcPoints=[];$tfPoints=[];
    foreach($rows as $row){
        $depth=(float)$row['kedalaman'];
        $y=$top+($depth/$maximumDepth)*$plotHeight;
        $qc=(float)$row['qc'];
        $tf=(float)$row['jhp'];
        $qcPoints[]=['x'=>$left+($qc/$maximumQc)*$plotWidth,'y'=>$y,'value'=>$qc,'depth'=>$depth];
        $tfPoints[]=['x'=>$left+($tf/$maximumTf)*$plotWidth,'y'=>$y,'value'=>$tf,'depth'=>$depth];
    }
    ?>
    <svg class="sondir-chart-svg combined-qc-tf-chart" viewBox="0 0 <?=$width?> <?=$height?>" role="img" aria-label="qc dan Total Friction terhadap kedalaman">
        <rect x="<?=$left?>" y="<?=$top?>" width="<?=$plotWidth?>" height="<?=$plotHeight?>" class="chart-plot-background"/>
        <?php for($i=0;$i<=5;$i++): $x=$left+$plotWidth*$i/5;?>
            <line x1="<?=$x?>" y1="<?=$top?>" x2="<?=$x?>" y2="<?=$top+$plotHeight?>" class="chart-grid-line"/>
            <text x="<?=$x?>" y="<?=$top-12?>" class="chart-tick-label tf-axis-label" text-anchor="middle"><?=e(chart_number($maximumTf*$i/5,2))?></text>
            <text x="<?=$x?>" y="<?=$top+$plotHeight+20?>" class="chart-tick-label qc-axis-label" text-anchor="middle"><?=e(chart_number($maximumQc*$i/5,0))?></text>
        <?php endfor;?>
        <?php for($i=0;$i<=$depthTickCount;$i++): $y=$top+$plotHeight*$i/$depthTickCount;$depth=$maximumDepth*$i/$depthTickCount;?>
            <line x1="<?=$left?>" y1="<?=$y?>" x2="<?=$left+$plotWidth?>" y2="<?=$y?>" class="chart-grid-line"/>
            <text x="<?=$left-11?>" y="<?=$y+4?>" class="chart-tick-label" text-anchor="end"><?=e(chart_number($depth,2))?></text>
        <?php endfor;?>
        <line x1="<?=$left?>" y1="<?=$top?>" x2="<?=$left+$plotWidth?>" y2="<?=$top?>" class="chart-axis-line tf-axis-line"/>
        <line x1="<?=$left?>" y1="<?=$top+$plotHeight?>" x2="<?=$left+$plotWidth?>" y2="<?=$top+$plotHeight?>" class="chart-axis-line qc-axis-line"/>
        <line x1="<?=$left?>" y1="<?=$top?>" x2="<?=$left?>" y2="<?=$top+$plotHeight?>" class="chart-axis-line"/>
        <text x="<?=$left+$plotWidth/2?>" y="19" class="chart-axis-title tf-axis-title" text-anchor="middle">Total Friction, Tf (kg/cm)</text>
        <text x="<?=$left+$plotWidth/2?>" y="<?=$height-8?>" class="chart-axis-title qc-axis-title" text-anchor="middle">Tahanan Konus, qc (kg/cm²)</text>
        <text x="18" y="<?=$top+$plotHeight/2?>" class="chart-axis-title" text-anchor="middle" transform="rotate(-90 18 <?=$top+$plotHeight/2?>)">Kedalaman (m)</text>
        <polyline points="<?=e(implode(' ',array_map(fn($point)=>number_format($point['x'],2,'.','').','.number_format($point['y'],2,'.',''),$qcPoints)))?>" fill="none" stroke="#1769aa" class="chart-profile-line"/>
        <polyline points="<?=e(implode(' ',array_map(fn($point)=>number_format($point['x'],2,'.','').','.number_format($point['y'],2,'.',''),$tfPoints)))?>" fill="none" stroke="#e58a19" class="chart-profile-line"/>
        <?php foreach($qcPoints as $point):?>
            <circle cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="3.5" fill="#fff" stroke="#1769aa" class="chart-profile-point qc-profile-point"><title>Kedalaman <?=e(chart_number($point['depth'],3))?> m · qc <?=e(chart_number($point['value'],0))?> kg/cm²</title></circle>
        <?php endforeach;?>
        <?php foreach($tfPoints as $point):?>
            <circle cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="3.5" fill="#fff" stroke="#e58a19" class="chart-profile-point tf-profile-point"><title>Kedalaman <?=e(chart_number($point['depth'],3))?> m · Tf <?=e(chart_number($point['value'],2))?> kg/cm</title></circle>
        <?php endforeach;?>
    </svg>
    <?php
}

function render_sbt_chart(array $rows, array $config): void
{
    $width=900;$height=660;$left=78;$right=28;$top=25;$bottom=70;
    $plotWidth=$width-$left-$right;
    $plotHeight=$height-$top-$bottom;
    $frMax=(float)($config['display_fr_max']??10);
    $x=fn(float $fr): float=>$left+($fr/$frMax)*$plotWidth;
    $y=fn(float $qcMpa): float=>$top+(1-(log10($qcMpa)+1)/3)*$plotHeight;
    $pointRows=[];
    foreach($rows as $row){
        $classification=sondir_soil_classification((float)$row['qc'],(float)$row['friction_ratio']);
        $qcMpa=$classification['qc_mpa'];
        $fr=(float)$row['friction_ratio'];
        if($qcMpa<.1||$qcMpa>100||$fr<0||$fr>$frMax)continue;
        $pointRows[]=array_merge($row,$classification,['x'=>$x($fr),'y'=>$y($qcMpa)]);
    }
    ?>
    <svg class="sondir-chart-svg sbt-chart" viewBox="0 0 <?=$width?> <?=$height?>" role="img" aria-label="Diagram klasifikasi tanah Robertson 12 zona">
        <defs><clipPath id="sbt-plot"><rect x="<?=$left?>" y="<?=$top?>" width="<?=$plotWidth?>" height="<?=$plotHeight?>"/></clipPath></defs>
        <rect x="<?=$left?>" y="<?=$top?>" width="<?=$plotWidth?>" height="<?=$plotHeight?>" class="chart-plot-background"/>
        <g clip-path="url(#sbt-plot)">
            <?php foreach($config['zones'] as $zone):
                $polygon=implode(' ',array_map(fn($point)=>number_format($x((float)$point[0]),2,'.','').','.number_format($y((float)$point[1]),2,'.',''),$zone['polygon']));?>
                <polygon points="<?=e($polygon)?>" fill="<?=e($zone['color'])?>" class="sbt-zone"><title>Zona <?=e($zone['zone'])?> · <?=e($zone['name_id'])?></title></polygon>
            <?php endforeach;?>
            <?php foreach([.1,.2,.3,.4,.5,.6,.7,.8,.9,1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100] as $tick):?>
                <line x1="<?=$left?>" y1="<?=$y($tick)?>" x2="<?=$left+$plotWidth?>" y2="<?=$y($tick)?>" class="<?=in_array($tick,[.1,1,10,100],true)?'sbt-grid-major':'sbt-grid-minor'?>"/>
            <?php endforeach;?>
            <?php for($tick=0;$tick<=$frMax;$tick+=.5):?>
                <line x1="<?=$x($tick)?>" y1="<?=$top?>" x2="<?=$x($tick)?>" y2="<?=$top+$plotHeight?>" class="<?=floor($tick)===$tick?'sbt-grid-major':'sbt-grid-minor'?>"/>
            <?php endfor;?>
            <?php foreach($config['zones'] as $zone):?>
                <text x="<?=$x((float)$zone['label'][0])?>" y="<?=$y((float)$zone['label'][1])+5?>" class="sbt-zone-number" text-anchor="middle"><?=e($zone['zone'])?></text>
            <?php endforeach;?>
            <?php if($pointRows):?>
                <polyline points="<?=e(implode(' ',array_map(fn($point)=>number_format($point['x'],2,'.','').','.number_format($point['y'],2,'.',''),$pointRows)))?>" class="sbt-point-line"/>
                <?php foreach($pointRows as $point):?>
                    <circle cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="5" fill="<?=e($point['warna'])?>" class="sbt-point"><title><?=e(chart_number((float)$point['kedalaman'],2))?> m · qc <?=e(chart_number((float)$point['qc_mpa'],3))?> MPa · FR <?=e(chart_number((float)$point['friction_ratio'],2))?>% · Zona <?=e($point['zone_number']??'-')?> · <?=e($point['jenis'])?></title></circle>
                <?php endforeach;?>
            <?php endif;?>
        </g>
        <rect x="<?=$left?>" y="<?=$top?>" width="<?=$plotWidth?>" height="<?=$plotHeight?>" fill="none" class="chart-axis-line"/>
        <?php for($tick=0;$tick<=$frMax;$tick++):?><text x="<?=$x($tick)?>" y="<?=$top+$plotHeight+23?>" class="chart-tick-label" text-anchor="middle"><?=$tick?></text><?php endfor;?>
        <?php foreach([.1,1,10,100] as $tick):?><text x="<?=$left-12?>" y="<?=$y($tick)+4?>" class="chart-tick-label" text-anchor="end"><?=e(chart_number($tick,1))?></text><?php endforeach;?>
        <text x="<?=$left+$plotWidth/2?>" y="<?=$height-17?>" class="chart-axis-title" text-anchor="middle">Friction Ratio, FR (%)</text>
        <text x="20" y="<?=$top+$plotHeight/2?>" class="chart-axis-title" text-anchor="middle" transform="rotate(-90 20 <?=$top+$plotHeight/2?>)">Tahanan Konus, qc (MPa)</text>
    </svg>
    <div class="sbt-legend">
        <?php foreach($config['zones'] as $zone):?><span><i style="background:<?=e($zone['color'])?>"></i><b><?=e($zone['zone'])?></b> <?=e($zone['name_id'])?></span><?php endforeach;?>
    </div>
    <?php
}

$id=(int)($_GET['id']??0);
$q=$pdo->prepare('SELECT t.kode_titik,t.nama_titik,p.nama_proyek FROM titik_sondir t JOIN proyek p ON p.id=t.proyek_id WHERE t.id=?');
$q->execute([$id]);
$t=$q->fetch();
if(!$t){http_response_code(404);exit('Data tidak ditemukan.');}

$q=$pdo->prepare('SELECT kedalaman,qc,fs,jhp,friction_ratio FROM hasil_sondir WHERE titik_sondir_id=? ORDER BY kedalaman');
$q->execute([$id]);
$rows=$q->fetchAll();
$soilChartConfig=sondir_soil_chart_config();
$pageTitle='Grafik '.$t['kode_titik'];
require APP_ROOT.'/includes/header.php';
?>
<link rel="stylesheet" href="<?=url('assets/css/chart.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/chart.css')?>">

<div class="page-heading">
    <div><a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Pengujian</a><h2 class="mt-2 mb-0"><?=e($t['nama_titik']?:$t['kode_titik'])?></h2><p><?=e($t['kode_titik'].' · '.$t['nama_proyek'])?></p></div>
    <button onclick="print()" class="btn btn-outline-primary no-print"><i class="bi bi-printer"></i> Cetak grafik</button>
</div>

<div class="alert alert-info py-2 no-print"><i class="bi bi-arrows-expand-vertical me-2"></i>Sumbu vertikal menunjukkan <strong>kedalaman (m)</strong> dan bertambah ke arah bawah. Pada grafik gabungan, <strong>Tf berada di atas</strong> dan <strong>qc berada di bawah</strong>.</div>

<?php if(!$rows):?>
    <div class="card"><div class="empty-state"><i class="bi bi-graph-up"></i><strong>Belum ada data grafik</strong><span>Isi dan simpan data pengujian terlebih dahulu.</span></div></div>
<?php else:?>
<div class="row g-3 sondir-chart-grid">
    <div class="col-xl-6">
        <div class="card sondir-chart-card h-100">
            <div class="card-header bg-white">
                <div><span class="eyebrow">qc + Tf</span><h5 class="mb-0">Tahanan Konus & Total Friction</h5></div>
                <div class="chart-series-legend"><span class="series-qc"><i></i>qc bawah</span><span class="series-tf"><i></i>Tf atas</span></div>
            </div>
            <div class="card-body"><?php render_combined_qc_tf_chart($rows);?></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card sondir-chart-card h-100">
            <div class="card-header bg-white">
                <div><span class="eyebrow">FR</span><h5 class="mb-0">Friction Ratio</h5></div>
                <span class="chart-axis-hint">X: FR · Y: Kedalaman</span>
            </div>
            <div class="card-body"><?php render_profile_chart($rows,'friction_ratio','FR','%', '#8b4fd6',2);?></div>
        </div>
    </div>
    <div class="col-12">
        <div class="card sondir-chart-card">
            <div class="card-header bg-white">
                <div><span class="eyebrow">KLASIFIKASI TANAH</span><h5 class="mb-0">Diagram Robertson SBT — 12 Zona</h5></div>
                <span class="chart-axis-hint">X: FR (%) · Y: qc (MPa, log)</span>
            </div>
            <div class="card-body"><?php render_sbt_chart($rows,$soilChartConfig);?></div>
            <div class="card-footer bg-white small text-secondary">Jenis tanah pada tabel input dan data tersimpan ditentukan dari zona titik pada diagram ini. qc dikonversi otomatis dari kg/cm² ke MPa (1 kg/cm² = <?=KG_CM2_TO_MPA?> MPa).</div>
        </div>
    </div>
</div>
<?php endif;?>

<?php require APP_ROOT.'/includes/footer.php'; ?>
