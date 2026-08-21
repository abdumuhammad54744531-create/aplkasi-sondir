# Audit Upgrade Modul Sondir Profesional

Tanggal audit: 21 Agustus 2026  
Basis aplikasi: PHP native 8.2+, MySQL, Bootstrap, JavaScript, Leaflet, SVG, Dompdf, PhpSpreadsheet.

## FASE 1 - Kondisi aplikasi saat ini

| Area | Temuan nyata |
|---|---|
| Arsitektur | PHP native modular. Halaman/endpoint dipisah per folder, tetapi belum mempunyai domain service yang konsisten. |
| Database | 17 tabel aktif. Relasi utama memakai `proyek.id -> titik_sondir.proyek_id -> hasil_sondir.titik_sondir_id`. Data lama tetap utuh. |
| Proyek | Identitas dasar, pihak proyek, alamat, koordinat, tanggal, PIC, dokumen, dan status sudah tersedia. Field kontrak, bangunan, UTM/elevasi proyek, tujuan, serta ruang lingkup belum lengkap. |
| Titik sondir | Multi-titik, koordinat, elevasi, waktu, alat, operator, interval, kedalaman, MAT, kondisi lapangan, alasan penghentian, dan workflow dasar sudah tersedia. Struktur catatan penghentian belum terpisah. |
| Alat | Geometri piston/konus/sleeve, faktor kalibrasi, kapasitas, interval, sertifikat, masa berlaku, dan status tersedia. Pemisahan mechanical/electronic dan konus tunggal/bikonus belum eksplisit. |
| Data lapangan | Tabel Depth/Cw/Tw dengan tambah baris, paste Excel, import Excel, kalkulasi langsung, simpan draft, dan kirim pemeriksaan tersedia. Autosave server berkala dan pemetaan kolom import belum tersedia. |
| Kalkulasi | qc, Kw, fs, Tf/JHP, dan Rf dihitung dari geometri alat. Formula masih terduplikasi di JavaScript, API, dan import; belum memakai service canonical/unit conversion. |
| Validasi | Duplikasi kedalaman, Tw<Cw, nilai negatif, kapasitas alat, serta penguncian setelah disetujui sudah ada. Pemeriksaan interval, lonjakan, kedalaman akhir, kalibrasi, dan readiness belum lengkap. |
| Interpretasi | Robertson SBT 12 zona, label batas zona, konsistensi/kepadatan, profil hatch, dan tabel interval otomatis sudah tersedia. `klasifikasi_tanah` tersedia untuk interval manual tetapi belum memiliki workflow review penuh. |
| Grafik | Grafik SVG qc, fs, Rf, Tf, grafik gabungan, serta Diagram Robertson SBT 12 zona tersedia dan tajam di PDF. Lembar log enam track, overlay antar titik, elevasi absolut, dan cross section belum tersedia. |
| Fondasi | Halaman fondasi dangkal/tiang dan tabel Meyerhof/Schmertmann tersedia. Formula masih berada pada halaman/report, identitas metode belum menjadi master selectable, dan Qp/Qs/Qu/Qall belum tersimpan sebagai analisis terpisah. |
| Laporan | Cover, ringkasan, TOC statis, bab metodologi, peta satelit, data mentah, grafik, SBT, profil, fondasi, kesimpulan, pengesahan QR, dan dokumentasi tersedia. Daftar tabel/gambar otomatis, revision control, kesiapan laporan, dan lampiran kondisional lengkap belum tersedia. |
| Workflow | Draft -> menunggu pemeriksaan -> revisi/disetujui -> diterbitkan tersedia per titik. Belum ada tahapan calculated/interpreted/reviewed/approved/final pada entitas laporan proyek dan revisi formal. |
| Audit | `audit_log` menyimpan user, aksi, data lama/baru, IP, user-agent, waktu. Audit raw data saat ini menyimpan ringkasan, bukan snapshot setiap baris. |
| Keamanan data | Data disetujui/diterbitkan dikunci. Penggantian raw data dilakukan atomik dalam transaksi. Belum ada revisi setelah final. |

## FASE 2 - Gap analysis

### SUDAH ADA

- Multi proyek dan multi titik dengan relasi yang benar.
- Master alat dan kalibrasi, peringatan kapasitas, status alat.
- Input keyboard-friendly, paste Excel, import template, dan transaksi penyimpanan.
- Perhitungan mechanical sondir dasar berdasarkan geometri alat.
- Penyimpanan raw reading terpisah dari tabel interval interpretasi.
- Grafik vektor, profil tanah, peta satelit, Diagram Robertson SBT 12 zona.
- Pemeriksaan/pengesahan dasar, QR verifikasi, dokumentasi, audit trail.
- PDF profesional 36 halaman untuk dataset aktif.

### PERLU DIPERBAIKI

- Formula harus dipusatkan pada `MechanicalSondirCalculator`; JavaScript hanya pratinjau.
- Konversi unit harus melalui `UnitConversionService` dengan satuan canonical MPa/kPa.
- Validasi perlu severity hijau/kuning/merah dan justifikasi untuk error yang dapat dikecualikan.
- Import Excel perlu preview, mapping, validasi, baru commit.
- Autosave perlu server draft berkala dan cache lokal pemulihan.
- Master metode/referensi harus mengikat semua korelasi dan analisis fondasi.
- Penentuan lapisan pendukung harus configurable, bukan threshold universal tersembunyi.
- Laporan perlu menghilangkan baris kosong, menyusun daftar tabel/gambar, dan hanya menampilkan analisis yang benar-benar dihitung.
- Laporan dan approval perlu project-scoped, revisioned, dan immutable setelah FINAL.

### BELUM ADA

- Editor tujuan, checklist ruang lingkup, dan master referensi administrator.
- Parameter tanah hasil korelasi dengan value/unit/method/reference/confidence.
- Perbandingan antar titik pada kedalaman lokal dan elevasi absolut.
- Penampang interpretatif dengan disclaimer.
- Analisis fondasi tersimpan dengan input, metode, Qp/Qs/Qu/Qall, substitusi, dan hasil.
- Report readiness 0-100%, workflow lengkap, revision control, serta hash PDF final.
- Export PDF ringkas/lampiran, Excel perhitungan, CSV, dan print khusus grafik.
- Daftar isi/daftar tabel/daftar gambar yang benar-benar dinamis.

## Risiko teknis yang harus dikendalikan

1. Formula saat ini terduplikasi; perbedaan kecil antara browser, API, import, dan PDF dapat menghasilkan angka berbeda.
2. Kolom `satuan_tekanan` memakai label alat dan belum menjamin canonical unit.
3. `DELETE + INSERT` raw reading aman karena transaksi, tetapi audit belum menyimpan revisi detail setiap reading.
4. Analisis fondasi pada PDF saat ini bersifat korelasi awal dan tidak boleh dianggap desain final tanpa beban, penurunan, metode, serta asumsi engineer.
5. SBT adalah jenis perilaku tanah berdasarkan korelasi, bukan identifikasi tekstur laboratorium.

## Urutan implementasi aman

1. Migration additive dan master referensi.
2. Service unit, kalkulasi, validasi, serta unit test dataset manual.
3. Refactor API/import memakai service yang sama.
4. Autosave dan QA/QC raw data.
5. Interpretasi reviewable dan parameter korelasi.
6. Analisis fondasi tersimpan/transparan.
7. Comparison/cross-section.
8. Workflow laporan, revisions, approvals, verification hash.
9. Generator PDF dinamis dan export.

