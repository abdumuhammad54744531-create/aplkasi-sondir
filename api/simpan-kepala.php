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
$meta=is_array($body['meta']??null)?$body['meta']:[];
if(!$id){
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Titik sondir tidak valid.']);
    exit;
}
try{
    $q=$pdo->prepare('SELECT * FROM titik_sondir WHERE id=?');
    $q->execute([$id]);
    $t=$q->fetch();
    if(!$t)throw new RuntimeException('Titik sondir tidak ditemukan.');
    if(in_array($t['status'],['disetujui','diterbitkan'],true))throw new RuntimeException('Data sudah dikunci.');

    $nama=trim((string)($meta['nama_titik']??''));
    $tanggal=trim((string)($meta['tanggal_pengujian']??''));
    $latitude=trim((string)($meta['latitude']??''))===''?null:(float)$meta['latitude'];
    $longitude=trim((string)($meta['longitude']??''))===''?null:(float)$meta['longitude'];
    $elevasi=trim((string)($meta['elevasi']??''))===''?null:(float)$meta['elevasi'];
    $mukaAir=trim((string)($meta['muka_air_tanah']??''))===''?null:(float)$meta['muka_air_tanah'];
    if($nama==='')throw new RuntimeException('Nama titik wajib diisi.');
    if($tanggal===''||!DateTime::createFromFormat('Y-m-d',$tanggal))throw new RuntimeException('Tanggal pengujian tidak valid.');
    if($latitude!==null&&($latitude < -90||$latitude > 90))throw new RuntimeException('Latitude harus antara -90 dan 90.');
    if($longitude!==null&&($longitude < -180||$longitude > 180))throw new RuntimeException('Longitude harus antara -180 dan 180.');

    $pdo->prepare('UPDATE titik_sondir SET nama_titik=?,tanggal_pengujian=?,elevasi=?,muka_air_tanah=?,latitude=?,longitude=?,deskripsi_posisi=?,updated_at=NOW() WHERE id=?')
        ->execute([$nama,$tanggal,$elevasi,$mukaAir,$latitude,$longitude,trim((string)($meta['deskripsi_posisi']??'')),$id]);
    audit($pdo,'Menyimpan kepala data titik sondir','titik_sondir',$id,null,['nama_titik'=>$nama,'tanggal_pengujian'=>$tanggal,'latitude'=>$latitude,'longitude'=>$longitude]);
    echo json_encode(['success'=>true,'message'=>'Kepala data berhasil disimpan.','title'=>$nama]);
}catch(Throwable $e){
    error_log($e->getMessage());
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Kepala data gagal disimpan.']);
}
