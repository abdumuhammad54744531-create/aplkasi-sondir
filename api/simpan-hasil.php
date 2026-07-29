<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
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
 $depths=[];$tf=0;$pdo->prepare('DELETE FROM hasil_sondir WHERE titik_sondir_id=?')->execute([$id]);
 $ins=$pdo->prepare('INSERT INTO hasil_sondir(titik_sondir_id,nomor,kedalaman,bacaan_konus,bacaan_total,qc,hambatan_total,fs,jhp,friction_ratio,satuan_tekanan,jenis_tanah,keterangan,status_validasi,pesan_validasi) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 foreach($rows as $i=>$r){
   $d=(float)($r['kedalaman']??0);$cw=(float)($r['bacaan_konus']??0);$tw=(float)($r['bacaan_total']??0);if($d<=0)continue;
   $dk=number_format($d,3,'.','');if(isset($depths[$dk]))throw new RuntimeException("Kedalaman $d duplikat.");$depths[$dk]=true;
   $kw=$tw-$cw;$qc=$cw*((float)$t['api']/(float)$t['ac'])*(float)$t['fk'];$fs=$kw*((float)$t['api']/(float)$t['ass'])*(float)$t['ft'];$tf+=$fs*((float)$t['interval_kedalaman']*100);$fr=$qc!=0?$fs/$qc*100:0;$soil=sondir_soil_classification($qc,$fr);$strength=sondir_strength_classification($qc,$soil['jenis']);
   $warnings=[];if($tw<$cw)$warnings[]='Tw lebih kecil dari Cw';if($cw<0||$tw<0)$warnings[]='Nilai negatif';if($t['kapasitas_maksimum']&&$qc>$t['kapasitas_maksimum'])$warnings[]='Melebihi kapasitas alat';
   $ins->execute([$id,$i+1,$d,$cw,$tw,$qc,$kw,$fs,$tf,$fr,$t['satuan_kapasitas']?:'kPa/100',$soil['jenis'],$strength,$warnings?'peringatan':'valid',implode('; ',$warnings)]);
 }
 if($status==='menunggu_pemeriksaan'&&!$depths)throw new RuntimeException('Data pengujian belum diisi.');
 $pdo->prepare('UPDATE titik_sondir SET alat_id=?,status=?,kedalaman_aktual=?,updated_at=NOW() WHERE id=?')->execute([$alatId,$status,$depths?max(array_map('floatval',array_keys($depths))):null,$id]);
 audit($pdo,'Menyimpan hasil SNI 2827:2008','titik_sondir',$id,null,['alat_id'=>$alatId,'baris'=>count($depths),'status'=>$status]);
 $pdo->commit();echo json_encode(['success'=>true,'message'=>$status==='draft'?'Draft berhasil disimpan.':'Pengujian dikirim untuk pemeriksaan.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());http_response_code(422);echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Data gagal disimpan.']);}
