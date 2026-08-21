# Sistem Informasi Pengujian Sondir

Aplikasi PHP native untuk pengelolaan laboratorium, klien, proyek, alat, titik pengujian, input dan perhitungan sondir, grafik, pemeriksaan, laporan PDF, audit, dan backup.

## Menjalankan di Laragon

1. Pastikan folder proyek berada di `C:\laragon\www\Sondir`.
2. Jalankan Laragon, lalu klik **Start All**.
3. Buka Terminal Laragon dan impor `database/sondir.sql`, kemudian `database/sample-data.sql` melalui HeidiSQL atau:
   `mysql -u root < database/sondir.sql`
   `mysql -u root db_sondir < database/sample-data.sql`
4. Jalankan `php database/upgrade-professional.php`, lalu `php database/recalculate-canonical.php`. Migration bersifat additive dan tidak melakukan `DROP` tabel/data.
5. Dependensi telah disiapkan dengan Composer. Jika folder `vendor` belum ada, jalankan `composer install`.
6. Klik **Menu > www > Sondir**, atau buka `http://sondir.test`. Fallback: `http://localhost/Sondir`.
7. Login awal memakai username `admin` dan password `admin123`, lalu segera ganti password.

## Konfigurasi database

Nilai default adalah host `127.0.0.1`, database `db_sondir`, user `root`, dan password kosong. Untuk server lain, gunakan environment variable `SONDIR_DB_HOST`, `SONDIR_DB_PORT`, `SONDIR_DB_NAME`, `SONDIR_DB_USER`, dan `SONDIR_DB_PASS`.

Untuk shared hosting, salin `config/database.credentials.example.php` menjadi `config/database.credentials.php`, lalu isi host, database, username, dan password produksi. File kredensial tersebut diabaikan Git sehingga tidak ikut repository dan tidak tertimpa oleh deployment berikutnya. Environment variable tetap memiliki prioritas lebih tinggi daripada file kredensial.

## Upgrade profesional dan traceability

- Hasil audit dan gap analysis: `docs/AUDIT-UPGRADE-SONDIR.md`.
- Master metode dan referensi: menu **Metode & Referensi**.
- Perhitungan mechanical sondir: `includes/MechanicalSondirCalculator.php`.
- Konversi canonical kPa/MPa: `includes/UnitConversionService.php`.
- Validasi hijau/kuning/merah: `includes/SondirValidationService.php`.
- Report readiness 0-100%: `includes/ReportReadinessService.php`.
- Uji manual: `php tests/mechanical_calculation_test.php` dan `php tests/soil_classification_test.php`.

## Rumus SNI 2827:2008

- `Kw = Tw - Cw`
- `qc = Cw × (Api / Ac) × faktor kalibrasi konus`
- `fs = Kw × (Api / As) × faktor kalibrasi total`
- `Tf = Tf sebelumnya + (fs × interval pembacaan dalam cm)`
- `Rf = (fs / qc) × 100`, atau nol jika qc nol

Faktor rumus dapat dikelola pada menu **Rumus & Pengaturan**.

Jenis/perilaku tanah diperkirakan dengan diagram Robertson SBT 12 zona yang sama dengan modul `SOIL QC`. Nilai native alat dipertahankan, sementara `qc_kpa`, `qc_mpa`, `fs_kpa`, dan `fs_mpa` disimpan sebagai nilai canonical; FR tetap dalam persen. Jalankan `php database/recalculate-canonical.php` untuk menghitung ulang canonical unit dan SBT tanpa mengubah raw Cw/Tw.

Setiap hasil pengujian menyimpan `qc_mpa`, `zona_sbt`, penanda batas zona, dan versi klasifikasi. Ringkasan zona tampil pada menu Pengujian, detail zona tampil per kedalaman, dan kolom Zona SBT ikut dicetak pada tabel laporan.

Template Excel dapat diunduh dari halaman input pengujian. Impor membaca kolom `Kedalaman`, `Cw`, dan `Tw`; qc, fs, Rf, Tf, serta SBT dihitung oleh service yang sama dengan API input. Data baru disimpan setelah preview/validasi dan tombol konfirmasi; error fatal memblokir commit.

Pada menu **Titik Sondir**, field **Jumlah sondir** dapat membuat beberapa titik sekaligus. Setiap titik tampil sebagai tab pada halaman input pengujian dan memiliki kepala data, koordinat manual, lokasi perangkat, serta pemilihan koordinat melalui peta. Tabel input mendukung paste beberapa baris langsung dari Excel dengan urutan `Kedalaman`, `Cw`, `Tw`, `Jenis Tanah`, dan `Catatan`.

## Keamanan dan deployment

Semua proses tulis memakai prepared statement, CSRF, session timeout, pembatasan login, dan audit log. Folder uploads memblokir eksekusi PHP. Di shared hosting, arahkan document root ke folder aplikasi, impor database, jalankan `composer install --no-dev --optimize-autoloader`, atur kredensial database, dan pastikan `uploads` serta `storage/backups` dapat ditulis oleh PHP.

### Lupa password

- Tautan **Lupa password?** tersedia pada halaman login untuk akun internal.
- Di Laragon (`sondir.test`, `localhost`, atau `127.0.0.1`), tautan reset ditampilkan langsung agar pengujian tidak bergantung pada layanan email.
- Di server produksi, tautan hanya dikirim ke email akun dan berlaku selama 30 menit. Pastikan setiap akun, terutama Super Admin, memakai alamat email yang benar dan dapat menerima pesan.
- Pengiriman produksi menggunakan fungsi `mail()` PHP. Atur alamat pengirim melalui environment variable `SONDIR_MAIL_FROM`, misalnya `noreply@soil.labsipil.web.id`, dan aktifkan layanan email pada hosting.
- Tabel `password_reset_tokens` sudah ada pada SQL instalasi. Pada database lama, tabel juga dibuat otomatis saat halaman lupa password pertama kali dibuka, selama user database memiliki izin `CREATE TABLE`.
- Demi keamanan, respons permintaan tidak mengungkap apakah username/email terdaftar, permintaan dibatasi, token disimpan dalam bentuk hash, dan setiap token hanya dapat digunakan satu kali.

Untuk membuat PDF, buka menu **Laporan**, lalu pilih **PDF**. Untuk backup SQL, Super Admin membuka menu **Backup**. Restore sengaja dilakukan manual melalui alat administrasi database agar tidak dapat dipicu tanpa verifikasi.
