<?php
declare(strict_types=1);

require dirname(__DIR__).'/config/bootstrap.php';
require APP_ROOT.'/laporan/_report.php';

$pointId=(int)($pdo->query('SELECT id FROM titik_sondir ORDER BY id LIMIT 1')->fetchColumn()?:0);
if($pointId<1)throw new RuntimeException('Titik sondir untuk pengujian tidak tersedia.');
$query=$pdo->prepare('SELECT kedalaman,qc,qc_mpa,friction_ratio FROM hasil_sondir WHERE titik_sondir_id=? ORDER BY kedalaman');
$query->execute([$pointId]);$rows=$query->fetchAll();
if(!$rows)throw new RuntimeException('Data sondir untuk pengujian tidak tersedia.');

$svg=function(bool $showLine,string $style)use($rows):string{
    $uri=report_sbt_chart_uri($rows,$showLine,$style);
    return (string)base64_decode(substr($uri,strpos($uri,',')+1),true);
};
$solid=$svg(true,'solid');$dashed=$svg(true,'dashed');$none=$svg(false,'solid');
$valid=count(array_filter($rows,function(array $row):bool{$qc=(float)($row['qc_mpa']??((float)$row['qc']*.0980665));$fr=(float)$row['friction_ratio'];return $qc>=.1&&$qc<=100&&$fr>=0&&$fr<=10;}));

$checks=[
    [substr_count($solid,'class="depth-label"')===$valid,'Semua titik valid harus memiliki label kedalaman.'],
    [str_contains($solid,' m</text>'),'Satuan kedalaman meter harus tampil.'],
    [str_contains($solid,'<polyline')&&!str_contains($solid,'stroke-dasharray="9 6"'),'Garis penuh tidak sesuai.'],
    [str_contains($dashed,'<polyline')&&str_contains($dashed,'stroke-dasharray="9 6"'),'Garis putus-putus tidak sesuai.'],
    [!str_contains($none,'<polyline'),'Garis harus hilang ketika dinonaktifkan.'],
];
foreach($checks as [$passed,$message])if(!$passed)throw new RuntimeException($message);
echo 'OK - label kedalaman dan pilihan garis Diagram SBT lulus.'.PHP_EOL;
