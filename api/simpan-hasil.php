<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_once APP_ROOT.'/includes/MechanicalSondirCalculator.php';
require_once APP_ROOT.'/includes/SondirValidationService.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user'])){http_response_code(401);echo json_encode(['success'=>false,'message'=>'Sesi berakhir.']);exit;}
if(!can(['super_admin','operator'])){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Hak akses ditolak.']);exit;}
verify_csrf();
$body=json_decode(file_get_contents('php://input'),true)?:[];
$id=(int)($body['titik_id']??0);$alatId=(int)($body['alat_id']??0);$rows=$body['data']??[];$meta=is_array($body['meta']??null)?$body['meta']:[];$status=$body['status']??'draft';
if(!$id||!$alatId||!is_array($rows)||!in_array($status,['draft','menunggu_pemeriksaan'],true)){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Permintaan tidak valid.']);exit;}
$q=$pdo->prepare("SELECT t.*,a.faktor_kalibrasi_konus fk,a.faktor_kalibrasi_total ft,a.luas_piston api,a.luas_konus ac,a.luas_selimut ass,a.kapasitas_maksimum,a.satuan_kapasitas FROM titik_sondir t JOIN alat_sondir a ON a.id=? AND a.status='aktif' WHERE t.id=? FOR UPDATE");
try{
 $pdo->beginTransaction();$q->execute([$alatId,$id]);$t=$q->fetch();if(!$t)throw new RuntimeException('Titik atau alat tidak ditemukan.');
 if(in_array($t['status'],['disetujui','diterbitkan'],true))throw new RuntimeException('Data sudah dikunci.');
 if((float)$t['api']<=0||(float)$t['ac']<=0||(float)$t['ass']<=0)throw new RuntimeException('Parameter luas alat belum lengkap.');
 $depths=[];$tf=0;$previous=null;$fatal=[];$calculator=new MechanicalSondirCalculator();$validator=new SondirValidationService();$pdo->prepare('DELETE FROM hasil_sondir WHERE titik_sondir_id=?')->execute([$id]);
 $ins=$pdo->prepare('INSERT INTO hasil_sondir(titik_sondir_id,nomor,kedalaman,bacaan_konus,bacaan_total,qc,qc_mpa,qc_kpa,hambatan_total,fs,fs_kpa,fs_mpa,jhp,friction_ratio,satuan_tekanan,zona_sbt,batas_zona,versi_klasifikasi,calculation_method,calculation_version,jenis_tanah,keterangan,status_validasi,pesan_validasi,validation_json,source_type) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 foreach($rows as $i=>$r){
   $d=(float)($r['kedalaman']??0);$cw=(float)($r['bacaan_konus']??0);$tw=(float)($r['bacaan_total']??0);if($d<=0)continue;
   $dk=number_format($d,3,'.','');if(isset($depths[$dk]))throw new RuntimeException("Kedalaman $d duplikat.");$depths[$dk]=true;
   $result=$calculator->calculate($t,$d,$cw,$tw,$tf,(float)$t['interval_kedalaman']);$tf=$result['tf'];$issues=$validator->validateReading($result,$previous,$t);$previous=$result;$soil=sondir_soil_classification_mpa($result['qc_mpa'],$result['rf']);$strength=sondir_strength_classification($result['qc'],$soil['jenis']);$validationStatus=SondirValidationService::status($issues);$messages=array_column($issues,'message');if($validationStatus==='tidak_valid')$fatal[]='Baris '.($i+1).': '.implode('; ',$messages);
   $ins->execute([$id,$i+1,$d,$cw,$tw,$result['qc'],$result['qc_mpa'],$result['qc_kpa'],$result['kw'],$result['fs'],$result['fs_kpa'],$result['fs_mpa'],$result['tf'],$result['rf'],$result['native_unit'],$soil['zone_number'],(int)$soil['boundary_flag'],$soil['versi'],$result['method'],$result['version'],$soil['jenis'],$strength,$validationStatus,implode('; ',$messages),json_encode($issues,JSON_UNESCAPED_UNICODE),(string)($body['source_type']??'manual')]);
 }
 if($status==='menunggu_pemeriksaan'&&!$depths)throw new RuntimeException('Data pengujian belum diisi.');
 if($status==='menunggu_pemeriksaan'&&$fatal)throw new RuntimeException('Data memiliki error fatal dan belum dapat dikirim: '.implode(' | ',array_slice($fatal,0,3)));
 $pdo->prepare('UPDATE titik_sondir SET alat_id=?,status=?,kedalaman_aktual=?,updated_at=NOW() WHERE id=?')->execute([$alatId,$status,$depths?max(array_map('floatval',array_keys($depths))):null,$id]);
 audit($pdo,'Menyimpan hasil SNI 2827:2008','titik_sondir',$id,null,['alat_id'=>$alatId,'baris'=>count($depths),'status'=>$status]);
 $pdo->commit();echo json_encode(['success'=>true,'message'=>$status==='draft'?'Draft berhasil disimpan.':'Pengujian dikirim untuk pemeriksaan.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());http_response_code(422);echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Data gagal disimpan.']);}
