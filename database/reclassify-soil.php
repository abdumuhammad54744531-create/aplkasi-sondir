<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/config/bootstrap.php';

$schema=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$columns=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='hasil_sondir'");
$columns->execute([$schema]);
$existing=array_flip($columns->fetchAll(PDO::FETCH_COLUMN));
$definitions=[
    'qc_mpa'=>'DECIMAL(15,6) NULL AFTER qc',
    'zona_sbt'=>'TINYINT UNSIGNED NULL AFTER satuan_tekanan',
    'batas_zona'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER zona_sbt',
    'versi_klasifikasi'=>'VARCHAR(80) NULL AFTER batas_zona',
];
foreach($definitions as $name=>$definition){
    if(!isset($existing[$name]))$pdo->exec("ALTER TABLE hasil_sondir ADD COLUMN {$name} {$definition}");
}

$rows=$pdo->query('SELECT id,qc,friction_ratio,qc_mpa,zona_sbt,batas_zona,versi_klasifikasi,jenis_tanah,keterangan FROM hasil_sondir ORDER BY id')->fetchAll();
$update=$pdo->prepare('UPDATE hasil_sondir SET qc_mpa=?,zona_sbt=?,batas_zona=?,versi_klasifikasi=?,jenis_tanah=?,keterangan=?,updated_at=NOW() WHERE id=?');
$changed=0;

$pdo->beginTransaction();
try{
    foreach($rows as $row){
        $soil=sondir_soil_classification((float)$row['qc'],(float)$row['friction_ratio']);
        $strength=sondir_strength_classification((float)$row['qc'],$soil['jenis']);
        if(abs((float)$row['qc_mpa']-$soil['qc_mpa'])>.000001
            ||($row['zona_sbt']===null?null:(int)$row['zona_sbt'])!==$soil['zone_number']
            ||(bool)$row['batas_zona']!==(bool)$soil['boundary_flag']
            ||$row['versi_klasifikasi']!==$soil['versi']
            ||$row['jenis_tanah']!==$soil['jenis']
            ||$row['keterangan']!==$strength){
            $update->execute([$soil['qc_mpa'],$soil['zone_number'],(int)$soil['boundary_flag'],$soil['versi'],$soil['jenis'],$strength,$row['id']]);
            $changed++;
        }
    }
    $pdo->commit();
    echo "Klasifikasi Robertson diperbarui: {$changed} dari ".count($rows)." baris.\n";
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
