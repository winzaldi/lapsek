# Desain Fase 0
## Inisiasi dan Validasi Kebutuhan — Laporan Online Sekolah

**Status:** Dokumen kerja; keputusan yang belum ditandatangani bukan persetujuan.  
**Acuan:** [PRD Laporan Online Sekolah](./PRD-Laporan-Online-Sekolah.md), [Fase Pengembangan](./fase%20pengembangan.md), dan formulir indikator UKS yang diberikan.  
**Tujuan:** Menyelesaikan kebutuhan proses, klinis, akses, privasi, dan operasional sebelum desain Fase 1 dibekukan.

> Fase ini tidak menetapkan diagnosis, ambang klinis, dasar hukum, atau persetujuan pemrosesan data secara sepihak. Keputusan tersebut harus ditetapkan oleh pemilik proses dan pihak berwenang yang sesuai.

## 1. Hasil yang dituju

Pada akhir Fase 0, tim memiliki keputusan yang dapat ditindaklanjuti tentang:

- proses pemeriksaan semester dari penyiapan sasaran sampai rapor diterima;
- cakupan MVP dan batas kemampuan setiap peran;
- definisi indikator, satuan, keadaan kosong, dan aturan perhitungan;
- draf, finalisasi, publikasi, koreksi, dan pemeriksaan ulang;
- hak akses sekolah/Puskesmas/siswa serta verifikasi akun siswa;
- tata kelola data kesehatan siswa, retensi, ekspor, dan respons insiden;
- kebutuhan laporan, volume, SLA, dan kebutuhan mobile;
- kriteria penerimaan, risiko, keputusan terbuka, serta persetujuan pemangku kepentingan.

## 2. Pemangku kepentingan dan tanggung jawab keputusan

Peran berikut adalah fungsi yang perlu diwakili, bukan asumsi nama organisasi atau jabatan formal. Satu orang dapat mewakili beberapa fungsi apabila disetujui.

| Fungsi | Tanggung jawab dalam Fase 0 |
|---|---|
| Pemilik produk/layanan | Menentukan prioritas, cakupan MVP, metrik, dan menyetujui kebutuhan produk. |
| Pemilik proses UKS/Puskesmas | Menjelaskan proses pemeriksaan, petugas yang berwenang, arsip, dan alur koreksi. |
| Tenaga kesehatan/penanggung jawab klinis | Mengesahkan definisi indikator, satuan, algoritma status gizi, dan test vector klinis. |
| Perwakilan Operator Puskesmas | Memvalidasi kebutuhan pencatatan, daftar sasaran, pemeriksaan, dan laporan operasional. |
| Perwakilan sekolah/guru/operator | Memvalidasi pengelolaan siswa/kelas, batas akses, laporan sekolah, dan penggunaan harian. |
| Perwakilan siswa atau pendamping yang sesuai | Meninjau keterpahaman rapor dan pengalaman akses sesuai usia; tidak menggunakan data siswa nyata untuk demo tanpa otorisasi. |
| Pemilik data/pelindungan data | Menentukan tata kelola, dasar pemrosesan, pemberitahuan/persetujuan yang diperlukan, retensi, hak subjek data, dan pembagian tanggung jawab. |
| Admin Sistem/pengelola TI | Memvalidasi pengelolaan akun, penugasan, konfigurasi, lingkungan, dan proses dukungan. |
| Penanggung jawab keamanan/operasional | Menentukan kontrol minimum, penanganan insiden, backup/restore, RPO/RTO, dan kesiapan layanan. |
| QA/perwakilan teknis | Memastikan kebutuhan dapat diuji dan tidak bertentangan dengan batasan platform/API. |

### Catatan tata kelola

- Tidak ada peran **Admin Puskesmas** terpisah. Operasional Puskesmas dilakukan oleh **Operator Puskesmas** sesuai scope yang diberikan Admin Sistem.
- Admin Sistem mengelola akun, role, penugasan, dan konfigurasi global; aksesnya terhadap isi kesehatan harus diminimalkan dan diaudit.
- Keputusan klinis disahkan pemilik proses/tenaga kesehatan; keputusan pelindungan data ditinjau pemilik data/pihak berwenang.

## 3. Agenda lokakarya validasi

Sesi dapat dibagi menjadi beberapa pertemuan. Semua keputusan dan perbedaan pendapat dicatat pada register keputusan.

