<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Sesi berakhir.']);
    exit;
}
$query=trim((string)($_GET['q']??''));
if(mb_strlen($query)<3||mb_strlen($query)>200){
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Kata pencarian harus 3 sampai 200 karakter.']);
    exit;
}
try{
    $url='https://nominatim.openstreetmap.org/search?'.http_build_query([
        'format'=>'jsonv2',
        'limit'=>5,
        'countrycodes'=>'id',
        'accept-language'=>'id',
        'q'=>$query,
    ]);
    $curl=curl_init($url);
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>12,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_USERAGENT=>'Sondir-Lab-Universitas-Muhammadiyah-Buton/1.0',
    ]);
    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);
    if($response===false||$status!==200)throw new RuntimeException($error?:'Layanan lokasi tidak merespons.');
    $places=json_decode($response,true,512,JSON_THROW_ON_ERROR);
    echo json_encode(array_map(static fn(array $place):array=>[
        'display_name'=>(string)($place['display_name']??''),
        'lat'=>(float)($place['lat']??0),
        'lon'=>(float)($place['lon']??0),
    ],array_slice($places,0,5)),JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    error_log($e->getMessage());
    http_response_code(502);
    echo json_encode(['success'=>false,'message'=>'Pencarian lokasi sedang tidak tersedia.']);
}
