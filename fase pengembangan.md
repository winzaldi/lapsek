# Fase Pengembangan
## Laporan Online Sekolah — Data Kesehatan UKS

Dokumen ini menerjemahkan [PRD Laporan Online Sekolah](./PRD-Laporan-Online-Sekolah.md) menjadi tahapan kerja yang dapat direncanakan dan diuji. Urutan dibuat berdasarkan dependensi: aturan klinis dan akses harus disepakati sebelum implementasi pemeriksaan, dan fondasi API harus tersedia sebelum web/mobile mengintegrasikannya.

**Platform:** Laravel REST API, Next.js web, Flutter mobile  
**Peran Puskesmas:** Operator Puskesmas menjalankan operasional sesuai cakupan yang diberikan Admin Sistem. Tidak ada peran Admin Puskesmas terpisah.  
**Catatan estimasi:** Durasi dan ukuran tim belum ditetapkan karena volume, jumlah pengembang, dan target rilis belum tersedia.

## Ringkasan fase

| Fase | Nama | Hasil utama | Gerbang untuk lanjut |
|---|---|---|---|
| 0 | Inisiasi dan validasi kebutuhan | Keputusan proses, klinis, privasi, dan MVP disepakati | Pemilik proses menyetujui aturan dan cakupan MVP |
| 1 | Desain produk dan arsitektur | Alur, rancangan UI, model data, kontrak API, dan desain keamanan | Rancangan ditinjau pengguna dan teknis |
| 2 | Fondasi teknis dan lingkungan | Repo/aplikasi, CI, database, deployment nonproduksi, observabilitas | Build dan deployment awal berjalan |
| 3 | Backend inti dan master data | API identitas, peran, sekolah, siswa, kelas, dan periode | Aturan scope dan histori data lulus pengujian |
| 4 | Pemeriksaan dan arsip | Form pemeriksaan, kalkulasi, finalisasi, koreksi, histori, audit | Pengujian indikator dan integritas data lulus |
| 5 | Web Puskesmas dan sekolah | Operasional input, pemantauan, pengelolaan sekolah, dan laporan web | Alur utama end-to-end lulus UAT |
| 6 | Laporan dan ekspor | Rapor, rekap, grafik, dan unduhan berulang | Akurasi rekap, keamanan ekspor, dan privasi lulus |
| 7 | Aplikasi mobile siswa | Login siswa, rapor, dan histori pemeriksaan | Uji akses kepemilikan dan kompatibilitas lulus |
| 8 | Uji menyeluruh dan pilot | UAT, keamanan, performa, pemulihan, pilot terkontrol | Go-live disetujui berdasarkan kriteria rilis |
| 9 | Peluncuran dan operasi | Rollout, dukungan, monitoring, backup, dan perbaikan | Layanan stabil dan proses operasional berjalan |

## Fase 0 — Inisiasi dan validasi kebutuhan

### Langkah
1. Tetapkan pemilik produk, pemilik proses Puskesmas, perwakilan sekolah, tenaga kesehatan/UKS, pengelola data, serta penanggung jawab keamanan dan operasional.
2. Walkthrough proses saat ini dari penyiapan semester hingga laporan diterima siswa/sekolah.
3. Sepakati batas MVP dan kriteria keberhasilan; tentukan mana yang boleh diedit sekolah dan mana yang hanya dapat diisi petugas Puskesmas.
4. Finalisasi makna setiap indikator dari dokumen sumber, termasuk pilihan, satuan, kapan boleh kosong, dan siapa yang mengisi.
5. Tetapkan standar perhitungan status gizi anak, kebutuhan data untuk menghitungnya, metode anemia/tekanan darah, dan test vector yang disahkan tenaga kesehatan.
6. Putuskan alur draf, finalisasi, publikasi, koreksi, dan apakah finalisasi memerlukan pemeriksa kedua.
7. Tetapkan kebijakan akses data, akun siswa, verifikasi identitas, perpindahan sekolah, dasar pemrosesan data anak, retensi, penghapusan, dan respons insiden.
8. Tentukan format laporan peran, kebijakan grafik agregat, volume pengguna/data, SLA, serta kebutuhan offline mobile jika ada.