### Sesi A — Alur kerja dan cakupan MVP

1. Petakan proses saat ini: penyiapan periode, daftar sasaran, pemeriksaan, pencatatan, finalisasi, penyampaian laporan, dan pengarsipan.
2. Identifikasi aktor pada setiap langkah: siapa melakukan pemeriksaan, siapa memasukkan data, siapa meninjau, dan siapa dapat mengoreksi.
3. Sepakati skenario siswa naik kelas, pindah sekolah, belum memiliki NISN, tidak hadir, atau diperiksa di luar tanggal jadwal.
4. Putuskan kemampuan sekolah untuk menambah/memperbarui identitas dan enrollment, tanpa mengubah hasil klinis.
5. Tetapkan fungsi yang masuk MVP dan yang ditunda, khususnya mobile petugas, offline, odontogram, akses wali, dan integrasi eksternal.

### Sesi B — Indikator dan aturan pemeriksaan

1. Telaah setiap indikator pada bagian 5 dokumen ini.
2. Sepakati nama tampilan, definisi, nilai pilihan, unit, sumber nilai, dan pihak yang mengisi.
3. Tentukan perbedaan `belum diisi`, `tidak diperiksa`, `tidak berlaku` (jika diperlukan), dan jawaban negatif.
4. Tetapkan matriks wajib/opsional saat draf dan syarat minimum finalisasi.
5. Sahkan algoritma status gizi anak, masukan yang dibutuhkan, pembulatan, versi algoritma, dan test vector.
6. Putuskan alur ukur ulang, pemeriksaan duplikat, dan koreksi.

### Sesi C — Akses, privasi, dan keselamatan data

1. Tinjau matriks role/scope awal pada bagian 6.
2. Tentukan informasi yang dapat dilihat sekolah, siswa, Operator Puskesmas, dan Admin Sistem.
3. Tentukan proses verifikasi akun siswa dan pemulihan akses.
4. Tentukan dasar pemrosesan data anak, mekanisme pemberitahuan/persetujuan bila diwajibkan, retensi, koreksi, penghapusan, dan permintaan akses.
5. Tinjau ekspor, grafik kelompok kecil, audit log, backup, lokasi penyimpanan, dan prosedur insiden.

### Sesi D — Laporan, operasi, dan kesiapan

1. Tinjau rancangan rapor individual dan rekap sekolah/Puskesmas.
2. Tentukan format ekspor, filter, pihak yang boleh mengunduh detail, serta masa berlaku file.
3. Perkirakan jumlah sekolah, siswa, pengguna, puncak akses, dan ukuran ekspor.
4. Tetapkan SLA, RPO/RTO, kanal dukungan, pelatihan, dan proses pergantian operator.
5. Sepakati metrik pilot dan kriteria go/no-go.

## 4. Daftar alur yang harus divalidasi

Untuk setiap alur, catat variasi, pengecualian, aktor, data yang diperlukan, dan keputusan kebijakan.

| Alur | Langkah yang perlu dikonfirmasi | Pertanyaan validasi |
|---|---|---|
| Persiapan tahun ajaran | Admin Sistem mengaktifkan master/periode; sekolah menyiapkan kelas dan siswa; Puskesmas memeriksa cakupan | Siapa membuat/mengoreksi data sekolah dan penugasan? Bagaimana sekolah baru/berganti binaan? |
| Penempatan siswa | Siswa ditempatkan ke kelas dan tahun ajaran; enrollment lama tetap tersimpan | Siapa mengesahkan pindah sekolah/kelas? Bagaimana menghindari profil ganda? |
| Daftar sasaran pemeriksaan | Operator Puskesmas memilih sekolah, periode, kelas, dan siswa | Apakah sasaran berasal dari enrollment aktif? Bagaimana siswa pindahan, tidak hadir, atau pemeriksaan ulang? |
| Pemeriksaan | Petugas memeriksa; operator bisa memasukkan hasil jika berbeda orang | Siapa yang harus tercatat sebagai pemeriksa dan pencatat? Apakah satu sesi untuk satu siswa atau beberapa siswa? |
| Penyimpanan dan finalisasi | Draf disimpan; kelengkapan diperiksa; hasil difinalisasi | Siapa boleh memfinalisasi? Apakah perlu pemeriksa kedua? Apa yang wajib? |
| Publikasi rapor | Hasil yang disetujui ditampilkan kepada sekolah/siswa | Apakah publikasi langsung setelah finalisasi atau membutuhkan persetujuan? |
| Koreksi | Kesalahan pada data final dilaporkan, ditinjau, dan diperbaiki | Siapa meminta, menyetujui, dan melakukan koreksi? Apakah pengguna melihat riwayat versi? |
| Pelaporan | Sekolah/Puskesmas melihat rapor/rekap dan mengunduh berulang | Data detail apa yang tampil per peran? Apakah laporan bertanggal/snapshot? |
| Pergantian operator | Admin Sistem mencabut akun lama dan memberi penugasan baru | Bagaimana memastikan arsip tetap milik institusi dan akses lama dicabut? |
| Permintaan subjek data/insiden | Permintaan akses/koreksi atau dugaan kebocoran diterima dan ditangani | Kanal, penanggung jawab, tenggat, eskalasi, dan bukti tindakan apa yang diperlukan? |

