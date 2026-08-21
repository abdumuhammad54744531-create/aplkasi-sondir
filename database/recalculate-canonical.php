<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_once APP_ROOT.'/includes/UnitConversionService.php';

$rows=$pdo->query('SELECT h.id,h.qc,h.fs,h.friction_ratio,a.satuan_kapasitas FROM hasil_sondir h JOIN titik_sondir t ON t.id=h.titik_sondir_id JOIN alat_sondir a ON a.id=t.alat_id ORDER BY h.id')->fetchAll();
$update=$pdo->prepare('UPDATE hasil_sondir SET qc_kpa=?,qc_mpa=?,fs_kpa=?,fs_mpa=?,zona_sbt=?,batas_zona=?,versi_klasifikasi=?,jenis_tanah=?,keterangan=?,calculation_method=COALESCE(calculation_method,?),calculation_version=COALESCE(calculation_version,?) WHERE id=?');
$pdo->beginTransaction();try{foreach($rows as $row){$unit=(string)$row['satuan_kapasitas'];$qc=(float)$row['qc'];$fs=(float)$row['fs'];$qcKpa=UnitConversionService::toKpa($qc,$unit);$qcMpa=$qcKpa/1000;$fsKpa=UnitConversionService::toKpa($fs,$unit);$soil=sondir_soil_classification_mpa($qcMpa,(float)$row['friction_ratio']);$strength=sondir_strength_classification($qc,$soil['jenis']);$update->execute([$qcKpa,$qcMpa,$fsKpa,$fsKpa/1000,$soil['zone_number'],(int)$soil['boundary_flag'],$soil['versi'],$soil['jenis'],$strength,'Legacy mechanical recalculated to canonical unit','mechanical-v1.0',(int)$row['id']]);}$pdo->commit();echo 'Canonical unit dan SBT dihitung ulang untuk '.count($rows)." reading tanpa mengubah raw Cw/Tw.\n";}catch(Throwable $e){$pdo->rollBack();throw $e;}

