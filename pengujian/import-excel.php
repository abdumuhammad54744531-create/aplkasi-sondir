<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','operator']);
require APP_ROOT.'/vendor/autoload.php';
require_once APP_ROOT.'/includes/MechanicalSondirCalculator.php';
require_once APP_ROOT.'/includes/SondirValidationService.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$id=(int)($_GET['id']??$_POST['id']??0);
$q=$pdo->prepare("SELECT t.*,p.nama_proyek,a.kode_alat,a.nama_alat,a.faktor_kalibrasi_konus fk,a.faktor_kalibrasi_total ft,a.luas_piston api,a.luas_konus ac,a.luas_selimut ass,a.kapasitas_maksimum,a.satuan_kapasitas FROM titik_sondir t JOIN proyek p ON p.id=t.proyek_id JOIN alat_sondir a ON a.id=t.alat_id WHERE t.id=?");
$q->execute([$id]);$titik=$q->fetch();if(!$titik){http_response_code(404);exit('Titik tidak ditemukan.');}
$preview=$_SESSION['import_sondir'][$id]??[];
$errors=[];
$calculator=new MechanicalSondirCalculator();$validator=new SondirValidationService();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='confirm'){
        if(!$preview){flash('danger','Tidak ada data impor untuk dikonfirmasi.');redirect('pengujian/import-excel.php?id='.$id);}
        if(array_filter($preview,fn($row)=>($row['validation_status']??'valid')==='tidak_valid')){flash('danger','Import dibatalkan karena masih terdapat error fatal.');redirect('pengujian/import-excel.php?id='.$id);}
        try{
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM hasil_sondir WHERE titik_sondir_id=?')->execute([$id]);
            $ins=$pdo->prepare('INSERT INTO hasil_sondir(titik_sondir_id,nomor,kedalaman,bacaan_konus,bacaan_total,qc,qc_mpa,qc_kpa,hambatan_total,fs,fs_kpa,fs_mpa,jhp,friction_ratio,satuan_tekanan,zona_sbt,batas_zona,versi_klasifikasi,calculation_method,calculation_version,jenis_tanah,keterangan,status_validasi,pesan_validasi,validation_json,source_type) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $tf=0;$previous=null;
            foreach($preview as $i=>$r){
                $result=$calculator->calculate($titik,(float)$r['kedalaman'],(float)$r['cw'],(float)$r['tw'],$tf,(float)$titik['interval_kedalaman']);$tf=$result['tf'];$issues=$validator->validateReading($result,$previous,$titik);$previous=$result;$soil=sondir_soil_classification_mpa($result['qc_mpa'],$result['rf']);$strength=sondir_strength_classification($result['qc'],$soil['jenis']);$status=SondirValidationService::status($issues);$messages=array_column($issues,'message');
                $ins->execute([$id,$i+1,$r['kedalaman'],$r['cw'],$r['tw'],$result['qc'],$result['qc_mpa'],$result['qc_kpa'],$result['kw'],$result['fs'],$result['fs_kpa'],$result['fs_mpa'],$result['tf'],$result['rf'],$result['native_unit'],$soil['zone_number'],(int)$soil['boundary_flag'],$soil['versi'],$result['method'],$result['version'],$soil['jenis'],$strength,$status,implode('; ',$messages),json_encode($issues,JSON_UNESCAPED_UNICODE),'excel']);
            }
            $pdo->prepare("UPDATE titik_sondir SET status='draft',kedalaman_aktual=?,updated_at=NOW() WHERE id=?")->execute([max(array_column($preview,'kedalaman')),$id]);
            audit($pdo,'Mengimpor data sondir dari Excel/CSV','titik_sondir',$id,null,['jumlah'=>count($preview)]);
            $pdo->commit();unset($_SESSION['import_sondir'][$id]);flash('success',count($preview).' baris berhasil diimpor dan dihitung ulang.');redirect('pengujian/input.php?id='.$id);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());$errors[]='Data gagal disimpan.';}
    }else{
        $file=$_FILES['file']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK)$errors[]='Pilih file Excel atau CSV.';
        elseif($file['size']>5*1024*1024)$errors[]='Ukuran file maksimal 5 MB.';
        else{
            $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['xlsx','xls','csv'],true))$errors[]='Format yang diizinkan: XLSX, XLS, atau CSV.';
            else try{
                $sheet=IOFactory::load($file['tmp_name'])->getActiveSheet();
                $raw=$sheet->toArray(null,true,true,false);
                $headerIndex=null;
                foreach($raw as $i=>$row){$normalized=array_map(fn($v)=>strtolower(trim((string)$v)),$row);if(in_array('kedalaman',$normalized,true)&&in_array('cw',$normalized,true)&&in_array('tw',$normalized,true)){$headerIndex=$i;break;}}
                if($headerIndex===null)throw new RuntimeException('Header Kedalaman, Cw, dan Tw tidak ditemukan.');
                $headers=array_map(fn($v)=>strtolower(trim((string)$v)),$raw[$headerIndex]);
                $map=array_flip($headers);$parsed=[];$depths=[];$tf=0;$previous=null;
                foreach(array_slice($raw,$headerIndex+1) as $line=>$row){
                    $depth=(float)str_replace(',','.',trim((string)($row[$map['kedalaman']]??'')));
                    $cwRaw=trim((string)($row[$map['cw']]??''));$twRaw=trim((string)($row[$map['tw']]??''));
                    if($depth<=0&&$cwRaw===''&&$twRaw==='')continue;
                    $cw=(float)str_replace(',','.',$cwRaw);$tw=(float)str_replace(',','.',$twRaw);
                    if($depth<=0){$errors[]='Baris '.($headerIndex+$line+2).': kedalaman harus lebih dari nol.';continue;}
                    $key=number_format($depth,3,'.','');if(isset($depths[$key])){$errors[]='Kedalaman '.$depth.' duplikat.';continue;}$depths[$key]=true;
                    $result=$calculator->calculate($titik,$depth,$cw,$tw,$tf,(float)$titik['interval_kedalaman']);$tf=$result['tf'];$issues=$validator->validateReading($result,$previous,$titik);$previous=$result;$soil=sondir_soil_classification_mpa($result['qc_mpa'],$result['rf']);$strength=sondir_strength_classification($result['qc'],$soil['jenis']);
                    $parsed[]=['kedalaman'=>$depth,'cw'=>$cw,'tw'=>$tw,'qc'=>$result['qc'],'fs'=>$result['fs'],'rf'=>$result['rf'],'tf'=>$result['tf'],'zona'=>$soil['zone_number'],'jenis_tanah'=>$soil['jenis'],'keterangan'=>$strength,'validation_status'=>SondirValidationService::status($issues),'validation_messages'=>implode('; ',array_column($issues,'message'))];
                }
                if(!$parsed)$errors[]='File tidak berisi data yang dapat diimpor.';
                if(!$errors){$_SESSION['import_sondir'][$id]=$parsed;$preview=$parsed;}
            }catch(Throwable $e){error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'File tidak dapat dibaca.';}
        }
    }
}
$previewFatal=count(array_filter($preview,fn($row)=>($row['validation_status']??'valid')==='tidak_valid'));
$pageTitle='Impor Data Sondir';require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading"><div><a href="input.php?id=<?=$id?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke input</a><h2 class="mt-2">Impor Data Sondir</h2><p><?=e($titik['kode_titik'].' - '.$titik['nama_proyek'])?></p></div><a href="download-template.php" class="btn btn-outline-primary"><i class="bi bi-download"></i> Unduh template</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-body"><h5>Pilih file</h5><p class="text-secondary small">Gunakan kolom Kedalaman, Cw, dan Tw. Nilai qc, fs, Tf, FR, zona Robertson, jenis tanah, serta konsistensi/kepadatan dihitung otomatis.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="file" class="form-control mb-3" name="file" accept=".xlsx,.xls,.csv" required><button class="btn btn-primary w-100"><i class="bi bi-eye"></i> Tampilkan preview</button></form></div></div></div>
<div class="col-lg-8"><div class="card data-card"><div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><div><b>Preview dan validasi</b><div class="small <?=$previewFatal?'text-danger':'text-secondary'?>"><?=count($preview)?> baris diperiksa · <?=$previewFatal?> error fatal</div></div><?php if($preview):?><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="confirm"><button class="btn btn-success" <?=$previewFatal?'disabled title="Perbaiki error fatal sebelum import"':''?>><i class="bi bi-check2-circle"></i> Konfirmasi & simpan</button></form><?php endif;?></div><div class="table-responsive" style="max-height:520px"><table class="table mb-0"><thead><tr><th>No</th><th>Depth</th><th>Cw</th><th>Tw</th><th>qc</th><th>fs</th><th>Rf</th><th>Tf</th><th>Zona</th><th>Validasi</th></tr></thead><tbody><?php foreach($preview as $i=>$r):$status=$r['validation_status']??($r['tw']<$r['cw']?'tidak_valid':'valid');?><tr class="<?=$status==='tidak_valid'?'table-danger':($status==='peringatan'?'table-warning':'')?>"><td><?=$i+1?></td><td><?=$r['kedalaman']?></td><td><?=$r['cw']?></td><td><?=$r['tw']?></td><td><?=isset($r['qc'])?number_format((float)$r['qc'],3,',','.'):'-'?></td><td><?=isset($r['fs'])?number_format((float)$r['fs'],4,',','.'):'-'?></td><td><?=isset($r['rf'])?number_format((float)$r['rf'],2,',','.').'%':'-'?></td><td><?=isset($r['tf'])?number_format((float)$r['tf'],3,',','.'):'-'?></td><td><?=isset($r['zona'])&&$r['zona']!==null?'Z'.e($r['zona']):'-'?></td><td><span class="badge text-bg-<?=$status==='valid'?'success':($status==='peringatan'?'warning':'danger')?>"><?=e(ucwords(str_replace('_',' ',$status)))?></span><small class="d-block"><?=e($r['validation_messages']??'')?></small></td></tr><?php endforeach;?><?php if(!$preview):?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-file-earmark-spreadsheet"></i><strong>Belum ada preview</strong><span>Unggah file untuk memeriksa data sebelum disimpan.</span></div></td></tr><?php endif;?></tbody></table></div></div></div></div>
<?php require APP_ROOT.'/includes/footer.php';
