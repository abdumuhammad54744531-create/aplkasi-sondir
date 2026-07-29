<?php
declare(strict_types=1);

function foundation_context(PDO $pdo): array
{
    $projects=$pdo->query(
        "SELECT p.id,p.kode_proyek,p.nama_proyek,COUNT(t.id) jumlah_titik
         FROM proyek p
         JOIN titik_sondir t ON t.proyek_id=p.id
         GROUP BY p.id,p.kode_proyek,p.nama_proyek
         ORDER BY p.created_at DESC,p.id DESC"
    )->fetchAll();

    $projectId=max(0,(int)($_GET['proyek_id']??0));
    $requestedPointId=max(0,(int)($_GET['id']??0));
    if(!$projectId&&$requestedPointId){
        $q=$pdo->prepare('SELECT proyek_id FROM titik_sondir WHERE id=?');
        $q->execute([$requestedPointId]);
        $projectId=(int)$q->fetchColumn();
    }
    if(!$projectId&&$projects)$projectId=(int)$projects[0]['id'];

    $points=[];
    if($projectId){
        $q=$pdo->prepare(
            'SELECT t.id,t.parent_id,t.kode_titik,t.nama_titik,t.nomor_urut,t.alamat_lokasi,t.status,
                    p.nama_proyek,p.kode_proyek,
                    (SELECT COUNT(*) FROM hasil_sondir h WHERE h.titik_sondir_id=t.id) jumlah_data
             FROM titik_sondir t
             JOIN proyek p ON p.id=t.proyek_id
             WHERE t.proyek_id=?
             ORDER BY COALESCE(t.parent_id,t.id),t.nomor_urut,t.id'
        );
        $q->execute([$projectId]);
        $points=$q->fetchAll();
    }

    $activePoint=null;
    foreach($points as $point){
        if((int)$point['id']===$requestedPointId){
            $activePoint=$point;
            break;
        }
    }
    if(!$activePoint&&$points)$activePoint=$points[0];

    $rows=[];
    if($activePoint){
        $q=$pdo->prepare(
            'SELECT kedalaman AS df,qc,jhp AS tf,jenis_tanah,keterangan
             FROM hasil_sondir
             WHERE titik_sondir_id=?
             ORDER BY kedalaman,nomor'
        );
        $q->execute([(int)$activePoint['id']]);
        $rows=$q->fetchAll();
    }

    return compact('projects','projectId','points','activePoint','rows');
}

function foundation_number(float $value, int $decimals=2): string
{
    $formatted=number_format($value,$decimals,',','.');
    if($decimals>0)$formatted=rtrim(rtrim($formatted,'0'),',');
    return $formatted;
}

function foundation_input_number(float $value): string
{
    return rtrim(rtrim(number_format($value,3,'.',''),'0'),'.');
}

function foundation_capacity(float $qc, float $tf, float $size, bool $circular): float
{
    $dimensionCm=$size*100;
    $area=$circular?M_PI*$dimensionCm**2/4:$dimensionCm**2;
    $perimeter=$circular?M_PI*$dimensionCm:4*$dimensionCm;
    return (($qc*$area/3)+($tf*$perimeter/5))/100;
}

function foundation_average_qc(array $rows, float $foundationDepth): array
{
    $minimumDepth=max(0,$foundationDepth-0.50);
    $maximumDepth=$foundationDepth+1.00;
    $values=[];
    foreach($rows as $row){
        $depth=(float)$row['df'];
        if($depth>=$minimumDepth&&$depth<=$maximumDepth)$values[]=(float)$row['qc'];
    }
    if(!$values)return ['qc'=>0.0,'count'=>0,'from'=>$minimumDepth,'to'=>$maximumDepth];

    return [
        'qc'=>array_sum($values)/count($values),
        'count'=>count($values),
        'from'=>$minimumDepth,
        'to'=>$maximumDepth,
    ];
}

function foundation_meyerhof_1956(float $averageQc, float $width): array
{
    $qAllow=$width<=1.20
        ?$averageQc/30
        :($averageQc/50)*(($width+0.30)/$width)**2;
    $load=$qAllow*98.0665*$width*$width;

    return ['q_allow'=>$qAllow,'p_allow'=>$load];
}

function foundation_soil_group(string $soilType): string
{
    return str_starts_with($soilType,'Lempung')||str_starts_with($soilType,'Lanau')?'lempung':'pasir';
}

