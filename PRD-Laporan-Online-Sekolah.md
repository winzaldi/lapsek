# Product Requirements Document
## Laporan Online Sekolah — Data Kesehatan UKS

**Status:** Rancangan awal untuk validasi pemangku kepentingan  
**Versi:** 1.0  
**Tanggal:** 30 Oktober 2026  
**Platform:** Laravel REST API, Next.js web, Flutter mobile  
**Pengguna utama:** Puskesmas, sekolah, guru/operator sekolah, dan siswa

## 1. Ringkasan produk

Laporan Online Sekolah (LAOS) adalah aplikasi pencatatan dan pelaporan pemeriksaan kesehatan UKS untuk SD/sederajat, SMP/sederajat, SMA/sederajat, dan SLB. Pemeriksaan dilakukan setiap semester. Satu siswa mempunyai satu riwayat kesehatan yang berlanjut lintas kelas, tahun ajaran, dan sekolah (bila ada proses perpindahan yang sah). Puskesmas menyimpan arsip pemeriksaan secara berkelanjutan, termasuk identitas petugas pemeriksa, sehingga arsip tetap tersedia saat pengelola program berganti.

Sekolah dapat melihat dan mengunduh laporan siswanya berulang kali menurut semester atau tahun ajaran. Siswa dapat melihat rapor kesehatannya sendiri. Aplikasi mendukung web responsif dan aplikasi mobile; kedua klien menggunakan API dan aturan akses yang sama.

## 2. Masalah dan tujuan

### Masalah
- Data pemeriksaan berisiko tersebar di formulir atau berkas yang sulit ditelusuri.
- Riwayat siswa tidak selalu terbawa saat pergantian kelas, tahun ajaran, maupun petugas program.
- Sekolah dan Puskesmas memerlukan arsip serta rekap yang dapat dicari dan diunduh kembali.
- Data kesehatan anak memerlukan pembatasan akses dan jejak audit yang jelas.

### Tujuan produk
1. Membuat satu sumber data pemeriksaan per siswa dengan histori lintas semester dan tahun ajaran.
2. Mencatat siapa yang melakukan, memasukkan, dan mengubah data pemeriksaan.
3. Menyajikan laporan individual dan rekap sekolah/Puskesmas yang bisa diunduh berulang.
4. Memberikan akses berbasis peran serta sekolah, wilayah kerja, dan kepemilikan data.
5. Mengurangi kesalahan input melalui validasi, rumus, dan pilihan terstandar.

### Indikator keberhasilan
- 100% pemeriksaan yang difinalisasi memiliki siswa, sekolah, periode, tanggal, dan petugas/aktor pencatat yang dapat ditelusuri.
- Tidak ada siswa yang dapat melihat laporan siswa lain melalui UI maupun API.
- Pengguna sekolah yang berwenang dapat menemukan dan mengunduh laporan untuk periode yang tersedia.
- Rekap semester dan tahunan konsisten dengan data pemeriksaan yang telah difinalisasi.
- Perubahan data final tercatat pada audit log dengan aktor, waktu, dan alasan.

## 3. Ruang lingkup

### Termasuk MVP
- Master wilayah kerja Puskesmas, sekolah, tahun ajaran, semester, kelas, siswa, guru/operator, dan akun.
- Pengelolaan profil sekolah, guru/operator, siswa, dan Puskesmas.
- Penugasan siswa ke sekolah dan kelas berdasarkan tahun ajaran, dengan histori.
- Pemeriksaan kesehatan per siswa per semester, penyimpanan draf, finalisasi, dan koreksi terkontrol.
- Riwayat pemeriksaan per siswa dan daftar siswa yang telah/belum diperiksa.
- Rapor individu, rekap sekolah, rekap Puskesmas, grafik dasar, ekspor PDF/CSV/XLSX.
- Akses dari Next.js dan Flutter melalui API Laravel.
- Log aktivitas, pengunduhan, perubahan, dan pemeriksaan.

### Tidak termasuk MVP (dapat menjadi fase berikutnya)
- Diagnosis, resep, rekam medis klinis lengkap, dan penggantian konsultasi tenaga kesehatan.
- Pengelolaan rujukan/tindak lanjut klinis, kecuali diputuskan dan disahkan pemilik proses.
- Akses orang tua/wali (belum termasuk daftar pengguna yang diberikan).
- Integrasi otomatis dengan sistem nasional atau sistem sekolah/Puskesmas lain.
- Pengumpulan data lokasi, biometrik, atau data kesehatan yang tidak disebut dalam formulir sumber.

## 4. Pengguna, peran, dan izin

