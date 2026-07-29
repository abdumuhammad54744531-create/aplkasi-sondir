USE db_sondir;
INSERT INTO users(nama_lengkap,username,email,password,level,jabatan,status) VALUES
('Admin Laboratorium','adminlab','adminlab@sondir.test','$2y$10$aMMZjKuVN5V8B1lwSb7t/eZatKsvDFFLzXa1D.AMzi3CIuQbxSSp6','admin_lab','Administrasi Laboratorium','aktif'),
('Operator Lapangan','operator','operator@sondir.test','$2y$10$aMMZjKuVN5V8B1lwSb7t/eZatKsvDFFLzXa1D.AMzi3CIuQbxSSp6','operator','Teknisi Sondir','aktif'),
('Kepala Laboratorium','pemeriksa','pemeriksa@sondir.test','$2y$10$aMMZjKuVN5V8B1lwSb7t/eZatKsvDFFLzXa1D.AMzi3CIuQbxSSp6','pemeriksa','Kepala Laboratorium','aktif');
INSERT INTO laboratorium(nama_laboratorium,nama_instansi,nomor_registrasi,nomor_akreditasi,berlaku_akreditasi,alamat,kabupaten,provinsi,telepon,email,kepala_laboratorium,footer_laporan)
VALUES('Laboratorium Mekanika Tanah','PT Rekayasa Geoteknik Nusantara','REG-LAB-001','KAN-LP-999-IDN','2028-12-31','Jl. Teknik Sipil No. 10','Makassar','Sulawesi Selatan','0411-555000','lab@sondir.test','Kepala Laboratorium','Hasil hanya berlaku untuk titik dan kondisi saat pengujian.');
INSERT INTO klien(kode_klien,nama_klien,nama_perusahaan,nama_kontak,whatsapp,email,alamat,kabupaten,provinsi) VALUES
('KL-0001','Dinas Pekerjaan Umum','Pemerintah Kota Makassar','Andi Rahman','081234560001','pu@example.test','Jl. A.P. Pettarani','Makassar','Sulawesi Selatan'),
('KL-0002','Bumi Konstruksi','PT Bumi Konstruksi Utama','Siti Aminah','081234560002','siti@example.test','Jl. Perintis Kemerdekaan','Maros','Sulawesi Selatan');
INSERT INTO alat_sondir(kode_alat,nama_alat,jenis_alat,merek,model,nomor_seri,kapasitas_maksimum,satuan_kapasitas,diameter_piston,diameter_konus,diameter_selimut,panjang_selimut_geser,luas_piston,luas_konus,luas_selimut,faktor_kalibrasi_konus,faktor_kalibrasi_total,interval_standar,nomor_sertifikat,tanggal_kalibrasi,tanggal_kedaluwarsa,lembaga_kalibrasi,kondisi,status) VALUES
('ALT-0001','Sondir Hidrolik 2,5 Ton','Konus ganda mekanis','Geotech','GT-25','SN-25001',250,'kPa/100',5.046,3.568248,3.568248,13.380931,20,10,150,1.02,1.01,.20,'KAL-2026-001','2026-01-15','2027-01-15','Balai Kalibrasi Teknik','Baik','aktif'),
('ALT-0002','Sondir Hidrolik 5 Ton','Konus ganda mekanis','Soiltest','ST-50','SN-50002',500,'kPa/100',5.046,3.568248,3.568248,13.380931,20,10,150,.99,1.00,.20,'KAL-2026-002','2026-06-10','2027-06-10','Balai Kalibrasi Teknik','Baik','aktif');
INSERT INTO proyek(klien_id,kode_proyek,nama_proyek,nama_pekerjaan,pemilik_pekerjaan,alamat_lokasi,kabupaten,provinsi,tanggal_mulai,jumlah_titik_rencana,operator_id,pemeriksa_id,status,created_by) VALUES
(1,'PRJ-2026-0001','Pembangunan Gedung Pelayanan Publik','Penyelidikan Tanah','Dinas Pekerjaan Umum','Kecamatan Panakkukang','Makassar','Sulawesi Selatan','2026-07-01',2,3,4,'berjalan',1),
(2,'PRJ-2026-0002','Kawasan Pergudangan Maros','Pengujian Sondir Area Gudang','PT Bumi Konstruksi Utama','Kecamatan Mandai','Maros','Sulawesi Selatan','2026-07-15',1,3,4,'berjalan',1);
INSERT INTO titik_sondir(proyek_id,alat_id,kode_titik,nama_titik,nomor_urut,tanggal_pengujian,operator_id,pemeriksa_id,interval_kedalaman,kedalaman_rencana,kedalaman_aktual,kondisi_cuaca,status,created_by) VALUES
(1,1,'PRJ-2026-0001-SD-01','Sudut Barat Laut',1,'2026-07-03',3,4,.20,10,2,'Cerah','menunggu_pemeriksaan',1),
(1,1,'PRJ-2026-0001-SD-02','Sudut Tenggara',2,'2026-07-04',3,4,.20,10,NULL,'Berawan','draft',1),
(2,2,'PRJ-2026-0002-SD-01','Tengah Area Gudang',1,'2026-07-18',3,4,.20,12,NULL,'Cerah','draft',1);
INSERT INTO hasil_sondir(titik_sondir_id,nomor,kedalaman,bacaan_konus,bacaan_total,qc,hambatan_total,fs,jhp,friction_ratio,satuan_tekanan,jenis_tanah) VALUES
(1,1,.2,5,10,5.10,10.10,.333,.067,6.53,'kg/cm2','Lempung lunak'),
(1,2,.4,7,13,7.14,13.13,.399,.147,5.59,'kg/cm2','Lempung lunak'),
(1,3,.6,9,16,9.18,16.16,.465,.239,5.07,'kg/cm2','Lempung sedang'),
(1,4,.8,12,20,12.24,20.20,.531,.346,4.34,'kg/cm2','Lempung sedang'),
(1,5,1.0,15,24,15.30,24.24,.596,.465,3.90,'kg/cm2','Lempung sedang'),
(1,6,1.2,19,29,19.38,29.29,.661,.597,3.41,'kg/cm2','Lempung kaku'),
(1,7,1.4,24,35,24.48,35.35,.725,.742,2.96,'kg/cm2','Lempung kaku'),
(1,8,1.6,31,43,31.62,43.43,.787,.900,2.49,'kg/cm2','Lempung sangat kaku'),
(1,9,1.8,40,53,40.80,53.53,.849,1.069,2.08,'kg/cm2','Pasir sedang'),
(1,10,2.0,52,66,53.04,66.66,.908,1.251,1.71,'kg/cm2','Pasir padat'),
(2,1,.2,4,9,4.08,9.09,.334,.067,8.19,'kg/cm2','Lempung sangat lunak'),
(2,2,.4,6,12,6.12,12.12,.400,.147,6.54,'kg/cm2','Lempung lunak'),
(2,3,.6,8,15,8.16,15.15,.466,.240,5.71,'kg/cm2','Lempung lunak'),
(2,4,.8,11,19,11.22,19.19,.531,.346,4.73,'kg/cm2','Lempung sedang'),
(2,5,1.0,14,23,14.28,23.23,.597,.466,4.18,'kg/cm2','Lempung sedang'),
(2,6,1.2,18,28,18.36,28.28,.661,.598,3.60,'kg/cm2','Lempung kaku'),
(2,7,1.4,23,34,23.46,34.34,.725,.743,3.09,'kg/cm2','Lempung kaku'),
(2,8,1.6,30,42,30.60,42.42,.788,.901,2.58,'kg/cm2','Lempung sangat kaku'),
(2,9,1.8,39,52,39.78,52.52,.849,1.071,2.13,'kg/cm2','Pasir sedang'),
(2,10,2.0,50,64,51.00,64.64,.909,1.253,1.78,'kg/cm2','Pasir padat');
INSERT INTO pengaturan_rumus(nama_rumus,faktor_konus,faktor_total,luas_konus,luas_selimut,jumlah_desimal,satuan_default,status) VALUES('Metode Standar Laboratorium',1,1,10,150,2,'kg/cm2','aktif');
INSERT INTO laporan(titik_sondir_id,nomor_laporan,kode_verifikasi,ringkasan_hasil,kesimpulan,status) VALUES(1,'LAP-SONDIR-2026-0001','VRF-2026-SONDIR-0001','Pengujian hingga kedalaman 2 meter.','Tahanan konus meningkat seiring kedalaman.','draft');

