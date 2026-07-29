<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_role(['super_admin','operator']);
$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT t.*,p.nama_proyek,a.kode_alat,a.nama_alat,a.faktor_kalibrasi_konus fk,a.faktor_kalibrasi_total ft,a.luas_piston api,a.luas_konus ac,a.luas_selimut ass,a.kapasitas_maksimum,a.satuan_kapasitas FROM titik_sondir t JOIN proyek p ON p.id=t.proyek_id JOIN alat_sondir a ON a.id=t.alat_id WHERE t.id=?");
$q->execute([$id]);
$titik=$q->fetch();
if(!$titik){http_response_code(404);exit('Titik sondir tidak ditemukan.');}
if(in_array($titik['status'],['disetujui','diterbitkan'],true)&&!can('super_admin')){http_response_code(403);exit('Data sudah dikunci.');}
$q=$pdo->prepare('SELECT * FROM hasil_sondir WHERE titik_sondir_id=? ORDER BY kedalaman');
$q->execute([$id]);
$hasil=$q->fetchAll();
$masterId=(int)($titik['parent_id']?:$titik['id']);
$q=$pdo->prepare('SELECT id,kode_titik,nama_titik,nomor_urut,status,latitude,longitude FROM titik_sondir WHERE id=? OR parent_id=? ORDER BY nomor_urut,id');
$q->execute([$masterId,$masterId]);
$tabTitik=$q->fetchAll();
$pageTitle='Input '.$titik['kode_titik'];
require APP_ROOT.'/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="<?=url('assets/css/sondir.css')?>?v=<?=filemtime(APP_ROOT.'/assets/css/sondir.css')?>">
<div class="page-heading"><div><a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Pengujian</a><h2 class="mt-2 mb-0"><?=e($titik['nama_proyek'])?></h2><p>Input pengujian per titik - formulir SNI 2827:2008</p></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="download-template.php"><i class="bi bi-download"></i> Template Excel</a><a class="btn btn-outline-primary" href="<?=url('dokumentasi/index.php?titik_id='.$id)?>"><i class="bi bi-camera"></i> Dokumentasi</a><a class="btn btn-outline-primary" href="import-excel.php?id=<?=$id?>"><i class="bi bi-file-earmark-spreadsheet"></i> Impor Excel/CSV</a><a class="btn btn-outline-primary" href="grafik.php?id=<?=$id?>"><i class="bi bi-graph-up"></i> Grafik</a></div></div>
<ul class="nav nav-tabs sondir-tabs mb-3"><?php foreach($tabTitik as $tab):?><li class="nav-item"><a class="nav-link <?=$tab['id']===$id?'active':''?>" href="input.php?id=<?=$tab['id']?>"><span>Sondir <?=e($tab['nomor_urut'])?></span><small><?=e($tab['kode_titik'])?></small></a></li><?php endforeach;?><li class="nav-item"><button type="button" class="nav-link" id="mapSummaryTab"><span><i class="bi bi-map me-1"></i>Peta Sondir</span><small><?=count($tabTitik)?> titik terdaftar</small></button></li></ul>
<div class="card mb-3 d-none overflow-hidden" id="pointMapPanel"><div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2"><div><span class="eyebrow">Peta Titik Sondir</span><h5 class="mb-0">Seluruh koordinat dalam satu peta</h5></div><div class="d-flex gap-2 align-items-center"><a class="btn btn-sm btn-outline-primary d-none" id="overviewGoogleMaps" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Google Maps</a><button type="button" class="btn btn-sm btn-outline-secondary" id="saveMapPosition" disabled><i class="bi bi-cloud-check"></i> Tersimpan otomatis</button></div></div><div id="pointOverviewMap" style="height:65vh;min-height:480px"></div><div class="card-footer bg-white"><div class="small text-secondary"><i class="bi bi-arrows-move me-1"></i>Semua ikon Sondir dapat digeser dan koordinat masing-masing langsung tersimpan otomatis.</div><div class="small text-warning-emphasis mt-1" id="mapMissingPoints"></div></div></div>
<div id="sondirInputPanel">
<div class="card mb-3" id="metaCard"><div class="card-header bg-white py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center"><div><span class="eyebrow">Kepala Data</span><h5 class="mb-0" id="metaTitle"><?=e($titik['nama_titik']?:'Sondir '.$titik['nomor_urut'])?></h5></div><div class="d-flex align-items-center gap-2"><?=status_badge($titik['status'])?><button type="button" class="btn btn-sm btn-outline-primary" id="editMeta"><i class="bi bi-pencil-square"></i> Edit</button><button type="button" class="btn btn-sm btn-light d-none" id="cancelMeta">Batal</button><button type="button" class="btn btn-sm btn-primary d-none" id="saveMeta"><i class="bi bi-check2-circle"></i> Simpan</button></div></div><div class="card-body"><div class="row g-3">
<div class="col-md-4"><label class="form-label">Nama titik</label><input disabled class="form-control meta-field" id="metaName" value="<?=e($titik['nama_titik'])?>"></div>
<div class="col-md-4"><label class="form-label">Tanggal pengujian</label><input disabled type="date" class="form-control meta-field" id="metaDate" value="<?=e($titik['tanggal_pengujian'])?>"></div>
<div class="col-md-2"><label class="form-label">Elevasi (m)</label><input disabled inputmode="decimal" class="form-control meta-field" id="metaElevation" value="<?=e($titik['elevasi'])?>"></div>
<div class="col-md-2"><label class="form-label">Muka air tanah (m)</label><input disabled inputmode="decimal" class="form-control meta-field" id="metaWater" value="<?=e($titik['muka_air_tanah'])?>"></div>
<div class="col-md-4"><label class="form-label">Latitude</label><input disabled inputmode="decimal" class="form-control meta-field" id="metaLatitude" value="<?=e($titik['latitude'])?>" placeholder="-5.147665"></div>
<div class="col-md-4"><label class="form-label">Longitude</label><input disabled inputmode="decimal" class="form-control meta-field" id="metaLongitude" value="<?=e($titik['longitude'])?>" placeholder="119.432732"></div>
<div class="col-md-4 d-flex align-items-end gap-2"><button disabled type="button" class="btn btn-outline-primary flex-fill meta-action" id="deviceLocation"><i class="bi bi-crosshair"></i> Lokasi perangkat</button><button disabled type="button" class="btn btn-outline-primary flex-fill meta-action" id="mapLocation" data-bs-toggle="modal" data-bs-target="#mapModal"><i class="bi bi-map"></i> Pilih dari peta</button></div>
<div class="col-12"><label class="form-label">Deskripsi posisi</label><input disabled class="form-control meta-field" id="metaDescription" value="<?=e($titik['deskripsi_posisi'])?>" placeholder="Contoh: sudut timur laut bangunan"></div>
</div></div></div>
<div class="card mb-3"><div class="card-body"><div class="row g-3 align-items-center"><div class="col-lg-6"><span class="eyebrow">Alat dari Titik Sondir</span><div class="d-flex align-items-center gap-3 mt-1"><div class="stat-icon"><i class="bi bi-tools"></i></div><div><h5 class="mb-0"><?=e($titik['kode_alat'].' - '.$titik['nama_alat'])?></h5><small class="text-secondary">Alat mengikuti pilihan pada data titik dan tidak dapat diganti dari halaman pengujian.</small></div></div></div><div class="col"><div class="tool-summary"><div class="row"><div class="col"><small>Api / Ac / As</small><div id="areaSummary"><?=e($titik['api'].' / '.$titik['ac'].' / '.$titik['ass'])?> cm²</div></div><div class="col tool-metric"><small>Kapasitas</small><div id="capacitySummary"><?=e($titik['kapasitas_maksimum'].' '.$titik['satuan_kapasitas'])?></div></div><div class="col tool-metric"><small>Interval</small><div id="intervalSummary"><?=e($titik['interval_kedalaman'])?> m</div></div></div></div></div></div></div></div>
<div class="alert alert-info py-2"><i class="bi bi-info-circle me-2"></i>Isi <strong>Cw</strong> dan <strong>Tw</strong>. Perkiraan jenis tanah beserta konsistensi/kepadatannya dihitung otomatis dari <strong>qc</strong> dan <strong>Rf</strong>. Untuk paste Excel gunakan urutan <strong>Kedalaman, Cw, Tw</strong>.</div>
<div class="card"><div class="spreadsheet-wrap"><table class="table table-bordered spreadsheet mb-0" id="sondirTable"><thead><tr>
<th>No</th>
<th><span class="column-name">Z</span><small class="column-unit">m</small></th>
<th><span class="column-name">Cw</span><small class="column-unit">kg/cm²</small></th>
<th><span class="column-name">Tw</span><small class="column-unit">kg/cm²</small></th>
<th><span class="column-name">Kw</span><small class="column-unit">kg/cm²</small></th>
<th><span class="column-name">qc</span><small class="column-unit">kg/cm²</small></th>
<th><span class="column-name">fs</span><small class="column-unit">kg/cm²</small></th>
<th><span class="column-name">fs.20</span><small class="column-unit">kg/cm</small></th>
<th><span class="column-name">Tf</span><small class="column-unit">kg/cm</small></th>
<th><span class="column-name">FR</span><small class="column-unit">%</small></th>
<th class="soil-profile-heading">Perkiraan Jenis Tanah</th>
<th>Aksi</th>
</tr></thead><tbody></tbody></table></div><div class="sticky-actions d-flex flex-wrap gap-2 justify-content-between"><div><button class="btn btn-light" id="addRow"><i class="bi bi-plus-lg"></i> Tambah baris</button><button class="btn btn-light" id="fillDepth">Isi kedalaman</button></div><div><span class="text-secondary small me-2" id="saveState">Belum ada perubahan</span><button class="btn btn-outline-primary" data-status="draft">Simpan draft</button><button class="btn btn-primary" data-status="menunggu_pemeriksaan">Simpan & selesaikan</button></div></div></div>
</div>
<script>window.sondirConfig=<?=json_encode(['id'=>$id,'alatId'=>(int)$titik['alat_id'],'interval'=>(float)$titik['interval_kedalaman'],'fk'=>(float)$titik['fk'],'ft'=>(float)$titik['ft'],'apiArea'=>(float)$titik['api'],'ac'=>(float)$titik['ac'],'as'=>(float)$titik['ass'],'capacity'=>(float)$titik['kapasitas_maksimum'],'unit'=>$titik['satuan_kapasitas'],'csrf'=>csrf_token(),'api'=>url('api/simpan-hasil.php'),'metaApi'=>url('api/simpan-kepala.php'),'coordinateApi'=>url('api/simpan-koordinat.php'),'searchApi'=>url('api/cari-lokasi.php'),'rows'=>$hasil,'points'=>array_map(fn($tab)=>['id'=>(int)$tab['id'],'nomor'=>(int)$tab['nomor_urut'],'nama'=>$tab['nama_titik'],'latitude'=>$tab['latitude']!==null?(float)$tab['latitude']:null,'longitude'=>$tab['longitude']!==null?(float)$tab['longitude']:null],$tabTitik)],JSON_UNESCAPED_UNICODE)?>;</script>
<div class="modal fade" id="mapModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><div><span class="eyebrow">Koordinat titik</span><h5 class="modal-title">Cari, klik, atau geser penanda</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><form class="p-3 border-bottom bg-white" id="mapSearchForm"><div class="input-group"><input class="form-control" id="mapSearchInput" placeholder="Cari jalan, kelurahan, kecamatan, atau kota..." autocomplete="off"><button class="btn btn-primary"><i class="bi bi-search"></i> Cari lokasi</button></div><div class="list-group mt-2 d-none" id="mapSearchResults"></div></form><div id="coordinateMap" style="height:55vh;min-height:400px"></div></div><div class="modal-footer"><a class="btn btn-outline-primary d-none" id="openGoogleMaps" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Buka di Google Maps</a><span class="me-auto text-secondary" id="mapCoordinateText">Belum ada lokasi dipilih</span><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Gunakan koordinat</button></div></div></div></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?=url('assets/js/sondir.js')?>?v=<?=filemtime(APP_ROOT.'/assets/js/sondir.js')?>"></script>
<?php require APP_ROOT.'/includes/footer.php';