| Peran | Cakupan data dan kemampuan |
|---|---|
| Admin Sistem | Mengelola konfigurasi global, akun/peran, referensi, dan dukungan operasional. Akses ke data kesehatan lintas wilayah harus dibatasi, beralasan, dan tercatat; bukan akses rutin tanpa kebutuhan. |
| Operator Puskesmas | Menjalankan seluruh operasional UKS Puskesmas pada wilayah yang ditugaskan: melihat sekolah binaan, memeriksa/memperbarui data operasional sekolah sesuai izin, membuat dan mengisi pemeriksaan, menyimpan draf, finalisasi sesuai kewenangan, memantau cakupan, serta mengakses arsip dan laporan. Tidak ada peran Admin Puskesmas terpisah; pembuatan akun, peran, penugasan, dan konfigurasi global ditangani Admin Sistem. |
| Operator Sekolah/Guru | Mengelola data sekolah yang diizinkan, kelas, daftar siswa/penempatan, melihat status pemeriksaan dan laporan siswa di sekolahnya, serta mengunduh laporan. Tidak dapat mengubah hasil pemeriksaan klinis. |
| Siswa | Melihat rapor milik sendiri dan histori pemeriksaan yang dipublikasikan. Tidak dapat mengubah hasil pemeriksaan atau melihat identitas/hasil siswa lain. |

Ketentuan:
- Satu akun dapat mempunyai lebih dari satu peran/penugasan jika disetujui kebijakan, tetapi setiap permintaan API tetap diperiksa terhadap cakupan sekolah/wilayah.
- Perubahan sekolah siswa harus mempertahankan histori pemeriksaan lama. Penempatan aktif ditentukan per tahun ajaran.
- Akses Admin Sistem ke data pemeriksaan harus diminimalkan dan dicatat; kebutuhan dukungan dapat menggunakan akses sementara yang disetujui.
- Puskesmas tidak memiliki peran administrator lokal. Operator Puskesmas bekerja dengan hak operasional sesuai cakupan yang diberikan Admin Sistem; pengelolaan akun dan hak akses tetap terpusat.
- Siswa hanya melihat hasil setelah pemeriksaan difinalisasi dan dipublikasikan sesuai kebijakan.

## 5. Struktur menu

### Web Puskesmas
1. **Beranda** — ringkasan pemeriksaan semester, kelengkapan data, dan sekolah binaan.
2. **Master Data**
   - Puskesmas dan wilayah kerja
   - Sekolah
   - Tahun ajaran dan semester
   - Kelas
   - Siswa dan penempatan sekolah/kelas
   - Guru/operator dan akun
3. **Pemeriksaan**
   - Buat/cari pemeriksaan
   - Daftar pemeriksaan per sekolah/periode/status
   - Daftar belum diperiksa
   - Detail, riwayat perubahan, koreksi
4. **Laporan**
   - Rapor kesehatan siswa
   - Rekap sekolah per semester/tahun ajaran
   - Rekap wilayah Puskesmas
   - Ekspor/riwayat unduhan
5. **Grafik**
   - Cakupan pemeriksaan
   - Distribusi indikator/status agregat
6. **Riwayat aktivitas**
   - Riwayat pemeriksaan dan perubahan yang dapat diakses sesuai kewenangan
7. **Profil akun**

### Web Sekolah
1. **Beranda** — jumlah siswa, status pemeriksaan, dan periode aktif.
2. **Profil Sekolah**
3. **Guru/Operator**
4. **Kelas**
5. **Siswa**
6. **Laporan**
   - Rapor per siswa
   - Rekap kelas/sekolah
   - Unduh per semester/per tahun ajaran
7. **Profil akun**

### Siswa (web/mobile)
1. **Rapor Kesehatan**
2. **Riwayat Pemeriksaan**
3. **Profil** — data identitas yang diizinkan untuk dilihat, bukan mengubah hasil klinis.

### Admin Sistem
1. Dasbor sistem
2. Puskesmas dan sekolah
3. Pengguna/peran/penugasan
4. Tahun ajaran, semester, dan referensi indikator
5. Audit dan konfigurasi

## 6. Alur pengguna utama

### A. Persiapan tahun ajaran
1. Admin Sistem memastikan data Puskesmas dan penugasan sekolah binaan aktif.
2. Admin Sistem membuat atau mengaktifkan tahun ajaran dan dua periode semester, dengan tanggal mulai/akhir.
3. Operator sekolah memasukkan/memperbarui kelas, siswa, dan penempatan siswa pada tahun ajaran.
4. Operator Puskesmas memeriksa daftar sekolah binaan dan cakupan siswa; kendala akun atau penugasan ditangani Admin Sistem.
5. Sistem mempertahankan penempatan dan pemeriksaan dari tahun sebelumnya sebagai histori, bukan menimpa data lama.