## 5. Kamus indikator untuk disahkan

Daftar ini diturunkan dari formulir sumber. Kolom “keputusan Fase 0” harus dilengkapi bersama tenaga kesehatan dan pemilik proses; nilai di bawah bukan rekomendasi ambang klinis.

| Kelompok | Indikator dari sumber | Keputusan yang perlu dicatat |
|---|---|---|
| Identitas/konteks | Sekolah, kelas, nama, tanggal lahir, NISN | Sumber data otoritatif; aturan siswa tanpa NISN; apakah perlu atribut jenis kelamin untuk kalkulasi; hak melihat/mengubah. |
| Umum | Tekanan darah | Format dan unit; metode pengukuran; batas/peringatan yang disahkan; wajib atau opsional. |
| Umum | Riwayat penyakit terdahulu dan keluarga | Definisi, batas teks, kewajiban, akses per role, dan apakah boleh ditampilkan ke siswa/sekolah. |
| Umum | Kebersihan diri: baik/kurang/buruk | Definisi operasional, pilihan, pihak pengisi, status belum diperiksa. |
| Gaya hidup | Sarapan: ya/tidak | Periode pertanyaan (misalnya hari pemeriksaan atau kebiasaan); pihak yang menjawab/mengisi; akses. |
| Gaya hidup | Merokok: ya/tidak | Definisi/kerahasiaan; siapa yang dapat melihat; penanganan jawaban kosong. |
| Gaya hidup | Jajan: ya/tidak | Definisi operasional/periode; pihak pengisi. |
| Gaya hidup | Minum alkohol/NAFZA: ya/tidak | Tetap satu pertanyaan atau dipisah; perlindungan privasi khusus; akses/tampilan dan tindak lanjut. |
| Gizi | Tinggi badan dan berat badan | Unit, presisi, metode, batas validasi/peringatan, wajib/opsional. |
| Gizi | IMT | Rumus, satuan, pembulatan, versi kalkulasi, kondisi tidak dapat dihitung. |
| Gizi | Status gizi: kurus/sangat kurus/normal/gemuk/obesitas | Standar resmi, indeks, umur/jenis kelamin yang dipakai, test vector, siapa mengesahkan. Jangan gunakan ambang dewasa. |
| Gizi | Anemia: ya/tidak | Metode pemeriksaan, sumber hasil, kategori yang disimpan, wajib/opsional. |
| Mata | Mata luar: sehat/tidak sehat; buta warna: ya/tidak; kacamata: ya/tidak | Definisi, metode, jawaban belum diperiksa, dan akses tampilan. |
| Telinga | Telinga luar: sehat/infeksi/serumen; pendengaran: normal/ada gangguan | Definisi, metode, opsi bila tidak dapat dinilai, dan pihak pengisi. |
| Gigi/mulut | Celah bibir/langit-langit; luka sudut mulut; sariawan; lidah kotor; luka lainnya; karies; gusi berdarah/bengkak; plak/sisa makanan; karang gigi; susunan gigi depan | Konfirmasi setiap indikator ya/tidak, makna “luka lainnya”, belum diperiksa, catatan yang diizinkan, dan apakah odontogram terperinci di luar MVP. |

### Matriks kelengkapan untuk dilengkapi