### Deliverable
- Daftar kebutuhan dan keputusan yang ditandatangani pemilik proses.
- Matriks peran-perizinan dan cakupan sekolah/wilayah.
- Kamus data indikator, matriks wajib/opsional, dan aturan perhitungan klinis.
- Acceptance criteria MVP, risiko, dan daftar keputusan yang belum selesai.

### Kriteria keluar
- Tidak ada keputusan klinis kritis yang masih terbuka untuk indikator yang akan diimplementasikan.
- Pemilik proses menyetujui aturan finalisasi, publikasi, akses sekolah/siswa, dan penanganan koreksi.
- Dasar pemrosesan, pemberitahuan/persetujuan yang diperlukan, retensi, dan pengelolaan insiden memiliki penanggung jawab.

## Fase 1 — Desain produk, UX, data, dan arsitektur

### Langkah
1. Buat flow dan wireframe untuk Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, dan Siswa.
2. Rancang desain responsif untuk web dan pola navigasi/komponen untuk Flutter.
3. Turunkan model konseptual PRD menjadi ERD dan rancangan migrasi, termasuk enrollment historis, snapshot sekolah/kelas saat pemeriksaan, dan versi data final.
4. Susun matriks akses hingga tingkat resource dan field sensitif; tentukan larangan lintas sekolah/siswa.
5. Definisikan kontrak API versioned: request/response, pagination, filter, error, idempotensi, dan autentikasi.
6. Rancang alur ekspor private, audit log, retensi, backup, pemantauan, dan pemulihan.
7. Rancang strategi migrasi/import data sekolah dan siswa serta penanganan duplikasi.

### Deliverable
- Wireframe dan prototipe alur utama.
- ERD, kamus data, status pemeriksaan, dan kebijakan integritas.
- API specification (misalnya OpenAPI) dan matriks otorisasi.
- Rancangan deployment, threat model, backup/restore, dan strategi pengujian.

### Kriteria keluar
- Alur dapat ditelusuri dari kebutuhan PRD hingga rancangan UI/API/data.
- Rancangan API dan model data telah ditinjau backend, frontend, mobile, QA, dan keamanan.

## Fase 2 — Fondasi teknis dan lingkungan

### Langkah
1. Siapkan repository dan struktur aplikasi Laravel API, Next.js, dan Flutter sesuai strategi tim.
2. Siapkan konfigurasi dev/test/staging/production tanpa menyimpan secret pada source control.
3. Siapkan database, migration baseline, penyimpanan file privat, antrean pekerjaan, dan cache bila diperlukan.
4. Terapkan format kode, lint, test runner, dependency scanning, dan pipeline CI untuk build/test otomatis.
5. Buat kerangka autentikasi, API versioning, respons error konsisten, logging terstruktur, dan correlation/request ID.
6. Siapkan deployment staging, konfigurasi HTTPS, security headers, backup awal, dan monitoring dasar.

### Deliverable
- Aplikasi skeleton yang dapat dibangun dan dideploy ke staging.
- CI menjalankan lint dan tes dasar pada setiap perubahan.
- Panduan setup lokal dan pengelolaan konfigurasi/secret.

### Kriteria keluar
- Deployment staging dapat diulang tanpa langkah manual yang tidak terdokumentasi.
- Tidak ada secret atau data kesehatan nyata pada lingkungan development/test.

## Fase 3 — Backend inti dan master data

### Langkah
1. Implementasikan login/logout, pengelolaan sesi/token, reset kredensial, status akun, dan throttling.
2. Implementasikan role dan permission: Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, dan Siswa.
3. Terapkan scope Puskesmas/sekolah pada policy/service dan query backend; gunakan deny-by-default.
4. Implementasikan master Puskesmas, sekolah, tahun ajaran, semester, kelas, siswa, guru/operator, dan penugasan.
5. Implementasikan enrollment per tahun ajaran agar perpindahan kelas/sekolah tidak menimpa histori.
6. Implementasikan verifikasi tautan akun siswa dengan profil siswa.
7. Implementasikan import siswa dengan preview, validasi, error per baris, deduplikasi, dan hasil yang dapat diunduh.
8. Tambahkan audit untuk perubahan master dan hak akses.

### Deliverable
- API master data dan dokumentasi.
- Antarmuka/API penugasan akun dengan pengelolaan terpusat oleh Admin Sistem.
- Data contoh sintetis dan skrip seed nonproduksi.