### B. Pemeriksaan oleh Puskesmas
1. Operator memilih sekolah, semester, kelas, dan siswa (atau membuka daftar sasaran).
2. Sistem memeriksa hak akses, periode aktif, dan pemeriksaan duplikat untuk siswa-periode.
3. Operator mengisi formulir indikator; sistem melakukan validasi dan menghitung IMT bila input memadai.
4. Operator menyimpan draf. Draf dapat dilanjutkan oleh petugas berwenang sesuai kebijakan.
5. Petugas meninjau lalu memfinalisasi pemeriksaan. Sistem menyimpan pelaku, waktu, dan status.
6. Pemeriksaan final tersedia pada histori Puskesmas dan laporan sekolah/siswa sesuai izin.
7. Koreksi setelah finalisasi dilakukan melalui alur koreksi dengan alasan dan audit, bukan penghapusan tanpa jejak.

### C. Akses laporan oleh sekolah
1. Guru/operator masuk dan memilih tahun ajaran, semester, kelas, atau siswa.
2. Sistem mengembalikan hanya siswa dan pemeriksaan dalam cakupan sekolah yang ditugaskan.
3. Pengguna membuka rapor atau rekap, melihat tanggal/periode/petugas dan status data.
4. Pengguna mengunduh PDF/CSV/XLSX. Unduhan berulang diperbolehkan dan tercatat.

### D. Akses rapor oleh siswa
1. Siswa masuk dengan akun yang ditautkan secara terverifikasi ke profil siswa.
2. Sistem menampilkan hanya pemeriksaan milik siswa tersebut yang telah dipublikasikan.
3. Siswa dapat memilih periode dan melihat histori.

### E. Pemantauan Puskesmas
1. Operator Puskesmas memilih tahun ajaran dan semester.
2. Sistem menampilkan cakupan pemeriksaan dan daftar siswa belum diperiksa menurut sekolah binaan.
3. Operator Puskesmas mengekspor rekap agregat atau detail sesuai hak akses.
4. Pergantian pengelola program dilakukan dengan mencabut/menambah penugasan; arsip tidak terkait pada akun petugas lama.

## 7. Data pemeriksaan dan aturan indikator

Formulir sumber **“indikator laos aplikasi.docx”** memuat indikator berikut. Formulir digital perlu menyimpan nilai terstruktur, bukan satu kolom teks bebas untuk seluruh hasil.

### Identitas dan konteks
- Sekolah, kelas, nama siswa, tanggal lahir, NISN.
- Tahun ajaran, semester, tanggal pemeriksaan.
- Petugas pemeriksa dan akun yang memasukkan data.

### Kesehatan umum
- Tekanan darah (sumber menyatakan diketik).
- Riwayat penyakit terdahulu (teks).
- Riwayat penyakit keluarga (teks).
- Kebersihan diri: baik/kurang/buruk.

### Gaya hidup
- Sarapan: ya/tidak.
- Merokok: ya/tidak.
- Jajan: ya/tidak.
- Minum alkohol/NAFZA: ya/tidak.

### Gizi
- Tinggi badan dan berat badan.
- Indeks massa tubuh (IMT), dihitung sistem.
- Status gizi: kurus/sangat kurus/normal/gemuk/obesitas.
- Anemia: ya/tidak.

### Mata
- Mata luar: sehat/tidak sehat.
- Buta warna: ya/tidak.
- Menggunakan kacamata: ya/tidak.

### Telinga
- Telinga luar: sehat/infeksi/serumen.
- Tajam pendengaran: normal/ada gangguan.

### Gigi dan mulut
- Celah bibir/langit-langit: ya/tidak.
- Luka pada sudut mulut: ya/tidak.
- Sariawan: ya/tidak.
- Lidah kotor: ya/tidak.
- Luka lainnya: ya/tidak.
- Gigi berlubang/karies: ya/tidak.
- Gusi mudah berdarah: ya/tidak.
- Gusi bengkak: ya/tidak.
- Gigi kotor (plak/sisa makanan): ya/tidak.
- Karang gigi: ya/tidak.
- Susunan gigi depan tidak teratur: ya/tidak.
- Label “odontogram” ada pada dokumen sumber, tetapi rincian diagram/nomor gigi tidak diberikan. MVP menyimpan indikator di atas; bentuk odontogram interaktif memerlukan spesifikasi klinis terpisah.

