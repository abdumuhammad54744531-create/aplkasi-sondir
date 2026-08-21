<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/MechanicalSondirCalculator.php';
require dirname(__DIR__).'/includes/SondirValidationService.php';

function close_to(float $actual,float $expected,float $tolerance,string $label): void{if(abs($actual-$expected)>$tolerance)throw new RuntimeException("$label gagal: $actual != $expected");}

$tool=['luas_piston'=>20.0,'luas_konus'=>10.0,'luas_selimut'=>150.0,'faktor_kalibrasi_konus'=>1.0,'faktor_kalibrasi_total'=>1.0,'interval_standar'=>.2,'satuan_kapasitas'=>'kg/cm2','kapasitas_maksimum'=>250.0];
$calculator=new MechanicalSondirCalculator();$first=$calculator->calculate($tool,.2,10,15);$second=$calculator->calculate($tool,.4,20,24,$first['tf']);
close_to($first['kw'],5,1e-9,'Kw');close_to($first['qc'],20,1e-9,'qc');close_to($first['fs'],2/3,1e-9,'fs');close_to($first['rf'],10/3,1e-9,'Rf');close_to($first['tf'],40/3,1e-9,'Tf');close_to($first['qc_mpa'],1.96133,1e-6,'qc MPa');close_to(UnitConversionService::convert(1,'MPa','kPa'),1000,1e-9,'MPa ke kPa');close_to(UnitConversionService::convert(1,'ton/m2','kPa'),9.80665,1e-9,'ton/m2 ke kPa');close_to(UnitConversionService::convert(1,'kPa/100','MPa'),.1,1e-9,'kPa/100 ke MPa');
$validator=new SondirValidationService();$issues=$validator->validateReading($second,$first,$tool);if($issues)throw new RuntimeException('Dataset valid menghasilkan issue: '.json_encode($issues));$bad=$calculator->calculate($tool,.4,10,5,$second['tf']);$badIssues=$validator->validateReading($bad,$second,$tool);if(SondirValidationService::status($badIssues)!=='tidak_valid')throw new RuntimeException('Tw<Cw tidak ditandai error fatal.');
echo "OK - perhitungan manual qc, fs, Rf, Tf, konversi unit, dan validasi lulus.\n";

