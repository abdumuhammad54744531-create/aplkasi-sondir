<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Sesi berakhir.']);
    exit;
}
if(!can(['super_admin','operator'])){
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Hak akses ditolak.']);
    exit;
}
verify_csrf();
$body=json_decode(file_get_contents('php://input'),true)?:[];
$id=(int)($body['titik_id']??0);
$latitude=filter_var($body['latitude']??null,FILTER_VALIDATE_FLOAT);
$longitude=filter_var($body['longitude']??null,FILTER_VALIDATE_FLOAT);
if(!$id||$latitude===false||$longitude===false||$latitude < -90||$latitude > 90||$longitude < -180||$longitude > 180){
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Koordinat tidak valid.']);
    exit;
}
try{
    $q=$pdo->prepare('SELECT status FROM titik_sondir WHERE id=?');
    $q->execute([$id]);
    $status=$q->fetchColumn();
    if($status===false)throw new RuntimeException('Titik sondir tidak ditemukan.');
    if(in_array($status,['disetujui','diterbitkan'],true))throw new RuntimeException('Data sudah dikunci.');
    $pdo->prepare('UPDATE titik_sondir SET latitude=?,longitude=?,updated_at=NOW() WHERE id=?')->execute([$latitude,$longitude,$id]);
    audit($pdo,'Memperbarui koordinat dari peta','titik_sondir',$id,null,['latitude'=>$latitude,'longitude'=>$longitude]);
    echo json_encode(['success'=>true,'message'=>'Koordinat tersimpan otomatis.','latitude'=>$latitude,'longitude'=>$longitude]);
}catch(Throwable $e){
    error_log($e->getMessage());
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Koordinat gagal disimpan.']);
}