### Aturan pengisian yang disarankan
- Pilihan ya/tidak harus membedakan **Belum diperiksa** dari **Tidak**. Kolom indikator opsional selama draf; wajib atau dapat ditunda saat finalisasi ditetapkan pada matriks kelengkapan.
- Riwayat penyakit dibatasi panjangnya, tidak boleh memuat markup/script, dan hanya ditampilkan ke pengguna yang berhak.
- Tekanan darah sebaiknya dua nilai numerik (sistolik/diastolik, mmHg) dengan validasi rentang konfigurabel; kebutuhan kolom catatan/format khusus perlu disepakati.
- Tinggi badan dicatat dalam cm dan berat badan dalam kg dengan presisi yang ditetapkan. Nilai mentah dan satuan disimpan.
- IMT = berat (kg) / tinggi (m)²; tidak dihitung jika tinggi/berat kosong atau tidak masuk akal. Simpan nilai kalkulasi dan versi rumus.
- Status gizi anak tidak boleh memakai ambang IMT dewasa. Algoritma harus mengikuti standar pertumbuhan yang disahkan (misalnya indeks menurut umur dan jenis kelamin, standar Kemenkes/WHO yang dipilih), menggunakan umur pada tanggal pemeriksaan. Hasil status gizi yang diketik manual harus dicegah atau secara eksplisit dibedakan dari hasil otomatis.
- Status pemeriksaan setiap indikator: belum diisi, tidak diperiksa, atau terisi. Ketiga keadaan tidak boleh disamakan.
- “Minum alcohol / napza” adalah indikator gabungan pada sumber. Konfirmasi apakah perlu dipisah menjadi dua pertanyaan; hindari menyimpulkan salah satu dari jawaban indikator gabungan.

## 8. Model data / tabel utama

Skema berikut konseptual dan dapat disesuaikan dengan konvensi Laravel. Seluruh tabel menggunakan primary key internal (UUID disarankan untuk API), `created_at`, `updated_at`, dan bila relevan `deleted_at`/status aktif. Data pemeriksaan tidak dihapus secara fisik melalui antarmuka biasa.

| Tabel | Kolom penting | Relasi/kendala |
|---|---|---|
| `puskesmas` | `id`, `code`, `name`, `address`, `status` | Satu Puskesmas memiliki banyak sekolah binaan dan pengguna. |
| `schools` | `id`, `npsn`, `name`, `level`, `type`, `address`, `puskesmas_id`, `status` | `level`: SD/SMP/SMA; `type` mencakup sederajat/SLB sesuai master. NPSN unik bila tersedia. |
| `users` | `id`, `name`, `email/username`, `password_hash`, `status`, `last_login_at` | Tidak menyimpan password polos. Identitas akun dipisah dari profil siswa. |
| `roles`, `permissions`, `user_roles` | role/permission dan cakupan | Cakupan dapat menunjuk Puskesmas/sekolah; pemeriksaan otorisasi server-side. |
| `school_staff` | `id`, `school_id`, `user_id`, `position`, `active_from`, `active_to` | Histori penugasan guru/operator sekolah. |
| `students` | `id`, `nisn` nullable, `name`, `birth_date`, `sex` (perlu konfirmasi), `status` | NISN unik jika terisi; identitas internal tetap tersedia jika NISN belum ada. |
| `student_accounts` | `student_id`, `user_id`, `verified_at` | Tautan akun siswa yang diverifikasi; satu profil siswa tidak boleh ditautkan ke akun yang salah. |
| `academic_years` | `id`, `label`, `starts_on`, `ends_on`, `status` | Rentang tanggal tidak tumpang tindih tanpa pengecualian yang disetujui. |
| `semesters` | `id`, `academic_year_id`, `name`, `starts_on`, `ends_on`, `status` | Tepat dua semester standar per tahun ajaran; penamaan/tanggal dapat dikonfigurasi. |
| `classes` | `id`, `school_id`, `academic_year_id`, `name`, `grade_level` | Nama kelas unik dalam sekolah dan tahun ajaran. |
| `student_enrollments` | `id`, `student_id`, `school_id`, `class_id`, `academic_year_id`, `status` | Histori penempatan; validasi kelas dan sekolah harus cocok. |
| `examinations` | `id`, `student_id`, `school_id`, `class_id`, `semester_id`, `examined_at`, `status`, `examiner_user_id`, `entered_by_user_id`, `finalized_at`, `published_at`, `version` | Maksimal satu pemeriksaan aktif/final per siswa-semester, kecuali koreksi/ulang resmi. Sekolah dan kelas disimpan sebagai snapshot konteks saat pemeriksaan. |
| `examination_general` | `examination_id`, tekanan darah, riwayat penyakit, kebersihan diri | Relasi 1:1 ke pemeriksaan. |
| `examination_lifestyle` | `examination_id`, sarapan, merokok, jajan, alkohol/NAFZA | Relasi 1:1; akses terbatas dan audit akses. |
| `examination_nutrition` | `examination_id`, tinggi_cm, berat_kg, bmi, status_gizi, anemia, `calculation_version` | Relasi 1:1; BMI/status memiliki asal nilai otomatis/manual bila relevan. |
| `examination_eyes` | `examination_id`, mata_luar, buta_warna, kacamata | Relasi 1:1. |
| `examination_ears` | `examination_id`, kondisi_telinga_luar, pendengaran | Relasi 1:1. |
| `examination_oral` | `examination_id`, seluruh indikator gigi/mulut | Relasi 1:1; nilai boolean nullable agar “belum diperiksa” berbeda dengan tidak. |
| `examination_corrections` | `id`, `examination_id`, `requested_by`, `approved_by`, `reason`, `old_version`, `new_version` | Jejak koreksi setelah finalisasi. |
| `audit_logs` | `actor_user_id`, `action`, `resource_type`, `resource_id`, `occurred_at`, `request_id`, metadata minimal | Catat perubahan/status/ekspor/akses sensitif. Hindari menyalin seluruh isi kesehatan ke log. |
| `report_exports` | `id`, `requested_by`, `scope`, `period`, `format`, `status`, `file_key`, `expires_at` | Tautan file tidak publik; log unduhan dan masa berlaku. |