### Kriteria keluar
- Uji akses membuktikan Operator Puskesmas hanya mengakses cakupannya, sekolah hanya sekolahnya, dan siswa hanya profil tertaut.
- Histori enrollment tetap benar setelah kenaikan kelas dan perpindahan sekolah.
- Import tidak membuat data parsial/duplikat tanpa laporan yang jelas.

## Fase 4 — Pemeriksaan kesehatan dan arsip

### Langkah
1. Implementasikan pembuatan/pembaruan draf pemeriksaan untuk setiap siswa-semester.
2. Implementasikan semua kelompok indikator sesuai kamus data yang disahkan: umum, gaya hidup, gizi, mata, telinga, dan gigi/mulut.
3. Pastikan belum diisi, tidak diperiksa, dan nilai “tidak” merupakan keadaan yang berbeda.
4. Terapkan validasi nilai, satuan, tanggal, periode, pemeriksa, enrollment, dan larangan duplikasi.
5. Implementasikan kalkulasi IMT dan status gizi sesuai algoritma anak yang disepakati; simpan versi kalkulasi serta hasil uji referensi.
6. Implementasikan workflow draf → finalisasi → publikasi dan alur koreksi dengan alasan, versi lama/baru, serta aktor.
7. Implementasikan histori pemeriksaan siswa dan daftar sudah/belum diperiksa untuk Operator Puskesmas.
8. Auditkan pembuatan, perubahan, finalisasi, publikasi, koreksi, dan akses sensitif dengan metadata minimal.

### Deliverable
- API pemeriksaan, histori, finalisasi, publikasi, dan koreksi.
- Validasi backend sebagai sumber kebenaran untuk web dan mobile.
- Test suite untuk aturan indikator, perhitungan, integritas, dan audit.

### Kriteria keluar
- Pemeriksaan final memiliki siswa, sekolah, periode, tanggal, petugas pemeriksa, dan aktor pencatat.
- Duplikasi siswa-semester dicegah sesuai aturan bisnis.
- Finalisasi yang tidak memenuhi matriks kelengkapan ditolak dengan daftar kesalahan.
- Koreksi tidak menghapus jejak versi sebelumnya.
- Hasil kalkulasi cocok dengan test vector yang disetujui.

## Fase 5 — Web operasional Puskesmas dan sekolah

### Langkah
1. Bangun login dan layout web responsif berdasarkan role dan scope.
2. Bangun beranda Puskesmas dengan periode, sekolah binaan, cakupan, serta daftar belum diperiksa.
3. Bangun alur Operator Puskesmas: pilih sekolah/periode/siswa, isi draf, tinjau, finalisasi, publikasi, dan koreksi.
4. Bangun alur Operator Sekolah/Guru: profil sekolah, kelas, siswa, enrollment, dan pemantauan status pemeriksaan.
5. Bangun pencarian/filter/paginasi serta pesan error dan status kosong yang mudah dipahami.
6. Pastikan sekolah tidak dapat mengubah hasil pemeriksaan klinis dan tidak melihat data sekolah lain.
7. Uji usability dengan petugas Puskesmas dan perwakilan sekolah menggunakan data sintetis.

### Deliverable
- Web MVP untuk administrasi yang diizinkan, pemeriksaan, dan pemantauan.
- Dokumentasi panduan singkat untuk Operator Puskesmas dan sekolah.
- Catatan UAT dan daftar perbaikan.

### Kriteria keluar
- Pengguna dapat menyelesaikan skenario persiapan, pemeriksaan, finalisasi, dan koreksi tanpa intervensi database.
- Hak akses diuji pada antarmuka dan API; kontrol backend tidak bergantung pada penyembunyian menu.
- Temuan UAT yang memblokir operasional telah diselesaikan.

## Fase 6 — Rapor, rekap, grafik, dan ekspor

