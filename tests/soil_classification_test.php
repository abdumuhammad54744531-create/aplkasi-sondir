<?php
declare(strict_types=1);

define('APP_ROOT',dirname(__DIR__));
require APP_ROOT.'/includes/SoilBehaviorClassifier.php';
require APP_ROOT.'/includes/functions.php';

$checks=0;
$check=function(bool $condition,string $message)use(&$checks): void {
    $checks++;
    if(!$condition)throw new RuntimeException($message);
};

$classifier=new SoilClassifier(APP_ROOT.'/config/soil_classification_zones.json');
$check($classifier->classify(1.55,5.85)['zone_number']===3,'Titik acuan Zona 3 gagal.');
$check($classifier->classify(.30,.70)['zone_number']===1,'Titik acuan Zona 1 gagal.');
$check($classifier->classify(.19,6.70)['zone_number']===2,'Titik acuan Zona 2 gagal.');

$converted=sondir_soil_classification(1.55/KG_CM2_TO_MPA,5.85);
$check($converted['zone_number']===3,'Konversi kg/cm2 ke MPa tidak menghasilkan Zona 3.');
$check(abs($converted['qc_mpa']-1.55)<.0001,'Nilai qc hasil konversi tidak tepat.');
$check($converted['versi']==='robertson-1986-digitized-v2.1-fr10','Versi klasifikasi tidak ikut disimpan.');

$config=sondir_soil_chart_config();
foreach($config['zones'] as $zone){
    $result=$classifier->classify((float)$zone['label'][1],(float)$zone['label'][0]);
    $check($result['zone_number']===$zone['zone'],'Label Zona '.$zone['zone'].' berada di zona yang salah.');
}

echo "OK - {$checks} pemeriksaan klasifikasi Robertson lulus.\n";