**Kendala integritas penting**
- Unique pemeriksaan aktif pada `(student_id, semester_id)` untuk mencegah duplikasi; jalur koreksi tidak membuat salinan yang mengaburkan histori.
- Foreign key untuk siswa, sekolah, kelas, semester, dan petugas.
- Data hasil final menyimpan versi indikator/rumus dan waktu finalisasi.
- `students` tidak dihapus bila sudah memiliki pemeriksaan; nonaktifkan/arsipkan profil.
- Jika siswa pindah sekolah, buat enrollment baru; jangan mengubah sekolah pemeriksaan historis.

## 9. Fungsi backend (Laravel API)

### Layanan/domain
- Autentikasi, manajemen sesi/token, reset kredensial, dan kebijakan akun.
- Otorisasi berbasis role + scope Puskesmas/sekolah pada setiap endpoint dan query.
- CRUD terkontrol master Puskesmas, sekolah, kelas, siswa, tahun ajaran, semester, dan penugasan.
- Impor siswa massal dengan preview, validasi baris, laporan kesalahan, dan pencegahan duplikasi.
- Pembuatan/pembaruan draf pemeriksaan, validasi lintas entitas, finalisasi, publikasi, koreksi, dan histori versi.
- Perhitungan IMT serta pemanggilan/implementasi algoritma status gizi yang telah disetujui; versi rumus disimpan.
- Query histori pemeriksaan, status cakupan, rekap agregat, dan grafik.
- Pembuatan ekspor PDF/CSV/XLSX secara aman; pekerjaan besar dapat diproses antrean.
- Audit aktivitas, log akses data sensitif, serta pemantauan kegagalan.
- API versioning (`/api/v1`), pagination, filter, sort yang diizinkan, dan kontrak error konsisten.

### Kelompok endpoint konseptual
- `POST /api/v1/auth/login`, `POST /auth/logout`, `POST /auth/refresh`
- `GET/POST /schools`, `GET/POST /students`, `GET/POST /classes`
- `GET/POST /academic-years`, `GET/POST /semesters`
- `GET/POST /examinations`, `GET/PATCH /examinations/{id}`
- `POST /examinations/{id}/finalize`, `POST /examinations/{id}/publish`
- `POST /examinations/{id}/corrections`
- `GET /students/{id}/health-history`
- `GET /reports/schools/{id}`, `GET /reports/puskesmas/{id}`
- `POST /report-exports`, `GET /report-exports/{id}`, `GET /report-exports/{id}/download`
- `GET /audit-logs` (khusus peran berwenang)

Daftar endpoint merupakan rancangan, bukan kontrak final. Gunakan DTO/Form Request dan Resource API; jangan mengekspos model database mentah.

## 10. Validasi bisnis dan input