| Tahap/status | Indikator wajib | Indikator boleh kosong/tidak diperiksa | Siapa yang dapat mengubah |
|---|---|---|---|
| Draf | Belum ditetapkan | Belum ditetapkan | Operator Puskesmas berwenang |
| Siap ditinjau | Belum ditetapkan | Belum ditetapkan | Operator Puskesmas berwenang |
| Final | Belum ditetapkan | Belum ditetapkan | Perlu keputusan finalisasi/koreksi |
| Dipublikasikan | Belum ditetapkan | Belum ditetapkan | Perlu keputusan publikasi |

## 6. Matriks akses awal untuk dikonfirmasi

| Data/aksi | Admin Sistem | Operator Puskesmas | Operator Sekolah/Guru | Siswa |
|---|---|---|---|---|
| Membuat akun, role, dan scope | Ya | Tidak | Tidak | Tidak |
| Mengelola sekolah binaan/Puskesmas | Ya, administratif | Baca/operasional sesuai penugasan | Sekolah sendiri sesuai izin | Tidak |
| Mengelola kelas, siswa, enrollment | Sesuai kebijakan | Baca sasaran; perubahan hanya jika ditugaskan | Sekolah sendiri sesuai izin | Profil sendiri terbatas |
| Mengisi hasil pemeriksaan | Bukan penggunaan rutin | Ya, dalam scope | Tidak | Tidak |
| Melihat hasil kesehatan individual | Akses dukungan minimal dan tercatat jika disetujui | Dalam scope dan kebutuhan tugas | Siswa sekolah sendiri sesuai kebijakan | Milik sendiri yang dipublikasikan |
| Finalisasi/publikasi/koreksi | Tidak rutin; kontrol khusus jika disetujui | Sesuai alur yang disahkan | Tidak | Tidak |
| Ekspor detail | Sesuai kebutuhan administratif minimum | Scope Puskesmas | Sekolah sendiri sesuai izin | Tidak |

Untuk setiap sel yang berubah, catat: siapa menyetujui, alasan kebutuhan, scope, field sensitif, masa berlaku, serta bagaimana tindakan diuji. UI tersembunyi tidak dianggap kontrol akses; keputusan harus berlaku di API.

## 7. Keputusan dan register

Semua baris dimulai dengan status **Belum disepakati**. Isi status menjadi **Disetujui**, **Ditolak**, atau **Ditunda** hanya setelah keputusan dan pihak yang berwenang dicatat.

| ID | Keputusan | Pemilik persetujuan | Pihak yang dikonsultasikan | Status | Keputusan/tautan bukti | Tanggal |
|---|---|---|---|---|---|---|
| D0-01 | Standar status gizi anak, variabel, rumus, dan test vector | Pemilik proses/tenaga kesehatan | QA, backend, pemilik data | Belum disepakati |  |  |
| D0-02 | Jenis kelamin/atribut lain yang diperlukan untuk kalkulasi serta aksesnya | Pemilik proses dan pemilik data | Tenaga kesehatan, sekolah | Belum disepakati |  |  |
| D0-03 | Input tekanan darah, satuan, metode, dan aturan peringatan | Tenaga kesehatan | Operator Puskesmas, QA | Belum disepakati |  |  |
| D0-04 | Metode dan pencatatan anemia | Tenaga kesehatan | Operator Puskesmas | Belum disepakati |  |  |
| D0-05 | Alkohol/NAFZA tetap gabungan atau dipisah | Pemilik proses dan pemilik data | Tenaga kesehatan, sekolah | Belum disepakati |  |  |
| D0-06 | Lingkup odontogram MVP | Pemilik produk/proses klinis | Tenaga kesehatan, sekolah | Belum disepakati |  |  |
| D0-07 | Identitas petugas pemeriksa dan aktor pencatat; pemeriksaan tim | Pemilik proses Puskesmas | Operator Puskesmas, QA | Belum disepakati |  |  |
| D0-08 | Syarat draf, finalisasi, publikasi, koreksi, dan pemeriksaan ulang | Pemilik proses Puskesmas | Tenaga kesehatan, sekolah | Belum disepakati |  |  |
| D0-09 | Field hasil yang boleh dilihat sekolah/siswa dan aturan publikasi | Pemilik produk/data | Puskesmas, sekolah, perwakilan siswa | Belum disepakati |  |  |
| D0-10 | Cara verifikasi akun siswa dan pemulihan akses | Pemilik produk/data | Sekolah, Admin Sistem | Belum disepakati |  |  |
| D0-11 | Dasar pemrosesan data anak, pemberitahuan/persetujuan, retensi, hak subjek data | Pemilik data/pihak berwenang | Pemilik proses, keamanan | Belum disepakati |  |  |
| D0-12 | Penghapusan/arsip, wilayah hosting, insiden, dan RPO/RTO | Pemilik layanan/data | Keamanan, TI, hukum/kepatuhan | Belum disepakati |  |  |
| D0-13 | NISN kosong/duplikat, siswa pindah, dan koreksi identitas | Pemilik data sekolah | Puskesmas, sekolah | Belum disepakati |  |  |
| D0-14 | Format laporan, hak ekspor detail, dan ambang agregasi grafik | Pemilik produk/data | Sekolah, Puskesmas, keamanan | Belum disepakati |  |  |
| D0-15 | Volume pengguna/data, SLA, periode puncak, dukungan offline | Pemilik layanan | TI, Puskesmas, sekolah | Belum disepakati |  |  |
| D0-16 | Fungsi yang termasuk MVP dan fungsi yang ditunda | Pemilik produk | Semua perwakilan | Belum disepakati |  |  |

