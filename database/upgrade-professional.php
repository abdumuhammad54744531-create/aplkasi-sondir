<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

function add_column(PDO $pdo,string $table,string $column,string $definition): void
{
    $q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);
    if(!$q->fetchColumn())$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

$projectColumns=[
    'nomor_laporan'=>'VARCHAR(100) NULL AFTER nomor_pekerjaan','nama_paket'=>'VARCHAR(200) NULL AFTER nama_pekerjaan','nama_bangunan'=>'VARCHAR(200) NULL AFTER nama_paket','pemberi_tugas'=>'VARCHAR(200) NULL AFTER pemilik_pekerjaan','laboratorium_pelaksana'=>'VARCHAR(200) NULL AFTER konsultan_pengawas','nomor_kontrak'=>'VARCHAR(100) NULL AFTER laboratorium_pelaksana','tanggal_kontrak'=>'DATE NULL AFTER nomor_kontrak','sistem_koordinat'=>'VARCHAR(100) NULL AFTER longitude','zona_utm'=>'VARCHAR(30) NULL AFTER sistem_koordinat','elevasi_lokasi'=>'DECIMAL(10,3) NULL AFTER zona_utm','satuan_elevasi'=>'VARCHAR(20) DEFAULT \'m\' AFTER elevasi_lokasi','tanggal_laporan'=>'DATE NULL AFTER tanggal_selesai','jenis_bangunan'=>'VARCHAR(150) NULL AFTER tanggal_laporan','jumlah_lantai'=>'INT NULL AFTER jenis_bangunan','fungsi_bangunan'=>'VARCHAR(200) NULL AFTER jumlah_lantai','tujuan_penyelidikan'=>'TEXT NULL AFTER fungsi_bangunan','ruang_lingkup'=>'JSON NULL AFTER tujuan_penyelidikan','ruang_lingkup_keterangan'=>'TEXT NULL AFTER ruang_lingkup',
];
foreach($projectColumns as $column=>$definition)add_column($pdo,'proyek',$column,$definition);

$pointColumns=[
    'northing'=>'DECIMAL(15,4) NULL AFTER koordinat_y','easting'=>'DECIMAL(15,4) NULL AFTER northing','datum_elevasi'=>'VARCHAR(100) NULL AFTER elevasi','pengawas_id'=>'INT NULL AFTER pemeriksa_id','penanggung_jawab_id'=>'INT NULL AFTER pengawas_id','alasan_penghentian_kode'=>'VARCHAR(50) NULL AFTER alasan_penghentian','kedalaman_penghentian'=>'DECIMAL(10,3) NULL AFTER alasan_penghentian_kode','qc_terakhir'=>'DECIMAL(15,4) NULL AFTER kedalaman_penghentian','waktu_penghentian'=>'TIME NULL AFTER qc_terakhir','catatan_penghentian'=>'TEXT NULL AFTER waktu_penghentian',
];
foreach($pointColumns as $column=>$definition)add_column($pdo,'titik_sondir',$column,$definition);

foreach(['teknologi_alat'=>"ENUM('mechanical','electronic','cptu') DEFAULT 'mechanical' AFTER jenis_alat",'tipe_konus'=>"ENUM('tunggal','bikonus','electronic') DEFAULT 'bikonus' AFTER teknologi_alat",'kapasitas_manometer'=>'DECIMAL(15,4) NULL AFTER kapasitas_maksimum'] as $column=>$definition)add_column($pdo,'alat_sondir',$column,$definition);
foreach(['qc_kpa'=>'DECIMAL(18,6) NULL AFTER qc_mpa','fs_kpa'=>'DECIMAL(18,6) NULL AFTER fs','fs_mpa'=>'DECIMAL(18,9) NULL AFTER fs_kpa','calculation_method'=>'VARCHAR(150) NULL AFTER versi_klasifikasi','calculation_version'=>'VARCHAR(50) NULL AFTER calculation_method','validation_json'=>'JSON NULL AFTER pesan_validasi','source_type'=>"ENUM('manual','excel','paste','api') DEFAULT 'manual' AFTER validation_json"] as $column=>$definition)add_column($pdo,'hasil_sondir',$column,$definition);
foreach(['proyek_id'=>'INT NULL AFTER id','revision_no'=>'INT DEFAULT 0 AFTER status','workflow_status'=>"ENUM('draft','field_data_complete','calculated','interpreted','reviewed','approved','final') DEFAULT 'draft' AFTER revision_no",'readiness_score'=>'DECIMAL(5,2) DEFAULT 0 AFTER workflow_status','finalized_at'=>'DATETIME NULL AFTER tanggal_diterbitkan'] as $column=>$definition)add_column($pdo,'laporan',$column,$definition);

