<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require APP_ROOT.'/laporan/_report.php';

$code=strtoupper(trim((string)($_GET['kode']??'')));
$type=($_GET['jenis']??'report')==='signer'?'signer':'report';
$match=null;$reportNumber='';
$projects=$pdo->query('SELECT * FROM proyek ORDER BY id DESC')->fetchAll();
foreach($projects as $project){
    $q=$pdo->prepare('SELECT tanggal_pengujian FROM titik_sondir WHERE proyek_id=? AND tanggal_pengujian IS NOT NULL ORDER BY tanggal_pengujian DESC LIMIT 1');
    $q->execute([(int)$project['id']]);$date=$q->fetchColumn()?:date('Y-m-d',strtotime((string)$project['created_at']));
    $reportNumber='SND/'.str_pad((string)$project['id'],3,'0',STR_PAD_LEFT).'/LAB-UM.BTN/'.report_roman_month((int)date('n',strtotime((string)$date))).'/'.date('Y',strtotime((string)$date));
    if(hash_equals(report_legal_token($project,$reportNumber),$code)){$match=$project;break;}
}
$lab=$pdo->query('SELECT * FROM laboratorium ORDER BY id LIMIT 1')->fetch()?:[];
$valid=(bool)$match;
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verifikasi Legalitas Laporan</title><style>
body{margin:0;background:#eef3f7;color:#17324d;font-family:Arial,sans-serif}.wrap{max-width:700px;margin:40px auto;padding:20px}.card{background:#fff;border-radius:18px;padding:28px;box-shadow:0 12px 35px rgba(23,50,77,.12)}.mark{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;font-size:32px;background:<?=$valid?'#dcfce7':'#fee2e2'?>;color:<?=$valid?'#166534':'#991b1b'?>}.status{color:<?=$valid?'#166534':'#991b1b'?>}table{width:100%;border-collapse:collapse;margin-top:20px}td{padding:10px;border-bottom:1px solid #dce5ec}td:first-child{font-weight:bold;width:35%}.code{margin-top:20px;padding:12px;background:#f3f6f8;border-radius:8px;font-family:monospace;word-break:break-all}.note{font-size:13px;color:#64748b;margin-top:18px}</style></head><body><div class="wrap"><div class="card"><div class="mark"><?=$valid?'✓':'!'?></div>
<h1 class="status"><?=$valid?'Dokumen terverifikasi':'Kode tidak valid'?></h1>
<?php if($valid):?><p><?=$type==='signer'?'Kode ini mengesahkan identitas penanda tangan elektronik Kepala Laboratorium.':'Kode ini mengesahkan legalitas elektronik laporan proyek.'?></p><table>
<tr><td>Nomor laporan</td><td><?=e($reportNumber)?></td></tr><tr><td>Proyek</td><td><?=e($match['nama_proyek'])?></td></tr><tr><td>Kode proyek</td><td><?=e($match['kode_proyek'])?></td></tr>
<?php if($type==='signer'):?><tr><td>Penanda tangan</td><td><?=e($lab['kepala_laboratorium']?:'MUHAMMAD ABDU, S.T., M.T')?></td></tr><tr><td>Jabatan</td><td>Kepala Laboratorium</td></tr><?php else:?><tr><td>Penerbit</td><td><?=e($lab['nama_laboratorium']??'Laboratorium Teknik Sipil')?></td></tr><tr><td>Lokasi pekerjaan</td><td><?=e($match['alamat_lokasi']?:'-')?></td></tr><?php endif;?></table>
<?php else:?><p>Kode yang dipindai tidak cocok dengan arsip laporan pada sistem.</p><?php endif;?>
<div class="code"><?=e($code?:'Kode tidak tersedia')?></div><div class="note">Halaman verifikasi elektronik Sistem Informasi Pengujian Sondir.</div></div></div></body></html>