## 8. Batas MVP untuk dikonfirmasi

### Kandidat MVP sesuai PRD

- Master Puskesmas, sekolah, tahun ajaran, semester, kelas, siswa, akun, role, dan penugasan.
- Riwayat enrollment siswa dan histori pemeriksaan lintas semester/tahun ajaran.
- Pemeriksaan dengan semua indikator sumber, status pengisian, validasi, dan audit.
- Draf, finalisasi, publikasi, koreksi terkontrol, serta daftar sudah/belum diperiksa.
- Web operasional untuk Puskesmas dan sekolah.
- Rapor individu dan rekap sekolah/Puskesmas; ekspor privat berulang.
- Flutter untuk siswa melihat rapor dan histori yang dipublikasikan.
- Kontrol akses, log aktivitas, backup/restore, UAT, pilot, dan dukungan operasional.

### Di luar MVP kecuali diputuskan lain

- Diagnosis/resep/rekam medis klinis penuh atau keputusan terapi.
- Rujukan/tindak lanjut klinis yang belum memiliki proses dan pemilik.
- Akses orang tua/wali.
- Integrasi sistem eksternal/nasional.
- Odontogram interaktif rinci.
- Offline mobile dan aplikasi pemeriksaan mobile untuk petugas.

Pemilik produk mengisi keputusan akhir dan alasannya. Jangan menyebut suatu fitur “MVP” hanya karena sudah tertulis pada daftar kandidat; semua dependensi klinis, privasi, dan operasional harus siap.

## 9. Metrik dan kriteria keberhasilan yang disepakati

Isi nilai target setelah baseline dan kapasitas layanan diketahui. Jangan mengarang target angka operasional.

| Metrik | Definisi pengukuran | Target yang disetujui | Sumber/pemilik |
|---|---|---|---|
| Kelengkapan atribusi pemeriksaan | Proporsi pemeriksaan final dengan siswa, sekolah, periode, tanggal, pemeriksa, dan pencatat | 100% sesuai PRD | Audit pemeriksaan |
| Cakupan pemeriksaan | Siswa yang diperiksa dibanding sasaran pada periode | Belum ditetapkan | Rekap periode |
| Ketepatan akses | Uji bahwa siswa/sekolah tidak memperoleh data di luar scope | Tidak ada kebocoran akses | Hasil uji keamanan |
| Akurasi laporan | Rekonsiliasi angka laporan dengan record final sesuai filter | Harus konsisten; metode sampling disepakati | QA/pemilik laporan |
| Keberhasilan unduh | Pengguna berwenang dapat mengunduh ulang laporan yang diminta | Belum ditetapkan | Log ekspor/support |
| Kualitas koreksi | Perubahan final memiliki alasan, aktor, waktu, dan versi terdahulu | 100% perubahan final terlacak | Audit |
| Ketersediaan/performa | SLA dan p95 sesuai target layanan | Belum ditetapkan | Monitoring |
| Penyelesaian dukungan | Waktu respons/penyelesaian masalah pengguna | Belum ditetapkan | Support desk |