### Langkah
1. Implementasikan rapor individu dengan histori per semester/tahun ajaran dan status pemeriksaan yang jelas.
2. Implementasikan rekap sekolah/Puskesmas: sasaran, diperiksa, belum diperiksa, dan cakupan.
3. Tambahkan grafik agregat dengan ambang privasi untuk kelompok kecil sesuai kebijakan.
4. Sediakan filter sekolah, kelas, jenjang, periode, dan status sesuai hak akses.
5. Implementasikan PDF/CSV/XLSX; sanitasi formula spreadsheet, kontrol ukuran, dan antrean untuk ekspor besar.
6. Simpan ekspor pada storage privat, cek otorisasi saat unduh, terapkan expiry, dan catat peminta/waktu/scope.
7. Cocokkan angka laporan dengan query data final dan uji hasil ekspor yang diunduh berulang.

### Deliverable
- Laporan siswa, rekap, grafik dasar, dan ekspor.
- Riwayat permintaan/unduhan yang dapat diaudit.
- Test data dan rekonsiliasi akurasi laporan.

### Kriteria keluar
- Rekap semester dan tahunan konsisten dengan pemeriksaan final yang termasuk filter.
- Ekspor hanya tersedia bagi pengguna yang berhak dan tidak dapat diakses melalui URL publik/tebakan ID.
- Ekspor ulang menghasilkan hasil konsisten untuk kumpulan data dan waktu snapshot yang sama.
- Grafik tidak membocorkan kelompok kecil sesuai ambang yang disepakati.

## Fase 7 — Aplikasi mobile siswa

### Langkah
1. Implementasikan autentikasi dan pemulihan sesi yang aman di Flutter.
2. Implementasikan tautan akun ke profil siswa yang terverifikasi; jangan menggunakan nama sebagai satu-satunya faktor pengaitan.
3. Bangun tampilan rapor dan histori yang hanya menampilkan data siswa yang sedang login dan telah dipublikasikan.
4. Tambahkan pemilihan semester/tahun ajaran, status data, penanganan loading/error, dan aksesibilitas.
5. Hindari menyimpan data sensitif secara lokal tanpa kebutuhan; jika caching diperlukan, tetapkan enkripsi dan logout-clear.
6. Uji pada perangkat/versi OS yang disepakati, termasuk sesi kedaluwarsa dan pencabutan akses.

### Deliverable
- Flutter MVP untuk rapor dan histori siswa.
- Paket distribusi internal/test dan panduan rilis aplikasi.

### Kriteria keluar
- Siswa tidak dapat mengakses rapor siswa lain dengan mengganti parameter/API ID.
- Data yang belum dipublikasikan tidak tampil.
- Logout, token expiry, akun nonaktif, dan kondisi jaringan gagal ditangani dengan aman.

## Fase 8 — Uji menyeluruh dan pilot terbatas

### Langkah
1. Jalankan pengujian unit, integrasi, API contract, end-to-end, regresi, dan aksesibilitas.
2. Jalankan uji keamanan: IDOR, privilege escalation, brute force, input validation, ekspor, file access, dan log leakage.
3. Jalankan uji performa pada beban yang disepakati, terutama pencarian, rekap, dan ekspor.
4. Uji backup/restore dan prosedur pemulihan; verifikasi integritas histori dan audit.
5. Lakukan UAT dengan data sintetis dan skenario nyata yang telah dianonimkan/diotorisasi.
6. Pilot pada satu wilayah Puskesmas dan sejumlah sekolah yang disetujui; sediakan pelatihan, kanal bantuan, serta mekanisme pelaporan masalah.
7. Pantau akurasi input, kesalahan penggunaan, cakupan, performa, dan temuan privasi selama pilot.
8. Putuskan go/no-go berdasarkan kriteria penerimaan PRD, temuan keamanan, dan persetujuan pemilik proses.

### Deliverable
- Hasil QA/UAT/security/performance testing.
- Berita acara pilot, daftar isu prioritas, dan rencana remediasi.
- Persetujuan rilis dan rencana rollback.

### Kriteria keluar
- Tidak ada temuan keamanan kritis/tinggi yang belum ditangani atau secara formal diterima risikonya.
- Kasus akses siswa/sekolah lintas cakupan, pemeriksaan duplikat, finalisasi, koreksi, dan ekspor lulus.
- Prosedur dukungan, insiden, backup/restore, serta rollback telah diuji atau disimulasikan.

## Fase 9 — Peluncuran, operasi, dan iterasi

