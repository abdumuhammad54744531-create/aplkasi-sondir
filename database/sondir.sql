CREATE DATABASE IF NOT EXISTS db_sondir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_sondir;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS login_log,audit_log,permohonan,akun_pemohon,pengaturan_laporan,pengaturan_rumus,laporan,pemeriksaan,dokumentasi_sondir,klasifikasi_tanah,hasil_sondir,titik_sondir,alat_sondir,proyek,klien,laboratorium,users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,nama_lengkap VARCHAR(150) NOT NULL,username VARCHAR(100) NOT NULL UNIQUE,email VARCHAR(150) UNIQUE,whatsapp VARCHAR(30),password VARCHAR(255) NOT NULL,
 level ENUM('super_admin','admin_lab','operator','pemeriksa') NOT NULL,jabatan VARCHAR(150),nomor_identitas VARCHAR(100),foto VARCHAR(255),tanda_tangan VARCHAR(255),
 status ENUM('aktif','tidak_aktif') DEFAULT 'aktif',last_login DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE laboratorium (
 id INT AUTO_INCREMENT PRIMARY KEY,nama_laboratorium VARCHAR(200) NOT NULL,nama_instansi VARCHAR(200),nomor_registrasi VARCHAR(100),nomor_akreditasi VARCHAR(100),berlaku_akreditasi DATE,
 alamat TEXT,desa VARCHAR(100),kecamatan VARCHAR(100),kabupaten VARCHAR(100),provinsi VARCHAR(100),kode_pos VARCHAR(20),telepon VARCHAR(30),whatsapp VARCHAR(30),email VARCHAR(150),website VARCHAR(150),
 logo VARCHAR(255),kepala_laboratorium VARCHAR(150),nip_kepala VARCHAR(100),footer_laporan TEXT,catatan_laporan TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE klien (
 id INT AUTO_INCREMENT PRIMARY KEY,kode_klien VARCHAR(30) NOT NULL UNIQUE,nama_klien VARCHAR(150) NOT NULL,nama_perusahaan VARCHAR(200),nama_kontak VARCHAR(150),whatsapp VARCHAR(30),email VARCHAR(150),
 alamat TEXT,kabupaten VARCHAR(100),provinsi VARCHAR(100),npwp VARCHAR(50),catatan TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE proyek (
 id INT AUTO_INCREMENT PRIMARY KEY,klien_id INT NOT NULL,kode_proyek VARCHAR(50) NOT NULL UNIQUE,nomor_pekerjaan VARCHAR(100),nama_proyek VARCHAR(200) NOT NULL,nama_pekerjaan VARCHAR(200),
 pemilik_pekerjaan VARCHAR(200),kontraktor VARCHAR(200),konsultan_perencana VARCHAR(200),konsultan_pengawas VARCHAR(200),alamat_lokasi TEXT,desa VARCHAR(100),kecamatan VARCHAR(100),kabupaten VARCHAR(100),provinsi VARCHAR(100),
 latitude DECIMAL(10,8),longitude DECIMAL(11,8),tanggal_permohonan DATE,tanggal_mulai DATE,tanggal_selesai DATE,jumlah_titik_rencana INT DEFAULT 0,penanggung_jawab_id INT NULL,operator_id INT NULL,pemeriksa_id INT NULL,
 status ENUM('draft','berjalan','selesai','diarsipkan','dibatalkan') DEFAULT 'draft',surat_permohonan VARCHAR(255),surat_perintah_kerja VARCHAR(255),kontrak VARCHAR(255),denah_lokasi VARCHAR(255),dokumen_pendukung VARCHAR(255),
 catatan TEXT,created_by INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 FOREIGN KEY(klien_id) REFERENCES klien(id),FOREIGN KEY(penanggung_jawab_id) REFERENCES users(id),FOREIGN KEY(operator_id) REFERENCES users(id),FOREIGN KEY(pemeriksa_id) REFERENCES users(id),FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE alat_sondir (
 id INT AUTO_INCREMENT PRIMARY KEY,kode_alat VARCHAR(30) NOT NULL UNIQUE,nama_alat VARCHAR(150) NOT NULL,jenis_alat VARCHAR(100),merek VARCHAR(100),model VARCHAR(100),nomor_seri VARCHAR(100),
 kapasitas_maksimum DECIMAL(15,4),satuan_kapasitas VARCHAR(30),diameter_piston DECIMAL(15,6),diameter_konus DECIMAL(15,6),diameter_selimut DECIMAL(15,6),panjang_selimut_geser DECIMAL(15,6),luas_piston DECIMAL(15,6) DEFAULT 20,luas_konus DECIMAL(15,6),luas_selimut DECIMAL(15,6),rasio_luas DECIMAL(15,6),faktor_kalibrasi_konus DECIMAL(15,6) DEFAULT 1,
 faktor_kalibrasi_total DECIMAL(15,6) DEFAULT 1,interval_standar DECIMAL(10,4) DEFAULT .20,nomor_sertifikat VARCHAR(100),tanggal_kalibrasi DATE,tanggal_kedaluwarsa DATE,lembaga_kalibrasi VARCHAR(200),
 file_sertifikat VARCHAR(255),kondisi VARCHAR(100),status ENUM('aktif','tidak_aktif','perbaikan') DEFAULT 'aktif',catatan TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE titik_sondir (
 id INT AUTO_INCREMENT PRIMARY KEY,parent_id INT NULL,proyek_id INT NOT NULL,alat_id INT NOT NULL,kode_titik VARCHAR(70) NOT NULL UNIQUE,nama_titik VARCHAR(150),nomor_urut INT NOT NULL,tanggal_pengujian DATE,waktu_mulai TIME,waktu_selesai TIME,
 latitude DECIMAL(10,8),longitude DECIMAL(11,8),koordinat_x DECIMAL(15,4),koordinat_y DECIMAL(15,4),elevasi DECIMAL(10,3),sistem_koordinat VARCHAR(100),deskripsi_posisi TEXT,alamat_lokasi TEXT,operator_id INT NOT NULL,pemeriksa_id INT NULL,
 interval_kedalaman DECIMAL(10,4) DEFAULT .20,kedalaman_rencana DECIMAL(10,3),kedalaman_aktual DECIMAL(10,3),muka_air_tanah DECIMAL(10,3),kondisi_cuaca VARCHAR(100),kondisi_permukaan VARCHAR(150),
 jenis_tanah_permukaan VARCHAR(150),kondisi_alat VARCHAR(150),tahanan_maksimum DECIMAL(15,4),alasan_penghentian TEXT,catatan TEXT,
 status ENUM('draft','sedang_diuji','menunggu_pemeriksaan','perlu_revisi','disetujui','diterbitkan','dibatalkan') DEFAULT 'draft',created_by INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 FOREIGN KEY(parent_id) REFERENCES titik_sondir(id) ON DELETE CASCADE,FOREIGN KEY(proyek_id) REFERENCES proyek(id),FOREIGN KEY(alat_id) REFERENCES alat_sondir(id),FOREIGN KEY(operator_id) REFERENCES users(id),FOREIGN KEY(pemeriksa_id) REFERENCES users(id),FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE hasil_sondir (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL,nomor INT NOT NULL,kedalaman DECIMAL(10,3) NOT NULL,bacaan_konus DECIMAL(15,4) DEFAULT 0,bacaan_total DECIMAL(15,4) DEFAULT 0,
 qc DECIMAL(15,4) DEFAULT 0,qc_mpa DECIMAL(15,6) NULL,hambatan_total DECIMAL(15,4) DEFAULT 0,fs DECIMAL(15,4) DEFAULT 0,jhp DECIMAL(15,4) DEFAULT 0,friction_ratio DECIMAL(15,4) DEFAULT 0,satuan_tekanan VARCHAR(30),
 zona_sbt TINYINT UNSIGNED NULL,batas_zona TINYINT(1) NOT NULL DEFAULT 0,versi_klasifikasi VARCHAR(80) NULL,jenis_tanah VARCHAR(150),keterangan TEXT,status_validasi ENUM('valid','peringatan','tidak_valid') DEFAULT 'valid',pesan_validasi TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 UNIQUE KEY unik_kedalaman(titik_sondir_id,kedalaman),FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE klasifikasi_tanah (
 id INT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL,kedalaman_awal DECIMAL(10,3) NOT NULL,kedalaman_akhir DECIMAL(10,3) NOT NULL,qc_minimum DECIMAL(15,4),qc_maksimum DECIMAL(15,4),qc_rata_rata DECIMAL(15,4),
 fr_rata_rata DECIMAL(15,4),jenis_tanah VARCHAR(150),konsistensi VARCHAR(150),warna VARCHAR(20),deskripsi TEXT,metode ENUM('otomatis','manual') DEFAULT 'manual',catatan TEXT,created_by INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id) ON DELETE CASCADE,FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE dokumentasi_sondir (
 id INT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL,jenis_foto VARCHAR(100),judul VARCHAR(150),keterangan TEXT,nama_file VARCHAR(255) NOT NULL,urutan INT DEFAULT 0,latitude DECIMAL(10,8),longitude DECIMAL(11,8),tanggal_foto DATE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pemeriksaan (
 id INT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL,pemeriksa_id INT NOT NULL,status ENUM('diperiksa','perlu_revisi','disetujui') NOT NULL,catatan TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id),FOREIGN KEY(pemeriksa_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE laporan (
 id INT AUTO_INCREMENT PRIMARY KEY,titik_sondir_id INT NOT NULL UNIQUE,nomor_laporan VARCHAR(100) NOT NULL UNIQUE,kode_verifikasi VARCHAR(100) NOT NULL UNIQUE,hash_dokumen VARCHAR(255),ringkasan_hasil TEXT,rekomendasi TEXT,kesimpulan TEXT,
 catatan_teknis TEXT,file_pdf VARCHAR(255),status ENUM('draft','diterbitkan','dibatalkan') DEFAULT 'draft',diterbitkan_oleh INT NULL,tanggal_diterbitkan DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 FOREIGN KEY(titik_sondir_id) REFERENCES titik_sondir(id),FOREIGN KEY(diterbitkan_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pengaturan_rumus (
 id INT AUTO_INCREMENT PRIMARY KEY,alat_id INT NULL,nama_rumus VARCHAR(150) NOT NULL,faktor_konus DECIMAL(15,6) DEFAULT 1,faktor_total DECIMAL(15,6) DEFAULT 1,luas_konus DECIMAL(15,6),luas_selimut DECIMAL(15,6),jumlah_desimal INT DEFAULT 2,
 satuan_default VARCHAR(30) DEFAULT 'kg/cm2',status ENUM('aktif','tidak_aktif') DEFAULT 'aktif',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,FOREIGN KEY(alat_id) REFERENCES alat_sondir(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pengaturan_laporan (
 id TINYINT PRIMARY KEY DEFAULT 1,kop_nama VARCHAR(200) NOT NULL,kop_subjudul VARCHAR(200),kop_alamat TEXT,
 judul_laporan VARCHAR(200) NOT NULL,font_family VARCHAR(50) DEFAULT 'DejaVu Sans',font_size DECIMAL(4,1) DEFAULT 9.2,
 font_body_family VARCHAR(50) DEFAULT 'DejaVu Sans',font_heading_family VARCHAR(50) DEFAULT 'DejaVu Sans',font_table_family VARCHAR(50) DEFAULT 'DejaVu Sans',font_cover_family VARCHAR(50) DEFAULT 'DejaVu Serif',
 font_body_size DECIMAL(4,1) DEFAULT 9.2,font_heading_size DECIMAL(4,1) DEFAULT 13.0,font_subheading_size DECIMAL(4,1) DEFAULT 11.0,font_table_size DECIMAL(4,1) DEFAULT 6.4,font_caption_size DECIMAL(4,1) DEFAULT 8.0,font_cover_size DECIMAL(4,1) DEFAULT 27.0,line_height DECIMAL(3,2) DEFAULT 1.48,
 margin_top DECIMAL(4,1) DEFAULT 25.0,margin_right DECIMAL(4,1) DEFAULT 15.0,margin_bottom DECIMAL(4,1) DEFAULT 18.0,margin_left DECIMAL(4,1) DEFAULT 15.0,map_type VARCHAR(20) DEFAULT 'satellite',
 show_map TINYINT(1) DEFAULT 1,show_sbt_chart TINYINT(1) DEFAULT 1,sbt_show_connection_line TINYINT(1) DEFAULT 1,sbt_line_style VARCHAR(12) DEFAULT 'solid',show_equipment TINYINT(1) DEFAULT 1,show_foundation TINYINT(1) DEFAULT 1,show_documentation TINYINT(1) DEFAULT 1,
 warna_utama VARCHAR(7) DEFAULT '#173B61',warna_aksen VARCHAR(7) DEFAULT '#F4B400',gaya_kop VARCHAR(30) DEFAULT 'formal',
 logo_path VARCHAR(255),logo_left_path VARCHAR(255),logo_right_path VARCHAR(255),logo_left_position VARCHAR(12) DEFAULT 'left',logo_right_position VARCHAR(12) DEFAULT 'right',
 logo_left_width DECIMAL(4,1) DEFAULT 18,logo_left_height DECIMAL(4,1),logo_left_x DECIMAL(4,1) DEFAULT 0,logo_left_y DECIMAL(4,1) DEFAULT 0,logo_right_width DECIMAL(4,1) DEFAULT 18,logo_right_height DECIMAL(4,1),logo_right_x DECIMAL(4,1) DEFAULT 0,logo_right_y DECIMAL(4,1) DEFAULT 0,
 header_lines_enabled TINYINT(1) DEFAULT 1,header_lines LONGTEXT,header_double_line TINYINT(1) DEFAULT 1,header_line_1_width DECIMAL(4,2) DEFAULT .8,header_line_2_width DECIMAL(4,2) DEFAULT .3,header_line_gap DECIMAL(4,2) DEFAULT .8,header_to_line_gap DECIMAL(4,2) DEFAULT 2,line_to_content_gap DECIMAL(4,2) DEFAULT 4,
 examiner_address TEXT,examiner_city VARCHAR(100),examiner_province VARCHAR(100),examiner_postal_code VARCHAR(20),examiner_phone VARCHAR(60),examiner_email VARCHAR(120),examiner_website VARCHAR(160),signer_name VARCHAR(160),signer_position VARCHAR(160),signer_identity VARCHAR(100),signature_path VARCHAR(255),stamp_path VARCHAR(255),preface_template LONGTEXT,
 footer_text VARCHAR(255),updated_by INT NULL,updated_at DATETIME NULL,
 FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE akun_pemohon (
 id INT AUTO_INCREMENT PRIMARY KEY,nama_lengkap VARCHAR(150) NOT NULL,username VARCHAR(100) NOT NULL UNIQUE,email VARCHAR(150) NOT NULL UNIQUE,whatsapp VARCHAR(30),password VARCHAR(255) NOT NULL,
 status ENUM('aktif','diblokir') DEFAULT 'aktif',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE permohonan (
 id INT AUTO_INCREMENT PRIMARY KEY,nomor_permohonan VARCHAR(40) NOT NULL UNIQUE,pemohon_id INT NOT NULL,
 nama_klien VARCHAR(150) NOT NULL,nama_perusahaan VARCHAR(200),nama_kontak VARCHAR(150),whatsapp VARCHAR(30),email VARCHAR(150),alamat_klien TEXT,kabupaten_klien VARCHAR(100),provinsi_klien VARCHAR(100),npwp VARCHAR(50),
 nama_proyek VARCHAR(200) NOT NULL,nama_pekerjaan VARCHAR(200),pemilik_pekerjaan VARCHAR(200),alamat_lokasi TEXT,desa VARCHAR(100),kecamatan VARCHAR(100),kabupaten VARCHAR(100),provinsi VARCHAR(100),jumlah_titik_rencana INT DEFAULT 1,
 tanggal_rencana DATE,catatan_pemohon TEXT,status ENUM('diajukan','diterima','ditolak') DEFAULT 'diajukan',catatan_admin TEXT,klien_id INT NULL,proyek_id INT NULL,diproses_oleh INT NULL,diproses_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 FOREIGN KEY(pemohon_id) REFERENCES akun_pemohon(id),FOREIGN KEY(klien_id) REFERENCES klien(id),FOREIGN KEY(proyek_id) REFERENCES proyek(id),FOREIGN KEY(diproses_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE audit_log (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,aktivitas VARCHAR(200) NOT NULL,nama_tabel VARCHAR(100),data_id BIGINT NULL,data_lama LONGTEXT,data_baru LONGTEXT,alamat_ip VARCHAR(50),user_agent TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE login_log (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,username VARCHAR(100),status ENUM('berhasil','gagal') NOT NULL,alamat_ip VARCHAR(50),user_agent TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE password_reset_tokens (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,request_ip_hash CHAR(64) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_password_reset_user(user_id),INDEX idx_password_reset_expires(expires_at),INDEX idx_password_reset_ip_created(request_ip_hash,created_at),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO users(nama_lengkap,username,email,password,level,jabatan,status) VALUES
('Administrator Sistem','admin','admin@sondir.test','$2y$10$aMMZjKuVN5V8B1lwSb7t/eZatKsvDFFLzXa1D.AMzi3CIuQbxSSp6','super_admin','Administrator','aktif');