$pdo->exec("CREATE TABLE IF NOT EXISTS metode_referensi (
 id INT AUTO_INCREMENT PRIMARY KEY,kode VARCHAR(60) NOT NULL UNIQUE,nama_metode VARCHAR(200) NOT NULL,penulis VARCHAR(200),tahun INT,judul_referensi TEXT,parameter_dihitung VARCHAR(200),persamaan TEXT,batas_penerapan TEXT,satuan VARCHAR(100),catatan TEXT,status ENUM('aktif','tidak_aktif') DEFAULT 'aktif',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$seed=$pdo->prepare("INSERT INTO metode_referensi(kode,nama_metode,penulis,tahun,judul_referensi,parameter_dihitung,persamaan,batas_penerapan,satuan,catatan) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nama_metode=VALUES(nama_metode),catatan=VALUES(catatan)");
$seed->execute(['SNI-2827-2008','Cara uji penetrasi lapangan dengan alat sondir','Badan Standardisasi Nasional',2008,'SNI 2827:2008','qc, fs, Rf, Tf','Sesuai konfigurasi geometrik mechanical sondir','Pengujian penetrasi lapangan dengan alat sondir','Satuan sesuai konfigurasi alat','Acuan pelaksanaan utama.']);
$seed->execute(['ROBERTSON-SBT-1986','Robertson Soil Behavior Type - 12 zona','Robertson et al.',1986,'Use of piezometer cone data','Soil Behavior Type','Diagram qc non-normalisasi terhadap friction ratio','Korelasi perilaku tanah; bukan identifikasi tekstur laboratorium','qc MPa; FR %','Diagram 12 zona yang didigitasi dan direview.']);
$seed->execute(['MEYERHOF-PILE','Korelasi daya dukung tiang dari sondir','Meyerhof',null,'CPT pile capacity correlation','Qp, Qs, Qu, Qall','Metode wajib dipilih dan asumsi/faktor keamanan dicatat','Korelasi awal; verifikasi desain dan penurunan diperlukan','kN','Tidak boleh dianggap formula universal.']);

$pdo->exec("CREATE TABLE IF NOT EXISTS hasil_parameter_korelasi (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL,metode_referensi_id INT NOT NULL,nama_parameter VARCHAR(150) NOT NULL,nilai DECIMAL(20,8) NULL,satuan VARCHAR(50),confidence VARCHAR(50),remarks TEXT,input_json JSON,created_by INT NULL,reviewed_by INT NULL,status ENUM('draft','reviewed','approved') DEFAULT 'draft',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,INDEX(titik_sondir_id),FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id),FOREIGN KEY(metode_referensi_id) REFERENCES metode_referensi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS analisis_fondasi (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,proyek_id INT NOT NULL,titik_sondir_id INT NULL,jenis ENUM('dangkal','dalam') NOT NULL,metode_referensi_id INT NOT NULL,nama_analisis VARCHAR(200) NOT NULL,input_json JSON NOT NULL,asumsi TEXT,catatan TEXT,status ENUM('draft','reviewed','approved') DEFAULT 'draft',created_by INT NULL,reviewed_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,INDEX(proyek_id),INDEX(titik_sondir_id),FOREIGN KEY(proyek_id) REFERENCES proyek(id),FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id),FOREIGN KEY(metode_referensi_id) REFERENCES metode_referensi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS hasil_fondasi (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,analisis_fondasi_id BIGINT NOT NULL,kedalaman DECIMAL(10,3),qp DECIMAL(20,6),qs DECIMAL(20,6),qu DECIMAL(20,6),faktor_keamanan DECIMAL(10,4),qall DECIMAL(20,6),satuan VARCHAR(30) DEFAULT 'kN',substitusi TEXT,remarks TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(analisis_fondasi_id) REFERENCES analisis_fondasi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS report_revisions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,proyek_id INT NOT NULL,revision_no INT NOT NULL,tanggal DATE NOT NULL,uraian_perubahan TEXT NOT NULL,dibuat_oleh INT NULL,diperiksa_oleh INT NULL,disetujui_oleh INT NULL,status ENUM('draft','reviewed','approved','final') DEFAULT 'draft',hash_dokumen VARCHAR(255),file_pdf VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_project_revision(proyek_id,revision_no),FOREIGN KEY(proyek_id) REFERENCES proyek(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS report_readiness_checks (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,proyek_id INT NOT NULL,check_code VARCHAR(80) NOT NULL,label VARCHAR(200) NOT NULL,status ENUM('valid','warning','error') NOT NULL,detail TEXT,checked_at DATETIME NOT NULL,UNIQUE KEY uq_project_check(proyek_id,check_code),FOREIGN KEY(proyek_id) REFERENCES proyek(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "Upgrade schema profesional selesai tanpa drop tabel/data.\n";