function foundation_schmertmann_1978(float $qc, string $soilType, float $foundationDepth, float $width, float $safetyFactor=3.0): array
{
    $soilGroup=foundation_soil_group($soilType);
    $qUltimate=$soilGroup==='lempung'
        ?5+0.34*$qc
        :48-0.009*max(0,300-$qc)**1.5;
    $qUltimate=max(0,$qUltimate);
    $valid=$width>0&&$foundationDepth/$width<=1.5;
    $qAllow=$qUltimate/max(1,$safetyFactor);
    $load=$valid?$qAllow*98.0665*$width*$width:null;

    return [
        'soil_group'=>$soilGroup,
        'q_ultimate'=>$qUltimate,
        'q_allow'=>$qAllow,
        'p_allow'=>$load,
        'valid'=>$valid,
        'ratio'=>$width>0?$foundationDepth/$width:null,
    ];
}

function foundation_project_bar(array $projects, int $projectId, string $target, array $extraQuery=[]): void
{
    $targetQuery=http_build_query(array_merge(['proyek_id'=>$projectId],$extraQuery));
    ?>
    <div class="card foundation-filter mb-3"><div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <?php foreach($extraQuery as $key=>$value):?>
                <?php if(is_array($value)): foreach($value as $item):?>
                    <input type="hidden" name="<?=e($key)?>[]" value="<?=e($item)?>">
                <?php endforeach; else:?>
                    <input type="hidden" name="<?=e($key)?>" value="<?=e($value)?>">
                <?php endif;?>
            <?php endforeach;?>
            <div class="col-lg-7">
                <label class="form-label">Proyek</label>
                <select class="form-select" name="proyek_id" onchange="this.form.submit()">
                    <?php if(!$projects):?><option value="">Belum ada proyek dengan titik sondir</option><?php endif;?>
                    <?php foreach($projects as $project):?>
                        <option value="<?=(int)$project['id']?>" <?=$projectId===(int)$project['id']?'selected':''?>>
                            <?=e($project['kode_proyek'].' - '.$project['nama_proyek'].' · '.$project['jumlah_titik'].' titik')?>
                        </option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Tampilkan</button></div>
            <div class="col-lg text-lg-end">
                <a class="btn <?=$target==='tiang'?'btn-primary':'btn-light'?>" href="<?=url('daya-dukung/tiang.php?proyek_id='.$projectId)?>">Pondasi Tiang</a>
                <a class="btn <?=$target==='dangkal'?'btn-primary':'btn-light'?>" href="<?=url('daya-dukung/dangkal.php?'.$targetQuery)?>">Pondasi Dangkal</a>
            </div>
        </form>
    </div></div>
    <?php
}

function foundation_tabs(array $points, ?array $activePoint, string $target, int $projectId, array $extraQuery=[]): void
{
    if(!$points)return;
    ?>
    <ul class="nav nav-tabs foundation-tabs mb-3">
        <?php foreach($points as $point):?>
            <?php $query=http_build_query(array_merge(['proyek_id'=>$projectId,'id'=>(int)$point['id']],$extraQuery));?>
            <li class="nav-item">
                <a class="nav-link <?=(int)$point['id']===(int)($activePoint['id']??0)?'active':''?>"
                   href="<?=url('daya-dukung/'.$target.'.php?'.$query)?>">
                    <span>Sondir <?=e($point['nomor_urut'])?></span>
                    <small><?=e($point['kode_titik'])?></small>
                </a>
            </li>
        <?php endforeach;?>
    </ul>
    <?php
}

function foundation_point_summary(?array $activePoint): void
{
    if(!$activePoint)return;
    ?>
    <div class="foundation-point-summary">
        <div>
            <span class="eyebrow">Data sumber</span>
            <h5 class="mb-1"><?=e($activePoint['nama_titik']?:'Sondir '.$activePoint['nomor_urut'])?></h5>
            <small class="text-secondary"><?=e($activePoint['kode_titik'])?> · <?=e($activePoint['alamat_lokasi']?:'Alamat belum diisi')?></small>
        </div>
        <div class="text-end">
            <?=status_badge($activePoint['status'])?>
            <div class="small text-secondary mt-1"><?=(int)$activePoint['jumlah_data']?> baris pengujian</div>
        </div>
    </div>
    <?php
}