## 10. Risiko awal dan tindakan sebelum lanjut

| Risiko | Tindakan pada Fase 0 | Pemilik tindakan |
|---|---|---|
| Status gizi salah karena standar/umur/jenis kelamin belum diputuskan | Sahkan algoritma dan test vector klinis sebelum implementasi kalkulasi | Pemilik proses/tenaga kesehatan |
| Pengungkapan informasi sensitif anak | Sepakati dasar pemrosesan, akses per field, publikasi, retensi, dan prosedur insiden | Pemilik data |
| Siswa tertaut ke akun yang keliru | Tentukan metode verifikasi dan proses pemulihan/koreksi tautan | Pemilik produk/sekolah |
| Operator sekolah/Puskesmas mendapat akses melampaui tugas | Sahkan scope dan larangan akses; jadikan pengujian API sebagai acceptance criterion | Pemilik produk/keamanan |
| Riwayat hilang saat siswa atau operator berpindah | Tetapkan pemilik arsip institusional, enrollment historis, dan proses offboarding | Pemilik data/layanan |
| Salah tafsir jawaban kosong sebagai “Tidak” | Setujui status indikator dan uji contoh formulir | Pemilik proses/tenaga kesehatan |
| Laporan/ekspor membuka identitas dari kelompok kecil | Sepakati hak ekspor detail dan aturan agregasi sebelum desain laporan | Pemilik data |
| Target layanan tidak sesuai kapasitas | Estimasikan volume/puncak dan sepakati SLA/RPO/RTO | Pemilik layanan/TI |

## 11. Catatan hasil lokakarya

Lengkapi setelah sesi validasi; jangan mengisi dengan asumsi.

### Ringkasan proses yang disepakati

- Proses saat ini:
- Perubahan yang diinginkan:
- Pengecualian penting:

### Ruang lingkup MVP yang disepakati

- Termasuk:
- Ditunda:
- Alasan:

### Pertanyaan terbuka

| Pertanyaan | Dampak bila belum dijawab | Penanggung jawab | Tenggat/gerbang |
|---|---|---|---|
|  |  |  |  |

### Persetujuan

| Perwakilan | Nama/jabatan | Status persetujuan | Tanggal/catatan |
|---|---|---|---|
| Pemilik produk |  | Belum ditinjau |  |
| Pemilik proses Puskesmas |  | Belum ditinjau |  |
| Tenaga kesehatan/UKS |  | Belum ditinjau |  |
| Perwakilan sekolah |  | Belum ditinjau |  |
| Pemilik data/pelindungan data |  | Belum ditinjau |  |
| Keamanan dan operasional |  | Belum ditinjau |  |

## 12. Gerbang keluar Fase 0

Fase 0 dinyatakan selesai hanya jika:

- [ ] Pemilik produk, pemilik proses, tenaga kesehatan, sekolah, pemilik data, TI/keamanan, dan operasional telah terwakili.
- [ ] Alur tahun ajaran, enrollment, pemeriksaan, finalisasi, publikasi, koreksi, laporan, dan pergantian operator telah ditinjau.
- [ ] Kamus indikator dan matriks wajib/opsional telah disahkan; keputusan klinis kritis memiliki test vector.
- [ ] Role/scope serta field yang boleh dilihat/diekspor setiap peran disepakati.
- [ ] Dasar pemrosesan data anak, pemberitahuan/persetujuan bila diperlukan, retensi, hak subjek data, dan insiden mempunyai pemilik keputusan.
- [ ] Batas MVP, kriteria keberhasilan, kebutuhan volume/SLA, serta fungsi yang ditunda disetujui.
- [ ] Semua keputusan tercatat dengan pemilik, status, tanggal, dan bukti persetujuan; keputusan yang belum selesai mempunyai owner dan gerbang penyelesaian.
- [ ] Tidak ada isu klinis, akses, atau privasi kritis yang diam-diam diasumsikan untuk memulai implementasi.

Setelah gerbang ini disetujui, hasil Fase 0 digunakan untuk membekukan desain Fase 1 dan mengubah keputusan yang disepakati menjadi wireframe, ERD, matriks akses, kontrak API, threat model, serta test plan.
