<?php
declare(strict_types=1);

function permohonan_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS akun_pemohon (
      id INT AUTO_INCREMENT PRIMARY KEY,nama_lengkap VARCHAR(150) NOT NULL,username VARCHAR(100) NOT NULL UNIQUE,
      email VARCHAR(150) NOT NULL UNIQUE,whatsapp VARCHAR(30),password VARCHAR(255) NOT NULL,
      status ENUM('aktif','diblokir') DEFAULT 'aktif',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS permohonan (
      id INT AUTO_INCREMENT PRIMARY KEY,nomor_permohonan VARCHAR(40) NOT NULL UNIQUE,pemohon_id INT NOT NULL,
      nama_klien VARCHAR(150) NOT NULL,nama_perusahaan VARCHAR(200),nama_kontak VARCHAR(150),whatsapp VARCHAR(30),email VARCHAR(150),
      alamat_klien TEXT,kabupaten_klien VARCHAR(100),provinsi_klien VARCHAR(100),npwp VARCHAR(50),
      nama_proyek VARCHAR(200) NOT NULL,nama_pekerjaan VARCHAR(200),pemilik_pekerjaan VARCHAR(200),alamat_lokasi TEXT,
      desa VARCHAR(100),kecamatan VARCHAR(100),kabupaten VARCHAR(100),provinsi VARCHAR(100),jumlah_titik_rencana INT DEFAULT 1,
      tanggal_rencana DATE,catatan_pemohon TEXT,status ENUM('diajukan','diterima','ditolak') DEFAULT 'diajukan',
      catatan_admin TEXT,klien_id INT NULL,proyek_id INT NULL,diproses_oleh INT NULL,diproses_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
      FOREIGN KEY(pemohon_id) REFERENCES akun_pemohon(id),FOREIGN KEY(klien_id) REFERENCES klien(id),
      FOREIGN KEY(proyek_id) REFERENCES proyek(id),FOREIGN KEY(diproses_oleh) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function permohonan_number(PDO $pdo): string
{
    $prefix='PMH-'.date('Ym').'-';
    $q=$pdo->prepare('SELECT COUNT(*) FROM permohonan WHERE nomor_permohonan LIKE ?');
    $q->execute([$prefix.'%']);
    return $prefix.str_pad((string)((int)$q->fetchColumn()+1),4,'0',STR_PAD_LEFT);
}

function pemohon_logged_in(): bool { return !empty($_SESSION['pemohon']); }
