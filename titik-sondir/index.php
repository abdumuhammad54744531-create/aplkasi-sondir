<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();

$rolesKelola=['super_admin','admin_lab'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_role($rolesKelola);
    verify_csrf();
    $id=(int)($_POST['id']??0);
    if(($_POST['action']??'')==='delete'){
        try{
            $pdo->prepare('DELETE FROM titik_sondir WHERE id=?')->execute([$id]);
            audit($pdo,'Menghapus titik sondir','titik_sondir',$id);
            flash('success','Titik sondir berhasil dihapus.');
        }catch(PDOException $e){
            error_log($e->getMessage());
            flash('danger','Titik tidak dapat dihapus karena sudah memiliki pemeriksaan atau laporan.');
        }
        redirect('titik-sondir/index.php');
    }

    $proyek=(int)($_POST['proyek_id']??0);
    $alat=(int)($_POST['alat_id']??0);
    $jumlah=max(1,min(50,(int)($_POST['jumlah_titik']??1)));
    $urut=1;
    $prefix=strtoupper(preg_replace('/[^A-Z0-9]/','',trim((string)($_POST['prefix_titik']??'S'))));
    if($prefix==='')$prefix='S';
    $prefix=substr($prefix,0,6);
    $q=$pdo->prepare('SELECT kode_proyek FROM proyek WHERE id=?');
    $q->execute([$proyek]);
    $kodeProyek=$q->fetchColumn();
    if(!$kodeProyek||!$alat){
        flash('danger','Proyek dan alat wajib dipilih.');
        redirect('titik-sondir/index.php');
    }
    try{
        $pdo->beginTransaction();
        $tambahan=0;
        $dihapus=0;
        if($id){
            $kodeLamaStmt=$pdo->prepare('SELECT id,parent_id FROM titik_sondir WHERE id=?');
            $kodeLamaStmt->execute([$id]);
            $dataLama=$kodeLamaStmt->fetch();
            if(!$dataLama)throw new RuntimeException('Titik tidak ditemukan.');
            $id=(int)($dataLama['parent_id']?:$dataLama['id']);
            $masterStmt=$pdo->prepare('SELECT kode_titik,nama_titik,nomor_urut FROM titik_sondir WHERE id=?');
            $masterStmt->execute([$id]);
            $master=$masterStmt->fetch();
            if(!$master)throw new RuntimeException('Titik induk tidak ditemukan.');
            $urut=(int)$master['nomor_urut'];
            $kode=$master['kode_titik']?:$kodeProyek.'-'.$prefix.'-'.str_pad((string)$urut,2,'0',STR_PAD_LEFT);
            $alamat=trim($_POST['alamat_lokasi']??'');
            $data=[$proyek,$alat,$kode,$master['nama_titik'],$urut,$_POST['tanggal_pengujian']?:null,(int)$_POST['operator_id'],(int)($_POST['pemeriksa_id']??0)?:null,(float)$_POST['interval_kedalaman'],(float)($_POST['kedalaman_rencana']??0),trim($_POST['kondisi_cuaca']??''),$alamat,trim($_POST['catatan']??'')];
            $pdo->prepare('UPDATE titik_sondir SET proyek_id=?,alat_id=?,kode_titik=?,nama_titik=?,nomor_urut=?,tanggal_pengujian=?,operator_id=?,pemeriksa_id=?,interval_kedalaman=?,kedalaman_rencana=?,kondisi_cuaca=?,alamat_lokasi=?,catatan=?,updated_at=NOW() WHERE id=?')->execute([...$data,$id]);
            $pdo->prepare('UPDATE titik_sondir SET proyek_id=?,alat_id=?,tanggal_pengujian=?,operator_id=?,pemeriksa_id=?,interval_kedalaman=?,kedalaman_rencana=?,kondisi_cuaca=?,alamat_lokasi=?,catatan=?,updated_at=NOW() WHERE parent_id=?')->execute([$proyek,$alat,$_POST['tanggal_pengujian']?:null,(int)$_POST['operator_id'],(int)($_POST['pemeriksa_id']??0)?:null,(float)$_POST['interval_kedalaman'],(float)($_POST['kedalaman_rencana']??0),trim($_POST['kondisi_cuaca']??''),$alamat,trim($_POST['catatan']??''),$id]);
            audit($pdo,'Mengubah titik sondir','titik_sondir',$id,null,$data);
            $jumlahSekarangStmt=$pdo->prepare('SELECT 1+(SELECT COUNT(*) FROM titik_sondir WHERE parent_id=?)');
            $jumlahSekarangStmt->execute([$id]);
            $jumlahSekarang=(int)$jumlahSekarangStmt->fetchColumn();
            if($jumlah>$jumlahSekarang){
                $nomorMulai=$jumlahSekarang+1;
                $insert=$pdo->prepare('INSERT INTO titik_sondir(parent_id,proyek_id,alat_id,kode_titik,nama_titik,nomor_urut,tanggal_pengujian,operator_id,pemeriksa_id,interval_kedalaman,kedalaman_rencana,kondisi_cuaca,alamat_lokasi,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $tambahan=$jumlah-$jumlahSekarang;
                for($i=0;$i<$tambahan;$i++){
                    $nomor=$nomorMulai+$i;
                    $kodeBaru=$kodeProyek.'-'.$prefix.'-'.str_pad((string)$nomor,2,'0',STR_PAD_LEFT);
                    $dataBaru=[$id,$proyek,$alat,$kodeBaru,'Sondir '.$nomor,$nomor,$_POST['tanggal_pengujian']?:null,(int)$_POST['operator_id'],(int)($_POST['pemeriksa_id']??0)?:null,(float)$_POST['interval_kedalaman'],(float)($_POST['kedalaman_rencana']??0),trim($_POST['kondisi_cuaca']??''),$alamat,trim($_POST['catatan']??''),$_SESSION['user']['id']];
                    $insert->execute($dataBaru);
                    audit($pdo,'Menambah tab sondir','titik_sondir',(int)$pdo->lastInsertId(),null,['parent_id'=>$id,'nomor'=>$nomor,'target_jumlah'=>$jumlah]);
                }
            }elseif($jumlah<$jumlahSekarang){
                $kelebihanStmt=$pdo->prepare('SELECT id,kode_titik,nomor_urut,status FROM titik_sondir WHERE parent_id=? ORDER BY nomor_urut DESC,id DESC LIMIT '.($jumlahSekarang-$jumlah).' FOR UPDATE');
                $kelebihanStmt->execute([$id]);
                $kelebihan=$kelebihanStmt->fetchAll();
                foreach($kelebihan as $titikHapus){
                    if($titikHapus['status']!=='draft'){
                        throw new DomainException('Jumlah belum dapat dikurangi. '.$titikHapus['kode_titik'].' sudah berstatus '.ucwords(str_replace('_',' ',$titikHapus['status'])).'.');
                    }
                }
                $hapusTitik=$pdo->prepare('DELETE FROM titik_sondir WHERE id=?');
                foreach($kelebihan as $titikHapus){
                    $hapusTitik->execute([(int)$titikHapus['id']]);
                    $dihapus++;
                    audit($pdo,'Menghapus tab sondir draf','titik_sondir',(int)$titikHapus['id'],$titikHapus,['parent_id'=>$id,'target_jumlah'=>$jumlah]);
                }
            }
            $pdo->prepare('UPDATE proyek SET jumlah_titik_rencana=?,updated_at=NOW() WHERE id=?')->execute([$jumlah,$proyek]);
        }else{
            $insert=$pdo->prepare('INSERT INTO titik_sondir(parent_id,proyek_id,alat_id,kode_titik,nama_titik,nomor_urut,tanggal_pengujian,operator_id,pemeriksa_id,interval_kedalaman,kedalaman_rencana,kondisi_cuaca,alamat_lokasi,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $parentId=null;
            $alamat=trim($_POST['alamat_lokasi']??'');
            for($nomor=1;$nomor<=$jumlah;$nomor++){
                $kode=$kodeProyek.'-'.$prefix.'-'.str_pad((string)$nomor,2,'0',STR_PAD_LEFT);
                $nama='Sondir '.$nomor;
                $data=[$parentId,$proyek,$alat,$kode,$nama,$nomor,$_POST['tanggal_pengujian']?:null,(int)$_POST['operator_id'],(int)($_POST['pemeriksa_id']??0)?:null,(float)$_POST['interval_kedalaman'],(float)($_POST['kedalaman_rencana']??0),trim($_POST['kondisi_cuaca']??''),$alamat,trim($_POST['catatan']??''),$_SESSION['user']['id']];
                $insert->execute($data);
                $newId=(int)$pdo->lastInsertId();
                if($nomor===1)$parentId=$newId;
                audit($pdo,$nomor===1?'Menambah titik sondir':'Menambah tab sondir','titik_sondir',$newId,null,['parent_id'=>$nomor===1?null:$parentId,'nomor'=>$nomor,'jumlah_tab'=>$jumlah]);
            }
            $pdo->prepare('UPDATE proyek SET jumlah_titik_rencana=?,updated_at=NOW() WHERE id=?')->execute([$jumlah,$proyek]);
        }
        $pdo->commit();
        if($id){
            $pesan='Titik sondir berhasil diperbarui menjadi '.$jumlah.' titik.';
            if($tambahan>0)$pesan.=' '.$tambahan.' titik baru ditambahkan.';
            if($dihapus>0)$pesan.=' '.$dihapus.' titik draf beserta data inputnya dihapus.';
            flash('success',$pesan);
        }else{
            flash('success','Satu kelompok dibuat dengan '.$jumlah.' titik sondir.');
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log($e->getMessage());
        flash('danger',$e instanceof DomainException?$e->getMessage():'Titik gagal disimpan. Pastikan kode dan nomor urut belum digunakan.');
    }
    redirect('titik-sondir/index.php');
}

$projects=$pdo->query("SELECT id,kode_proyek,nama_proyek FROM proyek WHERE status!='dibatalkan' ORDER BY nama_proyek")->fetchAll();
$tools=$pdo->query("SELECT id,kode_alat,nama_alat,jenis_alat,merek,model,kapasitas_maksimum,satuan_kapasitas,luas_piston,luas_konus,luas_selimut,interval_standar,tanggal_kedaluwarsa FROM alat_sondir WHERE status='aktif' ORDER BY nama_alat")->fetchAll();
$users=$pdo->query("SELECT id,nama_lengkap,level FROM users WHERE status='aktif' ORDER BY nama_lengkap")->fetchAll();
$filter=(int)($_GET['proyek_id']??0);
$edit=null;
if($editId=(int)($_GET['id']??0)){
    $q=$pdo->prepare('SELECT * FROM titik_sondir WHERE id=?');
    $q->execute([$editId]);
    $edit=$q->fetch();
}
$editJumlah=1;
$editPrefix='S';
if($edit){
    if(!empty($edit['parent_id'])){
        $q=$pdo->prepare('SELECT * FROM titik_sondir WHERE id=?');
        $q->execute([$edit['parent_id']]);
        $edit=$q->fetch();
    }
    $q=$pdo->prepare('SELECT 1+(SELECT COUNT(*) FROM titik_sondir WHERE parent_id=?)');
    $q->execute([$edit['id']]);
    $editJumlah=max(1,(int)$q->fetchColumn());
    if(preg_match('/-([A-Z0-9]+)-\d+$/',$edit['kode_titik'],$match))$editPrefix=$match[1];
}
$q=$pdo->prepare("SELECT t.*,p.nama_proyek,a.nama_alat,a.kode_alat,u.nama_lengkap operator,(SELECT COUNT(*) FROM titik_sondir c WHERE c.parent_id=t.id) jumlah_tab FROM titik_sondir t JOIN proyek p ON p.id=t.proyek_id JOIN alat_sondir a ON a.id=t.alat_id JOIN users u ON u.id=t.operator_id WHERE t.parent_id IS NULL AND (?=0 OR t.proyek_id=?) ORDER BY t.id DESC");
$q->execute([$filter,$filter]);
$rows=$q->fetchAll();
$pageTitle='Titik Sondir';
require APP_ROOT.'/includes/header.php';
?>
<div class="page-heading">
  <div><span class="eyebrow">Pengujian Lapangan</span><h2>Titik Sondir</h2><p>Tentukan jumlah titik sekali, lalu sistem membuat seluruh tab sondir secara otomatis.</p></div>
  <?php if(can($rolesKelola)):?><button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#pointModal"><i class="bi bi-plus-lg"></i> Buat Titik Sondir</button><?php endif;?>
</div>
<div class="card data-card">
  <div class="toolbar"><form class="row g-2 align-items-center"><div class="col-md-5"><select class="form-select" name="proyek_id" onchange="this.form.submit()"><option value="0">Semua proyek</option><?php foreach($projects as $p):?><option value="<?=$p['id']?>" <?=$filter===$p['id']?'selected':''?>><?=e($p['kode_proyek'].' - '.$p['nama_proyek'])?></option><?php endforeach;?></select></div><?php if($filter):?><div class="col-auto"><a href="index.php" class="btn btn-light">Reset</a></div><?php endif;?></form></div>
  <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kode titik</th><th>Proyek</th><th>Alat terpilih</th><th>Operator</th><th>Tanggal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($rows as $r):?><tr><td><b><?=e($r['kode_titik'])?></b><br><small class="text-secondary"><?=e($r['alamat_lokasi']?:'-')?> · <?=1+(int)$r['jumlah_tab']?> tab sondir</small></td><td><?=e($r['nama_proyek'])?></td><td><span class="badge text-bg-light"><?=e($r['kode_alat'])?></span> <?=e($r['nama_alat'])?></td><td><?=e($r['operator'])?></td><td><?=tanggal_id($r['tanggal_pengujian'])?></td><td><?=status_badge($r['status'])?></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-primary" href="<?=url('pengujian/input.php?id='.$r['id'])?>"><i class="bi bi-table"></i> Input</a><?php if(can($rolesKelola)):?> <a class="btn btn-sm btn-outline-primary" href="?id=<?=$r['id']?>"><i class="bi bi-pencil-square"></i> Edit</a> <form method="post" class="d-inline" data-confirm="Hapus titik sondir ini beserta semua tab dan data hasilnya?"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus</button></form><?php endif;?></td></tr><?php endforeach;?>
  <?php if(!$rows):?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-geo-alt"></i><strong>Belum ada titik sondir</strong><span>Tambahkan titik untuk mulai merekam pengujian.</span></div></td></tr><?php endif;?>
  </tbody></table></div>
</div>

<?php if(can($rolesKelola)):?><div class="modal fade" id="pointModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="post" class="modal-content"><?=csrf_field()?><input type="hidden" name="id" value="<?=$edit['id']??0?>">
<div class="modal-header"><div><span class="eyebrow">Titik pengujian</span><h5 class="modal-title"><?=$edit?'Edit':'Buat'?> Titik Sondir</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="form-section mb-3"><div class="form-section-title"><i class="bi bi-geo-alt"></i> Identitas dan penugasan</div><div class="row g-3">
<div class="col-md-6"><label class="form-label required">Proyek</label><select required class="form-select" name="proyek_id"><option value="">Pilih proyek</option><?php foreach($projects as $p):?><option value="<?=$p['id']?>" <?=($edit['proyek_id']??$filter)==$p['id']?'selected':''?>><?=e($p['kode_proyek'].' - '.$p['nama_proyek'])?></option><?php endforeach;?></select></div>
	<div class="col-12"><div class="alert alert-primary mb-0"><i class="bi bi-layers me-2"></i>Jumlah titik akan diselaraskan otomatis. Jika jumlah dikurangi, titik terakhir yang masih berstatus draf beserta data inputnya akan dihapus dari Titik Sondir dan Pengujian.</div></div>
	<div class="col-md-3"><label class="form-label required">Jumlah titik sondir</label><input required type="number" min="1" max="50" class="form-control form-control-lg" name="jumlah_titik" value="<?=e($edit?$editJumlah:1)?>"><small class="text-secondary">Contoh: isi 3 untuk membuat Sondir 1, 2, dan 3.</small></div>
	<div class="col-md-3"><label class="form-label required">Singkatan titik</label><input required class="form-control form-control-lg text-uppercase" name="prefix_titik" maxlength="6" value="<?=e($edit?$editPrefix:'S')?>"><small class="text-secondary">Default S. Dapat diganti CPT atau SD.</small></div>
	<div class="col-md-6"><label class="form-label required">Alamat lokasi</label><input required class="form-control form-control-lg" name="alamat_lokasi" value="<?=e($edit['alamat_lokasi']??'')?>" placeholder="Contoh: Jl. Betoambari, Kota Baubau"></div>
<div class="col-md-4"><label class="form-label required">Operator</label><select required class="form-select" name="operator_id"><option value="">Pilih operator</option><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=($edit['operator_id']??0)==$u['id']?'selected':''?>><?=e($u['nama_lengkap'])?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Pemeriksa</label><select class="form-select" name="pemeriksa_id"><option value="">Pilih pemeriksa</option><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=($edit['pemeriksa_id']??0)==$u['id']?'selected':''?>><?=e($u['nama_lengkap'])?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label required">Tanggal pengujian</label><input required type="date" class="form-control" name="tanggal_pengujian" value="<?=e($edit['tanggal_pengujian']??date('Y-m-d'))?>"></div>
</div></div>
<div class="form-section"><div class="form-section-title"><i class="bi bi-tools"></i> Pilih alat dan parameter otomatis</div><div class="row g-3">
<div class="col-md-6"><label class="form-label required">Alat sondir</label><select required class="form-select" name="alat_id" id="alatSelect"><option value="">Pilih alat</option><?php foreach($tools as $a):?><option value="<?=$a['id']?>" <?=($edit['alat_id']??0)==$a['id']?'selected':''?> data-code="<?=e($a['kode_alat'])?>" data-type="<?=e($a['jenis_alat'])?>" data-capacity="<?=e($a['kapasitas_maksimum'])?>" data-unit="<?=e($a['satuan_kapasitas'])?>" data-piston="<?=e($a['luas_piston'])?>" data-cone="<?=e($a['luas_konus'])?>" data-sleeve="<?=e($a['luas_selimut'])?>" data-interval="<?=e($a['interval_standar'])?>" data-expiry="<?=e($a['tanggal_kedaluwarsa'])?>"><?=e($a['kode_alat'].' - '.$a['nama_alat'].' / '.$a['merek'].' '.$a['model'])?></option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Interval (m)</label><input type="number" step=".01" class="form-control" id="intervalInput" name="interval_kedalaman" value="<?=e($edit['interval_kedalaman']??'.20')?>"></div>
<div class="col-md-2"><label class="form-label">Rencana (m)</label><input type="number" step=".1" class="form-control" name="kedalaman_rencana" value="<?=e($edit['kedalaman_rencana']??10)?>"></div>
<div class="col-md-2"><label class="form-label">Cuaca</label><input class="form-control" name="kondisi_cuaca" value="<?=e($edit['kondisi_cuaca']??'')?>"></div>
<div class="col-12"><div class="tool-summary" id="toolSummary"><div class="row g-2"><div class="col-md"><small class="text-secondary">Jenis</small><div data-tool="type">-</div></div><div class="col-md tool-metric"><small class="text-secondary">Kapasitas</small><div data-tool="capacity">-</div></div><div class="col-md tool-metric"><small class="text-secondary">Api / Ac / As</small><div data-tool="areas">-</div></div><div class="col-md tool-metric"><small class="text-secondary">Kalibrasi berlaku</small><div data-tool="expiry">-</div></div></div></div></div>
<div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="catatan" rows="2"><?=e($edit['catatan']??'')?></textarea></div>
</div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Simpan titik</button></div></form></div></div><?php endif;?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const select=document.getElementById('alatSelect'), interval=document.getElementById('intervalInput');
 const update=()=>{const o=select?.selectedOptions[0];if(!o||!o.value)return;document.querySelector('[data-tool="type"]').textContent=o.dataset.type||'-';document.querySelector('[data-tool="capacity"]').textContent=(o.dataset.capacity||'-')+' '+(o.dataset.unit||'');document.querySelector('[data-tool="areas"]').textContent=`${o.dataset.piston||'-'} / ${o.dataset.cone||'-'} / ${o.dataset.sleeve||'-'} cm²`;document.querySelector('[data-tool="expiry"]').textContent=o.dataset.expiry||'-';if(!interval.dataset.changed)interval.value=o.dataset.interval||'.20';};
 select?.addEventListener('change',update);interval?.addEventListener('input',()=>interval.dataset.changed='1');update();
 <?php if($edit):?>const pointModal=document.getElementById('pointModal');if(pointModal)bootstrap.Modal.getOrCreateInstance(pointModal).show();<?php endif;?>
});
</script>
<?php require APP_ROOT.'/includes/footer.php';
