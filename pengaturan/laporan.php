<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','pemeriksa']);
require APP_ROOT.'/laporan/_report.php';

report_settings_ensure($pdo);
$lab=$pdo->query('SELECT * FROM laboratorium ORDER BY id LIMIT 1')->fetch()?:[];
$settings=report_settings($pdo,$lab);

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_role('super_admin');
    verify_csrf();
    $font=in_array($_POST['font_family']??'',['DejaVu Sans','DejaVu Serif','DejaVu Sans Mono'],true)?$_POST['font_family']:'DejaVu Sans';
    $style=in_array($_POST['gaya_kop']??'',['formal','minimal','balok'],true)?$_POST['gaya_kop']:'formal';
    $primary=report_hex_color((string)($_POST['warna_utama']??''),'#173B61');
    $accent=report_hex_color((string)($_POST['warna_aksen']??''),'#F4B400');
    $fontSize=max(7.5,min(12,(float)($_POST['font_size']??9.2)));
    $logoPath=(string)($settings['logo_path']??'');

    if(isset($_FILES['logo'])&&($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        if($_FILES['logo']['error']!==UPLOAD_ERR_OK||$_FILES['logo']['size']>2*1024*1024){
            flash('danger','Logo gagal diunggah atau ukurannya melebihi 2 MB.');
            redirect('pengaturan/laporan.php');
        }
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
        $extension=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
        if(!$extension){
            flash('danger','Logo harus berupa PNG, JPG, atau WebP.');
            redirect('pengaturan/laporan.php');
        }
        $directory=APP_ROOT.'/uploads/report';
        if(!is_dir($directory))mkdir($directory,0775,true);
        $filename='logo-laporan.'.$extension;
        if(!move_uploaded_file($_FILES['logo']['tmp_name'],$directory.'/'.$filename)){
            flash('danger','Logo tidak dapat disimpan.');
            redirect('pengaturan/laporan.php');
        }
        $logoPath='report/'.$filename;
    }

    $pdo->prepare(
        'INSERT INTO pengaturan_laporan(id,kop_nama,kop_subjudul,kop_alamat,judul_laporan,font_family,font_size,warna_utama,warna_aksen,gaya_kop,logo_path,footer_text,updated_by,updated_at)
         VALUES(1,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE kop_nama=VALUES(kop_nama),kop_subjudul=VALUES(kop_subjudul),kop_alamat=VALUES(kop_alamat),
         judul_laporan=VALUES(judul_laporan),font_family=VALUES(font_family),font_size=VALUES(font_size),warna_utama=VALUES(warna_utama),
         warna_aksen=VALUES(warna_aksen),gaya_kop=VALUES(gaya_kop),logo_path=VALUES(logo_path),footer_text=VALUES(footer_text),
         updated_by=VALUES(updated_by),updated_at=NOW()'
    )->execute([
        trim((string)$_POST['kop_nama']),trim((string)($_POST['kop_subjudul']??'')),trim((string)($_POST['kop_alamat']??'')),
        trim((string)$_POST['judul_laporan']),$font,$fontSize,$primary,$accent,$style,$logoPath,
        trim((string)($_POST['footer_text']??'')),(int)$_SESSION['user']['id'],
    ]);
    audit($pdo,'Mengubah pengaturan laporan','pengaturan_laporan',1,null,['font'=>$font,'gaya_kop'=>$style,'warna_utama'=>$primary]);
    flash('success','Pengaturan laporan berhasil disimpan.');
    redirect('pengaturan/laporan.php');
}

$pageTitle='Pengaturan Laporan';
require APP_ROOT.'/includes/header.php';
?>
<style>
.report-setting-preview{border:1px solid #cfdbe6;border-radius:14px;background:#fff;overflow:hidden;position:sticky;top:92px}
.report-paper{aspect-ratio:210/297;background:#fff;margin:20px;box-shadow:0 8px 30px rgba(25,52,78,.12);padding:24px;color:#22384c}
.report-kop-preview{min-height:70px;border-bottom:3px solid var(--report-primary);display:flex;gap:12px;align-items:center;padding:9px 4px}
.report-kop-preview.balok{background:var(--report-primary);color:#fff;padding:12px}.report-kop-preview.minimal{border-bottom-width:1px}
.report-kop-logo{width:52px;height:52px;object-fit:contain;background:#eef3f7;border-radius:8px}
.report-kop-name{font-weight:800;font-size:16px}.report-kop-sub{font-size:10px;opacity:.75}
.report-preview-title{margin-top:30px;border-left:5px solid var(--report-accent);padding:14px;background:#edf4f9;font-weight:800;color:var(--report-primary)}
</style>
<div class="page-heading">
  <div><span class="eyebrow">Laporan Proyek</span><h2>Pengaturan Laporan</h2><p>Atur kop atas, logo, identitas, warna, serta jenis dan ukuran huruf PDF.</p></div>
  <a class="btn btn-outline-primary" href="<?=url('laporan/index.php')?>"><i class="bi bi-file-earmark-pdf me-1"></i> Daftar laporan</a>
</div>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3">
      <div class="card-header bg-white py-3"><span class="eyebrow">Kop Atas</span><h5 class="mb-0">Identitas pada setiap halaman</h5></div>
      <div class="card-body"><div class="row g-3">
        <div class="col-12"><label class="form-label required">Nama kop</label><input class="form-control report-preview-input" required name="kop_nama" id="kopNama" value="<?=e($settings['kop_nama'])?>"></div>
        <div class="col-md-6"><label class="form-label">Subjudul/instansi</label><input class="form-control report-preview-input" name="kop_subjudul" id="kopSubjudul" value="<?=e($settings['kop_subjudul'])?>"></div>
        <div class="col-md-6"><label class="form-label">Alamat singkat</label><input class="form-control report-preview-input" name="kop_alamat" id="kopAlamat" value="<?=e($settings['kop_alamat'])?>"></div>
        <div class="col-md-6"><label class="form-label">Model kop</label><select class="form-select report-preview-input" name="gaya_kop" id="gayaKop"><?php foreach(['formal'=>'Formal bergaris','minimal'=>'Minimal','balok'=>'Balok warna'] as $value=>$label):?><option value="<?=$value?>" <?=$settings['gaya_kop']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
        <div class="col-md-6"><label class="form-label">Logo (maks. 2 MB)</label><input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/*"></div>
      </div></div>
    </div>
    <div class="card">
      <div class="card-header bg-white py-3"><span class="eyebrow">Tipografi dan Warna</span><h5 class="mb-0">Tampilan isi laporan</h5></div>
      <div class="card-body"><div class="row g-3">
        <div class="col-12"><label class="form-label required">Judul laporan</label><input class="form-control report-preview-input" required name="judul_laporan" id="judulLaporan" value="<?=e($settings['judul_laporan'])?>"></div>
        <div class="col-md-4"><label class="form-label">Jenis huruf</label><select class="form-select report-preview-input" name="font_family" id="fontFamily"><?php foreach(['DejaVu Sans'=>'Sans — modern','DejaVu Serif'=>'Serif — formal','DejaVu Sans Mono'=>'Mono — teknis'] as $value=>$label):?><option value="<?=$value?>" <?=$settings['font_family']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
        <div class="col-md-2"><label class="form-label">Ukuran isi</label><input class="form-control report-preview-input" type="number" min="7.5" max="12" step=".1" name="font_size" id="fontSize" value="<?=e($settings['font_size'])?>"></div>
        <div class="col-md-3"><label class="form-label">Warna utama</label><input class="form-control form-control-color w-100 report-preview-input" type="color" name="warna_utama" id="warnaUtama" value="<?=e($settings['warna_utama'])?>"></div>
        <div class="col-md-3"><label class="form-label">Warna aksen</label><input class="form-control form-control-color w-100 report-preview-input" type="color" name="warna_aksen" id="warnaAksen" value="<?=e($settings['warna_aksen'])?>"></div>
        <div class="col-12"><label class="form-label">Teks kaki halaman</label><input class="form-control" name="footer_text" value="<?=e($settings['footer_text'])?>" placeholder="Nama laboratorium · universitas"></div>
      </div></div>
      <div class="card-footer bg-white text-end"><?php if(can('super_admin')):?><button class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Simpan pengaturan</button><?php else:?><span class="text-secondary">Hanya Super Admin yang dapat menyimpan.</span><?php endif;?></div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="report-setting-preview"><div class="card-header bg-white py-3"><b>Pratinjau tampilan</b></div>
      <div class="report-paper" id="reportPaper" style="--report-primary:<?=e($settings['warna_utama'])?>;--report-accent:<?=e($settings['warna_aksen'])?>;font-family:'<?=e($settings['font_family'])?>'">
        <div class="report-kop-preview <?=e($settings['gaya_kop'])?>" id="kopPreview">
          <div class="report-kop-logo d-flex align-items-center justify-content-center"><i class="bi bi-building fs-3"></i></div>
          <div><div class="report-kop-name" id="previewKopNama"><?=e($settings['kop_nama'])?></div><div class="report-kop-sub" id="previewKopSub"><?=e(trim($settings['kop_subjudul'].' · '.$settings['kop_alamat'],' ·'))?></div></div>
        </div>
        <div class="report-preview-title" id="previewTitle"><?=e($settings['judul_laporan'])?></div>
        <p class="mt-4">Contoh isi laporan penyelidikan tanah. Lebar teks menggunakan seluruh area halaman.</p>
      </div>
    </div>
  </div>
</div></form>
<script>
(()=>{const q=id=>document.getElementById(id),paper=q('reportPaper'),kop=q('kopPreview'),sync=()=>{paper.style.setProperty('--report-primary',q('warnaUtama').value);paper.style.setProperty('--report-accent',q('warnaAksen').value);paper.style.fontFamily=q('fontFamily').value;paper.style.fontSize=q('fontSize').value+'px';kop.className='report-kop-preview '+q('gayaKop').value;q('previewKopNama').textContent=q('kopNama').value;q('previewKopSub').textContent=[q('kopSubjudul').value,q('kopAlamat').value].filter(Boolean).join(' · ');q('previewTitle').textContent=q('judulLaporan').value};document.querySelectorAll('.report-preview-input').forEach(el=>el.addEventListener('input',sync));sync()})();
</script>
<?php require APP_ROOT.'/includes/footer.php';?>