-- Selaraskan seluruh hasil contoh dengan rumus SNI 2827:2008.
UPDATE hasil_sondir h
JOIN (
  SELECT h2.id,
    (h2.bacaan_total-h2.bacaan_konus) kw,
    h2.bacaan_konus*(a.luas_piston/a.luas_konus)*a.faktor_kalibrasi_konus qc_sni,
    (h2.bacaan_total-h2.bacaan_konus)*(a.luas_piston/a.luas_selimut)*a.faktor_kalibrasi_total fs_sni,
    SUM((h2.bacaan_total-h2.bacaan_konus)*(a.luas_piston/a.luas_selimut)*a.faktor_kalibrasi_total*(t.interval_kedalaman*100))
      OVER(PARTITION BY h2.titik_sondir_id ORDER BY h2.kedalaman) tf_sni
  FROM hasil_sondir h2
  JOIN titik_sondir t ON t.id=h2.titik_sondir_id
  JOIN alat_sondir a ON a.id=t.alat_id
) x ON x.id=h.id
SET h.hambatan_total=x.kw,h.qc=x.qc_sni,h.fs=x.fs_sni,h.jhp=x.tf_sni,
    h.friction_ratio=IF(x.qc_sni=0,0,(x.fs_sni/x.qc_sni)*100),
    h.satuan_tekanan='kPa/100';