| Area | Validasi minimum |
|---|---|
| Akun | Username/email unik sesuai kebijakan; password disimpan dengan hash; throttling login; status akun aktif. |
| Sekolah | Jenjang hanya dari master; sekolah terikat pada Puskesmas aktif; NPSN format/keunikan divalidasi bila diisi. |
| Siswa | Nama wajib; tanggal lahir valid dan tidak di masa depan; NISN format dan unik bila terisi; data siswa tidak dihapus jika sudah punya pemeriksaan. |
| Penempatan | Kelas harus berasal dari sekolah dan tahun ajaran yang sama; rentang penugasan/keanggotaan tidak tumpang tindih secara tidak sah. |
| Periode | Tanggal pemeriksaan berada dalam rentang semester atau ada override beralasan; semester harus terkait tahun ajaran terpilih. |
| Pemeriksaan | Satu pemeriksaan per siswa-semester; siswa harus memiliki enrollment yang sesuai; pemeriksa aktif dan memiliki scope; pemeriksaan tidak final tanpa kelengkapan minimum. |
| Pilihan | Nilai enum harus berasal dari pilihan yang diizinkan; boolean mendukung null untuk belum diperiksa. |
| Pengukuran | Angka menggunakan unit konsisten dan batas masuk akal yang dapat dikonfigurasi; nilai di luar batas memberi error/peringatan eksplisit, tidak diam-diam dikoreksi. |
| Finalisasi | Memerlukan pemeriksa, tanggal, dan isian wajib yang disepakati; finalisasi dikunci dan koreksi dilakukan melalui alur berjejak. |
| Impor/ekspor | Validasi ukuran, tipe, encoding, formula spreadsheet berbahaya, data duplikat, serta batas jumlah baris. |

## 11. Privasi, keamanan, dan mitigasi risiko

Data kesehatan siswa adalah data pribadi sensitif dan subjeknya banyak anak. Implementasi perlu penilaian dampak pelindungan data, dasar pemrosesan yang sah, pemberitahuan yang sesuai usia, persetujuan/otorisasi bila diwajibkan, serta jadwal retensi/pemusnahan yang disahkan sebelum produksi.

| Risiko | Dampak | Mitigasi yang diperlukan |
|---|---|---|
| Kebocoran rapor antar sekolah/siswa | Tinggi | Pemeriksaan scope server-side pada setiap akses; deny-by-default; uji IDOR; siswa hanya dapat mengakses ID sendiri. |
| Pengambilalihan akun | Tinggi | Hash password kuat, MFA untuk admin/petugas jika tersedia, throttling, proteksi reset, sesi/token berumur terbatas, pencabutan token saat akun dinonaktifkan. |
| Akses berlebihan oleh operator/admin | Tinggi | Least privilege, penugasan scope dan masa berlaku, review akses berkala, log akses sensitif, prosedur akses darurat yang disetujui. |
| Paparan indikator gaya hidup/riwayat penyakit | Tinggi | Minimalkan tampilan dan ekspor; batasi berdasarkan peran; jangan tampilkan di grafik publik/kelas; hindari data rinci dalam log/URL/notifikasi. |
| Perubahan/penghapusan hasil tanpa jejak | Tinggi | Finalisasi, versioning, koreksi beralasan/approval, audit append-only dan backup teruji. |
| Kebocoran file ekspor | Tinggi | File private storage, URL unduh berumur pendek, otorisasi saat unduh, enkripsi, masa kedaluwarsa dan penghapusan terjadwal. |
| Salah klasifikasi gizi anak | Tinggi | Jangan gunakan ambang dewasa; persetujuan standar dan uji klinis; versi algoritma; validasi dengan kasus rujukan. |
| Akun siswa tertaut ke siswa yang salah | Tinggi | Verifikasi identitas/tautan oleh pihak berwenang; proses koreksi; jangan hanya mengandalkan nama atau NISN yang diketik bebas. |
| Data tidak akurat/duplikat | Sedang-tinggi | Unique constraint, validasi impor, deduplikasi terkontrol, audit, indikator belum diperiksa. |
| Ketidaktersediaan data saat pergantian petugas | Sedang-tinggi | Arsip dimiliki institusi, bukan akun individu; onboarding/offboarding; backup/restore dan dokumentasi admin. |
| Ancaman web/API (CSRF, XSS, SQLi, brute force) | Tinggi | Framework protections, parameterized queries, output encoding, CSRF/CORS yang ketat, validasi input, rate limit, security testing dan dependency patching. |
| Kehilangan data/insiden infrastruktur | Tinggi | Enkripsi transit dan at-rest, backup terenkripsi terpisah, uji pemulihan, monitoring, rencana respons insiden. |

### Persyaratan keamanan minimum
- HTTPS wajib; rahasia aplikasi disimpan di secret manager/environment terlindungi, tidak di repository.
- Enkripsi penyimpanan/backup serta kebijakan rotasi kunci.
- RBAC + scope checks di backend; frontend hanya menyembunyikan UI, bukan kontrol keamanan.
- Catat login, perubahan data, akses/ekspor data sensitif, dan perubahan hak akses; batasi retensi log serta redaksi.
- Hindari data kesehatan pada analytics pihak ketiga, crash report, URL, atau push notification.
- Terapkan CSP, validasi unggahan, ukuran payload, rate limiting, dan security headers.
- Backup/restore diuji; tetapkan RPO/RTO bersama pemilik sistem.
- Kebijakan retensi, hak akses/koreksi subjek data, prosedur insiden, dan penghapusan harus ditetapkan sebelum go-live.

