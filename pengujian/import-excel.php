<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','operator']);
require APP_ROOT.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$id=(int)($_GET['id']??$_POST['id']??0);
$q=$pdo->prepare("SELECT t.*,p.nama_proyek,a.kode_alat,a.nama_alat,a.faktor_kalibrasi_konus fk,a.faktor_kalibrasi_total ft,a.luas_piston api,a.luas_konus ac,a.luas_selimut ass,a.kapasitas_maksimum,a.satuan_kapasitas FROM titik_sondir t JOIN proyek p ON p.id=t.proyek_id JOIN alat_sondir a ON a.id=t.alat_id WHERE t.id=?");
$q->execute([$id]);$titik=$q->fetch();if(!$titik){http_response_code(404);exit('Titik tidak ditemukan.');}
$preview=$_SESSION['import_sondir'][$id]??[];
$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='confirm'){
        if(!$preview){flash('danger','Tidak ada data impor untuk dikonfirmasi.');redirect('pengujian/import-excel.php?id='.$id);}
        try{
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM hasil_sondir WHERE titik_sondir_id=?')->execute([$id]);
            $ins=$pdo->prepare('INSERT INTO hasil_sondir(titik_sondir_id,nomor,kedalaman,bacaan_konus,bacaan_total,qc,hambatan_total,fs,jhp,friction_ratio,satuan_tekanan,jenis_tanah,keterangan,status_validasi,pesan_validasi) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $tf=0;
            foreach($preview as $i=>$r){
                $kw=$r['tw']-$r['cw'];
                $qc=$r['cw']*((float)$titik['api']/max((float)$titik['ac'],.000001))*(float)$titik['fk'];
                $fs=$kw*((float)$titik['api']/max((float)$titik['ass'],.000001))*(float)$titik['ft'];
                $tf+=$fs*((float)$titik['interval_kedalaman']*100);
                $rf=$qc!=0?$fs/$qc*100:0;
                $soil=sondir_soil_classification($qc,$rf);
                $strength=sondir_strength_classification($qc,$soil['jenis']);
                $warning=$r['tw']<$r['cw']?'Tw lebih kecil dari Cw':'';
                $ins->execute([$id,$i+1,$r['kedalaman'],$r['cw'],$r['tw'],$qc,$kw,$fs,$tf,$rf,$titik['satuan_kapasitas']?:'kPa/100',$soil['jenis'],$strength,$warning?'peringatan':'valid',$warning]);
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
                $map=array_flip($headers);$parsed=[];$depths=[];
                foreach(array_slice($raw,$headerIndex+1) as $line=>$row){
                    $depth=(float)str_replace(',','.',trim((string)($row[$map['kedalaman']]??'')));
                    $cwRaw=trim((string)($row[$map['cw']]??''));$twRaw=trim((string)($row[$map['tw']]??''));
                    if($depth<=0&&$cwRaw===''&&$twRaw==='')continue;
                    $cw=(float)str_replace(',','.',$cwRaw);$tw=(float)str_replace(',','.',$twRaw);
                    if($depth<=0){$errors[]='Baris '.($headerIndex+$line+2).': kedalaman harus lebih dari nol.';continue;}
                    $key=number_format($depth,3,'.','');if(isset($depths[$key])){$errors[]='Kedalaman '.$depth.' duplikat.';continue;}$depths[$key]=true;
                    $kw=$tw-$cw;
                    $qc=$cw*((float)$titik['api']/max((float)$titik['ac'],.000001))*(float)$titik['fk'];
                    $fs=$kw*((float)$titik['api']/max((float)$titik['ass'],.000001))*(float)$titik['ft'];
                    $rf=$qc!=0?$fs/$qc*100:0;
                    $soil=sondir_soil_classification($qc,$rf);
                    $strength=sondir_strength_classification($qc,$soil['jenis']);
                    $parsed[]=['kedalaman'=>$depth,'cw'=>$cw,'tw'=>$tw,'ic'=>$soil['ic'],'jenis_tanah'=>$soil['jenis'],'keterangan'=>$strength];
                }
                if(!$parsed)$errors[]='File tidak berisi data yang dapat diimpor.';
                if(!$errors){$_SESSION['import_sondir'][$id]=$parsed;$preview=$parsed;}
            }catch(Throwable $e){error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'File tidak dapat dibaca.';}
        }
    }
}
$pageTitle='Impor Data Sondir';require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading"><div><a href="input.php?id=<?=$id?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke input</a><h2 class="mt-2">Impor Data Sondir</h2><p><?=e($titik['kode_titik'].' - '.$titik['nama_proyek'])?></p></div><a href="download-template.php" class="btn btn-outline-primary"><i class="bi bi-download"></i> Unduh template</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-body"><h5>Pilih file</h5><p class="text-secondary small">Gunakan kolom Kedalaman, Cw, dan Tw. Nilai qc, fs, Tf, Rf, Ic, jenis tanah, serta konsistensi/kepadatan dihitung otomatis.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="file" class="form-control mb-3" name="file" accept=".xlsx,.xls,.csv" required><button class="btn btn-primary w-100"><i class="bi bi-eye"></i> Tampilkan preview</button></form></div></div></div>
<div class="col-lg-8"><div class="card data-card"><div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><div><b>Preview data</b><div class="small text-secondary"><?=count($preview)?> baris valid</div></div><?php if($preview):?><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="confirm"><button class="btn btn-success"><i class="bi bi-check2-circle"></i> Konfirmasi & simpan</button></form><?php endif;?></div><div class="table-responsive" style="max-height:520px"><table class="table mb-0"><thead><tr><th>No</th><th>Kedalaman</th><th>Cw</th><th>Tw</th><th>Ic</th><th>Perkiraan jenis tanah</th><th>Validasi</th></tr></thead><tbody><?php foreach($preview as $i=>$r):?><tr class="<?=$r['tw']<$r['cw']?'table-warning':''?>"><td><?=$i+1?></td><td><?=$r['kedalaman']?></td><td><?=$r['cw']?></td><td><?=$r['tw']?></td><td><?=isset($r['ic'])&&$r['ic']!==null?number_format((float)$r['ic'],3,',','.'):'-'?></td><td><?=e(($r['jenis_tanah']??'')?sondir_soil_display_name($r['jenis_tanah']).' '.$r['keterangan']:'-')?></td><td><?=$r['tw']<$r['cw']?'<span class="badge text-bg-warning">Tw < Cw</span>':'<span class="badge text-bg-success">Valid</span>'?></td></tr><?php endforeach;?><?php if(!$preview):?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-earmark-spreadsheet"></i><strong>Belum ada preview</strong><span>Unggah file untuk memeriksa data sebelum disimpan.</span></div></td></tr><?php endif;?></tbody></table></div></div></div></div>
<?php require APP_ROOT.'/includes/footer.php';