### Langkah
1. Persiapkan data referensi produksi dan migrasi/import awal melalui validasi serta persetujuan pemilik data.
2. Buat akun dan penugasan berdasarkan prinsip least privilege; Operator Puskesmas ditugaskan Admin Sistem ke cakupan yang tepat.
3. Jalankan peluncuran bertahap per wilayah/sekolah; pantau error, kinerja, dan beban layanan.
4. Sediakan pelatihan, dokumentasi, support desk, prosedur pergantian operator, dan onboarding/offboarding.
5. Jadwalkan backup, uji pemulihan berkala, patch dependency, review akses, dan audit log.
6. Evaluasi metrik produk dan umpan balik pengguna; prioritaskan perbaikan berdasarkan risiko, dampak, dan frekuensi.
7. Rencanakan fase lanjutan seperti odontogram terperinci, akses orang tua, integrasi eksternal, atau dukungan offline hanya setelah kebutuhan dan risikonya disepakati.

### Deliverable
- Sistem produksi dan rencana operasi.
- Dashboard monitoring, runbook, dokumentasi pengguna/admin, serta kalender backup/review.
- Backlog iterasi yang ditinjau pemilik produk.

### Kriteria operasi
- Insiden dapat dilaporkan, ditangani, dan ditelusuri.
- Pergantian Operator Puskesmas/sekolah tidak menghilangkan arsip dan tidak meninggalkan akses lama aktif.
- Target ketersediaan, backup, pemulihan, dan waktu respons dipantau terhadap SLA yang disetujui.

## Alur kerja paralel lintas fase

Beberapa pekerjaan berjalan lintas fase, tetapi harus mengikuti gerbang yang sesuai:

- **Keamanan dan privasi:** threat model sejak Fase 1, kontrol teknis sejak Fase 2, verifikasi setiap fase, dan persetujuan sebelum pilot.
- **Kualitas data/klinis:** validasi indikator pada Fase 0, kontrak data Fase 1, test vector Fase 4, validasi hasil dalam pilot.
- **Dokumentasi dan pelatihan:** dokumentasikan API saat dibuat; panduan peran disiapkan sebelum UAT dan diperbarui setelah pilot.
- **Auditabilitas:** desain sejak awal; jangan menunda log sampai sistem akan dirilis karena keputusan data perlu tercatat dari operasi pertama.
- **Migrasi dan impor:** uji dengan data sintetis sejak Fase 3; migrasi data nyata hanya setelah dasar pemrosesan, persetujuan pemilik data, dan prosedur validasi ditetapkan.

## Jalur kritis dan dependensi

1. Keputusan standar status gizi dan indikator → model/form pemeriksaan → implementasi perhitungan → test vector klinis.
2. Matriks akses dan penugasan → autentikasi/otorisasi API → UI web/mobile → uji isolasi data.
3. Enrollment dan periode akademik → pemeriksaan per siswa-semester → histori → laporan tahunan.
4. Finalisasi/publikasi/koreksi → ketersediaan laporan → akses rapor siswa.
5. Kebijakan ekspor/retensi → implementasi storage privat → audit dan uji keamanan → penggunaan produksi.

Jika keputusan pada langkah awal belum selesai, pekerjaan yang bergantung padanya sebaiknya tetap berada pada prototipe/data sintetis dan tidak mengambil asumsi klinis atau akses yang belum disetujui.

## Daftar rilis MVP

- [ ] Keputusan klinis, akses, privasi, retensi, dan publikasi disetujui.
- [ ] Master Puskesmas, sekolah, kelas, siswa, enrollment, tahun ajaran, dan semester tersedia.
- [ ] Akun dan scope untuk Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, dan Siswa diuji.
- [ ] Form pemeriksaan memuat semua indikator sumber dan membedakan nilai kosong dari “Tidak”.
- [ ] Pemeriksaan, kalkulasi, finalisasi, publikasi, koreksi, histori, dan audit berfungsi.
- [ ] Rapor, rekap, grafik dasar, dan ekspor dapat digunakan berulang dengan akses yang benar.
- [ ] Flutter siswa hanya menampilkan rapor yang terverifikasi dan dipublikasikan.
- [ ] QA, keamanan, performa, backup/restore, UAT, dan pilot memenuhi gerbang rilis.
- [ ] Pelatihan, dukungan, monitoring, rollback, dan runbook siap.