## 12. Kebutuhan nonfungsional

- **Ketersediaan:** sasaran awal 99,5% per bulan di luar pemeliharaan terjadwal (perlu disetujui pemilik layanan).
- **Kinerja:** p95 endpoint baca umum < 2 detik untuk beban normal; ekspor besar diproses asinkron.
- **Skalabilitas:** mendukung banyak Puskesmas/sekolah, data lintas tahun ajaran, pagination, dan indeks pada foreign key/periode.
- **Kompatibilitas:** web responsif untuk desktop/tablet; Flutter Android dan iOS mengikuti dukungan versi yang disepakati.
- **Aksesibilitas:** bahasa Indonesia, label jelas, navigasi keyboard pada web, kontras dan ukuran teks memadai.
- **Keandalan:** transaksi untuk finalisasi; idempotensi untuk permintaan ekspor/operasi yang dapat diulang.
- **Backup:** jadwal, retensi, enkripsi, dan pengujian pemulihan ditetapkan saat desain operasional.
- **Auditabilitas:** seluruh perubahan klinis dan administratif yang sensitif dapat ditelusuri.

## 13. Laporan dan grafik

### Rapor kesehatan siswa
- Identitas minimal yang disetujui, sekolah/kelas saat pemeriksaan, tanggal, semester, tahun ajaran.
- Hasil indikator kesehatan terstruktur dan status “belum diperiksa” yang terlihat jelas.
- Perbandingan histori menurut semester; jangan menyajikan perubahan sebagai diagnosis.
- Petugas pemeriksa dan status final/publikasi.

### Rekap sekolah/Puskesmas
- Jumlah sasaran, diperiksa, belum diperiksa, dan cakupan per sekolah/kelas/periode.
- Distribusi indikator dalam bentuk agregat sesuai peran dan ambang minimum kelompok untuk mengurangi identifikasi siswa.
- Pilihan filter periode, jenjang, sekolah, kelas, serta status.
- Ekspor menyertakan periode, waktu pembuatan, dan keterangan bahwa data mencerminkan saat ekspor dibuat.
- Ekspor detail individu dibatasi pada peran yang berwenang; siswa tidak mendapat ekspor kolektif.

## 14. Skenario pengujian dan kriteria penerimaan

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| T01 | Admin Sistem membuat tahun ajaran dengan dua semester | Rentang dan relasi valid; periode tampil untuk sekolah/Puskesmas yang berwenang. |
| T02 | Siswa dipindah kelas pada tahun ajaran baru | Enrollment lama tetap; enrollment baru aktif; histori pemeriksaan lama tidak berubah. |
| T03 | Operator Puskesmas membuat draf pemeriksaan | Draf tersimpan dengan aktor, siswa, sekolah, kelas, semester, dan waktu yang tepat. |
| T04 | Operator mencoba pemeriksaan kedua untuk siswa-semester yang sama | Sistem menolak duplikasi atau mengarahkan ke alur koreksi/ulang yang disahkan. |
| T05 | Operator menyimpan nilai tinggi/berat valid | IMT dihitung dalam satuan benar; hasil dan versi rumus tersimpan. |
| T06 | Tinggi/berat kosong, nol, negatif, atau di luar rentang | Tidak ada IMT palsu; validasi/peringatan eksplisit muncul. |
| T07 | Status gizi dihitung untuk siswa dengan usia/jenis kelamin berbeda | Algoritma standar anak yang disahkan digunakan; hasil sesuai test vector klinis dan umur pada tanggal pemeriksaan. |
| T08 | Checkbox indikator tidak disentuh | Nilai tetap belum diperiksa/null, bukan otomatis “Tidak”. |
| T09 | Petugas finalisasi data tidak lengkap | Finalisasi ditolak dan daftar kekurangan ditampilkan. |
| T10 | Petugas mengoreksi pemeriksaan final | Perubahan memerlukan alasan/approval sesuai kebijakan; versi sebelumnya dan audit tetap tersedia. |
| T11 | Guru sekolah A meminta laporan siswa sekolah B melalui URL/API | Ditolak (403/404 sesuai kebijakan) dan akses tercatat tanpa membocorkan isi. |
| T12 | Siswa mencoba membaca ID siswa lain | Ditolak pada API meskipun request dibuat manual; tidak bergantung pada UI. |
| T13 | Siswa membuka rapor sendiri | Hanya pemeriksaan miliknya yang telah dipublikasikan tampil. |
| T14 | Operator Puskesmas lama dinonaktifkan | Tidak dapat login; riwayat pemeriksaan dan atribusi petugas tetap tersimpan. |
| T15 | Sekolah mengunduh ulang laporan semester/tahunan | Ekspor dapat dibuat berulang dengan filter dan isi yang konsisten; unduhan tercatat. |
| T16 | Pengguna mencoba menebak ID file ekspor | File tidak dapat diakses tanpa otorisasi dan tautan sah yang belum kedaluwarsa. |
| T17 | Impor siswa berisi NISN duplikat atau kelas tidak sesuai sekolah | Baris invalid ditandai, alasan jelas; tidak menghasilkan data parsial yang tidak terkontrol. |
| T18 | Ekspor spreadsheet memuat input berawalan formula | Nilai disanitasi agar tidak dieksekusi sebagai formula saat dibuka. |
| T19 | Rekap grafik untuk kelompok sangat kecil | Data ditahan/agregat sesuai ambang privasi yang disetujui; tidak membuka identitas. |
| T20 | Pemulihan backup pada lingkungan uji | Data dapat dipulihkan sesuai RPO/RTO yang disetujui dan integritas relasi diverifikasi. |

### Kriteria penerimaan MVP
- Seluruh peran inti dapat menyelesaikan alur yang menjadi kewenangannya di web; fungsi rapor siswa tersedia di web dan mobile.
- Semua indikator sumber mempunyai representasi data dan status kelengkapan.
- Histori pemeriksaan siswa dapat difilter per semester dan tahun ajaran.
- Laporan dapat dibuat dan diunduh berulang, dengan pembatasan akses yang sama pada API dan UI.
- Pengujian isolasi tenant, akses siswa, duplikasi, finalisasi, koreksi, dan ekspor lulus.
- Proses pergantian petugas tidak menghilangkan arsip.

## 15. Asumsi dan keputusan yang perlu disepakati

1. **Standar status gizi:** tentukan standar resmi, indeks (mis. IMT/U), parameter usia/jenis kelamin, dan test vector. Dokumen indikator tidak memberi algoritma.
2. **Jenis kelamin untuk kalkulasi:** apakah dan bagaimana atribut ini dikumpulkan, sumber kebenarannya, serta siapa yang boleh melihat/mengubahnya.
3. **Indikator anemia dan tekanan darah:** metode pengukuran, satuan, pilihan nilai, ambang/peringatan, dan apakah membutuhkan catatan.
4. **Alkohol/NAFZA:** apakah tetap satu indikator gabungan atau dipisah; perlakuan privasi dan tampilan kepada siswa/sekolah.
5. **Odontogram:** apakah MVP cukup dengan indikator ya/tidak atau memerlukan diagram gigi terperinci.
6. **Petugas pemeriksa:** apakah harus selalu tenaga kesehatan individu, dapat berupa tim, dan bagaimana pencatatan bila input dilakukan operator berbeda dari pemeriksa.
7. **Finalisasi/publikasi:** apakah Operator Puskesmas dapat langsung memfinalisasi atau diperlukan pemeriksaan oleh operator kedua, dan kapan siswa/sekolah dapat melihat hasil.
8. **Identitas siswa:** aturan bagi siswa tanpa NISN, verifikasi tautan akun, serta proses siswa pindah sekolah/duplikat.
9. **Kebijakan hukum dan operasional:** dasar pemrosesan, pemberitahuan/persetujuan untuk anak, retensi, pemusnahan, wilayah hosting, RPO/RTO, dan prosedur insiden.
10. **Format laporan dan akses sekolah:** detail indikator mana yang boleh dilihat guru/operator dan siapa yang boleh mengunduh data individu.
11. **Target operasional:** volume pengguna/siswa, periode puncak, SLA, kanal dukungan, serta dukungan offline Flutter (tidak termasuk MVP kecuali diminta).

## 16. Rekomendasi tahapan implementasi

1. **Validasi proses dan klinis:** finalisasi indikator, standar gizi, alur persetujuan/finalisasi, privasi, dan format laporan.
2. **Fondasi backend:** autentikasi/otorisasi, master data, periode akademik, enrollment, audit, dan API.
3. **Web Puskesmas/sekolah:** input, finalisasi, histori, dashboard, dan laporan.
4. **Mobile siswa:** autentikasi, rapor individu, dan histori; mobile petugas dapat ditambahkan setelah alur web stabil.
5. **Pilot terbatas:** satu wilayah Puskesmas dan beberapa sekolah dengan data uji/terkontrol, uji keamanan serta validasi pengguna.
6. **Rollout bertahap:** migrasi data yang tervalidasi, pelatihan, monitoring, dukungan, dan evaluasi metrik.
