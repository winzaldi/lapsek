# Desain Fase 1
## Laporan Online Sekolah — Data Kesehatan UKS

**Status:** Draft desain untuk review; belum menjadi keputusan klinis atau kontrak implementasi final.  
**Acuan:** [PRD Laporan Online Sekolah](./PRD-Laporan-Online-Sekolah.md) dan [Fase Pengembangan](./fase%20pengembangan.md).  
**Stack:** Laravel REST API, Next.js web, Flutter mobile.

Fase 1 menghasilkan rancangan alur, antarmuka, model data, akses, API, dan arsitektur. Beberapa keputusan Fase 0 masih terbuka. Rancangan yang bergantung pada keputusan tersebut ditandai **perlu persetujuan** dan tidak boleh diterjemahkan menjadi asumsi klinis atau kebijakan produksi.

## 1. Prinsip desain

1. **Satu sumber data:** Laravel API menjadi sumber kebenaran bagi web dan mobile.
2. **Riwayat tidak ditimpa:** perpindahan sekolah/kelas dan perubahan pemeriksaan mempertahankan histori.
3. **Akses minimum:** setiap permintaan API dibatasi berdasarkan role, penugasan, dan kepemilikan data.
4. **Draf berbeda dari final:** pemeriksaan final hanya berubah melalui alur koreksi yang diaudit.
5. **Status indikator eksplisit:** `belum_diisi`, `tidak_diperiksa`, dan nilai hasil adalah keadaan yang berbeda.
6. **Klinis bukan asumsi software:** algoritma status gizi, ambang pengukuran, dan aturan publikasi menunggu persetujuan pemilik proses/tenaga kesehatan.
7. **Ekspor bersifat privat:** berkas laporan memerlukan otorisasi saat dibuat dan saat diunduh.

## 2. Peta navigasi dan wireframe konseptual

### 2.1 Operator Puskesmas — web

```text
┌──────────────────────────────────────────────────────────────┐
│ LAOS     Puskesmas: [cakupan akun]     Periode: [semester ▼] │
├──────────────┬───────────────────────────────────────────────┤
│ Beranda      │ Ringkasan pemeriksaan                         │
│ Pemeriksaan  │ Sasaran | Sudah diperiksa | Belum | Cakupan   │
│ Sekolah      │                                               │
│ Laporan      │ [Pilih sekolah] [Pilih kelas] [Cari siswa...] │
│ Riwayat      │ Daftar sasaran / pemeriksaan                  │
│ Profil akun  │ Nama | Kelas | Status | Tanggal | Aksi        │
└──────────────┴───────────────────────────────────────────────┘
```

Alur pemeriksaan:

```text
Pilih sekolah/periode → pilih siswa → isi bagian indikator
→ simpan draf → tinjau kelengkapan → finalisasi
→ publikasi sesuai kebijakan → rapor tersedia bagi pemegang hak
```

Navigasi utama: Beranda, Pemeriksaan, Sekolah binaan, Laporan, Riwayat aktivitas, Profil akun. Pengelolaan akun/peran dan konfigurasi global tidak berada di menu Operator Puskesmas; itu merupakan kewenangan Admin Sistem.

### 2.2 Operator Sekolah/Guru — web

```text
┌──────────────────────────────────────────────────────────────┐
│ LAOS     Sekolah: [sekolah akun]    Tahun ajaran: [2026/27 ▼]│
├──────────────┬───────────────────────────────────────────────┤
│ Beranda      │ Siswa: n | Sudah diperiksa: n | Belum: n      │
│ Profil       │                                               │
│ Guru/Operator│ [Kelas ▼] [Status pemeriksaan ▼] [Cari...]    │
│ Kelas        │ Nama siswa | kelas | status | periode | rapor │
│ Siswa        │                                               │
│ Laporan      │ [Unduh rekap]                                 │
└──────────────┴───────────────────────────────────────────────┘
```

Sekolah mengelola profil, kelas, siswa, dan penempatan sesuai izin. Hasil klinis hanya-baca. Perubahan data identitas yang memengaruhi histori harus melalui aturan koreksi/master data dan tercatat.

### 2.3 Siswa — web/mobile

```text
┌───────────────────────────────┐
│ Rapor Kesehatan               │
│ Periode: [Semester 1 2026/27] │
│ Status: Final / Dipublikasikan│
│ Tanggal pemeriksaan           │
│ Ringkasan indikator           │
│ [Lihat detail]                │
│ Riwayat semester sebelumnya   │
└───────────────────────────────┘
```

Siswa hanya melihat profil yang telah diverifikasi dan pemeriksaan miliknya yang telah dipublikasikan. UI tidak menyediakan edit hasil pemeriksaan atau ekspor kumpulan siswa.

### 2.4 Admin Sistem

Menu konseptual: Dasbor Sistem; Puskesmas/sekolah; akun, peran, dan penugasan; tahun ajaran/semester/referensi; audit dan konfigurasi. Akses ke data kesehatan bukan kebutuhan rutin. Bila dukungan memerlukan akses sementara, alasan, masa berlaku, dan aktivitas dicatat.

### 2.5 Keadaan UI wajib

- Loading, kosong, gagal memuat, tidak berwenang, dan sesi kedaluwarsa harus mempunyai tampilan eksplisit.
- Form draf menampilkan indikator wajib/opsional dan membedakan belum diisi dengan “Tidak”.
- Sebelum finalisasi, tampilkan ringkasan kelengkapan dan konfirmasi tindakan.
- Sesudah finalisasi, form terkunci; koreksi menggunakan alur khusus dengan alasan.
- Rapor menampilkan sumber periode, tanggal pemeriksaan, status final/publikasi, dan penanda data belum diperiksa.
- Jangan menampilkan nilai sensitif di toast, URL, log browser, atau notifikasi push.

## 3. Model data konseptual (ERD)

Diagram berikut merupakan rancangan konseptual. Detail tipe data, enum, dan constraint final ditetapkan setelah keputusan Fase 0.

```mermaid
erDiagram
    PUSKESMAS ||--o{ SCHOOL : membina
    PUSKESMAS ||--o{ USER_ASSIGNMENT : memiliki_cakupan
    SCHOOL ||--o{ CLASS : memiliki
    SCHOOL ||--o{ STUDENT_ENROLLMENT : menerima
    ACADEMIC_YEAR ||--|{ SEMESTER : berisi
    ACADEMIC_YEAR ||--o{ CLASS : digunakan
    STUDENT ||--o{ STUDENT_ENROLLMENT : ditempatkan
    CLASS ||--o{ STUDENT_ENROLLMENT : berisi
    USER ||--o{ USER_ROLE : memiliki
    ROLE ||--o{ USER_ROLE : diberikan
    USER ||--o{ USER_ASSIGNMENT : ditugaskan
    STUDENT ||--o| STUDENT_ACCOUNT : memiliki
    USER ||--o| STUDENT_ACCOUNT : tertaut
    STUDENT ||--o{ EXAMINATION : diperiksa
    SCHOOL ||--o{ EXAMINATION : konteks_sekolah
    CLASS ||--o{ EXAMINATION : konteks_kelas
    SEMESTER ||--o{ EXAMINATION : periode
    USER ||--o{ EXAMINATION : pemeriksa
    EXAMINATION ||--o| EXAM_GENERAL : data_umum
    EXAMINATION ||--o| EXAM_LIFESTYLE : gaya_hidup
    EXAMINATION ||--o| EXAM_NUTRITION : gizi
    EXAMINATION ||--o| EXAM_EYES : mata
    EXAMINATION ||--o| EXAM_EARS : telinga
    EXAMINATION ||--o| EXAM_ORAL : gigi_mulut
    EXAMINATION ||--o{ EXAM_REVISION : versi
    USER ||--o{ AUDIT_LOG : melakukan
    USER ||--o{ REPORT_EXPORT : meminta
```

### Aturan relasi dan integritas

- `student_enrollments` menjadi riwayat penempatan; jangan mengubah enrollment lama untuk menimpa sekolah/kelas sebelumnya.
- `examinations` menyimpan `student_id`, `school_id`, `class_id`, `semester_id`, `examiner_user_id`, `entered_by_user_id`, waktu pemeriksaan, status, versi, dan snapshot konteks yang diperlukan.
- Pemeriksaan aktif dibatasi unik pada `(student_id, semester_id)` kecuali kebijakan pemeriksaan ulang disahkan.
- Setiap tabel indikator bergantung pada pemeriksaan; setiap indikator yang boleh kosong harus dapat membedakan belum diperiksa dari jawaban negatif.
- Pemeriksaan final tidak dihapus fisik. Koreksi menghasilkan versi/jejak lama-baru dan alasan.
- Akun pengguna dipisah dari profil siswa; hubungan akun-siswa harus diverifikasi dan dicatat.
- Data sensitif seminimal mungkin disalin ke audit log. Gunakan actor, aksi, resource, waktu, request ID, alasan, dan metadata terbatas.
- Master referensi dinonaktifkan/diarsipkan, bukan dihapus jika sudah direferensikan.

## 4. Matriks akses awal

`Baca` tetap dibatasi pada scope penugasan; `Kelola` tidak otomatis memberi akses ke semua data kesehatan.

| Resource/aksi | Admin Sistem | Operator Puskesmas | Operator Sekolah/Guru | Siswa |
|---|---|---|---|---|
| Konfigurasi global, role, dan penugasan | Kelola | Tidak | Tidak | Tidak |
| Data sekolah/Puskesmas | Kelola sesuai kebutuhan administratif; akses kesehatan dibatasi | Baca sekolah binaan; operasional sesuai izin | Baca/kelola sekolah sendiri sesuai izin | Tidak |
| Kelas, siswa, enrollment | Kelola administratif | Baca siswa sasaran; perubahan hanya jika ditugaskan | Kelola data sekolah sendiri sesuai izin | Lihat profil sendiri yang diizinkan |
| Buat/ubah draf pemeriksaan | Tidak untuk penggunaan rutin; akses dukungan harus sementara dan diaudit | Kelola dalam cakupan | Tidak | Tidak |
| Finalisasi/publikasi/koreksi | Tidak rutin; kontrol khusus bila dibutuhkan | Sesuai kebijakan yang disahkan | Tidak | Tidak |
| Rapor individual | Akses darurat/dukungan tercatat, jika disetujui | Baca dalam cakupan tugas | Baca siswa di sekolah sendiri sesuai izin | Baca milik sendiri yang dipublikasikan |
| Rekap/ekspor detail | Sesuai kebutuhan administratif minimum dan audit | Cakupan Puskesmas sesuai izin | Sekolah sendiri sesuai izin | Tidak |
| Audit akses dan perubahan | Kelola audit; data kesehatan tetap diminimalkan | Riwayat aktivitas dalam cakupannya | Aktivitas sekolah yang diizinkan | Tidak |

### Pemeriksaan otorisasi server-side

1. Periksa status akun dan role aktif.
2. Periksa scope penugasan aktif untuk Puskesmas/sekolah dan masa berlakunya.
3. Periksa hubungan objek yang diminta dengan scope tersebut.
4. Untuk siswa, pastikan `student_account` terverifikasi dan resource cocok dengan `student_id` akun.
5. Periksa status publikasi sebelum memberikan hasil ke sekolah/siswa.
6. Terapkan otorisasi yang sama pada detail, histori, pencarian, agregat, dan ekspor.
7. Catat akses/penolakan untuk data sensitif tanpa memasukkan isi hasil pemeriksaan ke log.

## 5. Kontrak API awal

### Konvensi

- Prefix: `/api/v1`.
- JSON UTF-8; timestamp ISO 8601 dengan zona waktu eksplisit; tanggal pemeriksaan format `YYYY-MM-DD`.
- Autentikasi bearer token/sesi terlindungi, dipilih pada desain teknis Fase 2.
- Pagination berbasis `page` dan `per_page` dengan batas maksimum server.
- Filter/sort hanya menerima daftar field yang diizinkan; semua endpoint resource menjalankan policy scope.
- Semua operasi mutasi memvalidasi payload di backend; frontend bukan sumber kebenaran validasi.
- Gunakan idempotency key untuk operasi yang dapat diulang dan berisiko membuat duplikasi, sesuai rancangan implementasi.

### Resource dan endpoint rancangan

| Method & path | Fungsi | Role/scope |
|---|---|---|
| `POST /auth/login` | Login | Semua peran yang aktif |
| `POST /auth/logout` | Logout/revoke sesi | Pengguna login |
| `GET /me` | Profil dan kapabilitas akun | Pengguna login |
| `GET /schools` | Daftar sekolah yang boleh diakses | Scope berdasarkan role |
| `GET/POST /students` | Cari/buat profil siswa | Admin Sistem atau Operator Sekolah sesuai scope |
| `GET/PATCH /students/{id}` | Detail/perubahan data siswa | Scope sekolah; siswa sendiri hanya data profil tertentu |
| `POST /students/{id}/enrollments` | Membuat penempatan tahun ajaran | Operator Sekolah/Admin Sistem sesuai kebijakan |
| `GET /academic-years` / `GET /semesters` | Daftar periode aktif/arsip | Scope akun |
| `GET/POST /examinations` | Daftar sasaran atau buat draf | Operator Puskesmas dalam scope |
| `GET/PATCH /examinations/{id}` | Baca/ubah draf | Operator Puskesmas dalam scope; sekolah hanya status/laporan terbatas |
| `POST /examinations/{id}/finalize` | Finalisasi | Operator Puskesmas dengan kapabilitas yang disahkan |
| `POST /examinations/{id}/publish` | Publikasi rapor | Kapabilitas sesuai keputusan Fase 0 |
| `POST /examinations/{id}/corrections` | Ajukan/lakukan koreksi beralasan | Operator berwenang sesuai alur persetujuan |
| `GET /students/{id}/health-history` | Histori individu | Puskesmas/sekolah sesuai scope; siswa sendiri |
| `GET /reports/schools/{id}` | Rekap sekolah | Sekolah sendiri/Puskesmas binaan sesuai scope |
| `GET /reports/puskesmas/{id}` | Rekap cakupan Puskesmas | Operator Puskesmas dalam scope |
| `POST /report-exports` | Membuat ekspor | Role dan scope sesuai data yang diminta |
| `GET /report-exports/{id}` | Status pekerjaan ekspor | Peminta yang berwenang |
| `GET /report-exports/{id}/download` | Unduh ekspor privat | Peminta/role yang berhak; tautan kedaluwarsa |
| `GET /audit-logs` | Audit terfilter | Admin Sistem atau pemilik scope dengan izin |

Endpoint di atas adalah rancangan untuk ditinjau, belum merupakan OpenAPI final. Route mutasi finalisasi/publikasi/koreksi harus mengikuti keputusan proses yang disahkan.

### Bentuk respons standar (konseptual)

```json
{
  "data": {},
  "meta": {
    "request_id": "uuid",
    "pagination": {
      "page": 1,
      "per_page": 25,
      "total": 0
    }
  }
}
```

Respons error menggunakan status HTTP yang sesuai dan format konsisten, misalnya:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data belum memenuhi persyaratan.",
    "details": {
      "field": ["Alasan kesalahan yang aman ditampilkan."]
    },
    "request_id": "uuid"
  }
}
```

Jangan menyertakan stack trace, SQL, token, atau isi rekam kesehatan yang tidak diperlukan dalam error.

### Transisi status pemeriksaan

```text
draft ──finalize──> finalized ──publish──> published
  │                    │
  └──discard*          └──correction request/approval──> revised version
```

`discard` hanya untuk draf dan harus tercatat. Nama status, siapa yang boleh menjalankan transisi, dan apakah finalisasi sekaligus publikasi masih perlu disahkan. Jangan mengizinkan perubahan langsung pada record final tanpa riwayat versi.

## 6. Arsitektur aplikasi

```text
┌────────────────────┐       ┌─────────────────────┐
│ Next.js Web        │       │ Flutter Mobile      │
│ Admin/Puskesmas/   │       │ Rapor siswa         │
│ Sekolah            │       └──────────┬──────────┘
└─────────┬──────────┘                  │ HTTPS
          └──────────────┬─────────────┘
                         ▼
             ┌────────────────────────┐
             │ Laravel REST API       │
             │ Auth + Policy + Domain │
             │ Validation + Audit     │
             └───────┬────────┬───────┘
                     │        │
             ┌───────▼───┐  ┌─▼────────────────┐
             │ Relational│  │ Queue + Private  │
             │ Database  │  │ Object Storage   │
             └───────────┘  └──────────────────┘
```

### Batas tanggung jawab

- **Next.js:** antarmuka web, navigasi, validasi kenyamanan pengguna; tidak memutuskan otorisasi final.
- **Flutter:** antarmuka mobile siswa, menyimpan token secara aman, tidak menyimpan data sensitif lebih dari kebutuhan.
- **Laravel:** autentikasi/otorisasi, aturan bisnis, validasi, kalkulasi, audit, laporan, dan kontrak API.
- **Database relasional:** konsistensi transaksi, foreign key, unique constraint, dan histori.
- **Queue/private storage:** pembuatan ekspor besar; file tidak dapat diakses publik dan diperiksa otorisasinya saat unduh.

### Komponen logis Laravel

`Auth/Identity` · `AccessControl` · `ReferenceData` · `School/Enrollment` · `Examination` · `Reporting/Export` · `Audit`.

Pisahkan validasi request, policy otorisasi, layanan/domain, akses repository/query, dan serializer API. Finalisasi harus transaksional agar status, timestamp, versi, dan audit tidak terpisah sebagian.

## 7. Data, impor, ekspor, dan operasi

### Impor data siswa

1. Operator memilih sekolah dan tahun ajaran sesuai scope.
2. Mengunggah template yang disediakan sistem; server membatasi tipe, ukuran, dan jumlah baris.
3. Sistem melakukan preview dan validasi NISN, kolom wajib, kelas, duplikasi, serta format.
4. Operator meninjau baris valid/tidak valid dan mengonfirmasi proses.
5. Sistem memproses secara transaksional per batch dengan idempotency/protection against duplicate; hasil impor dan kesalahan dapat diunduh.
6. Impor tidak menghapus enrollment atau pemeriksaan lama. Perubahan penempatan menjadi histori enrollment baru.

### Ekspor laporan

1. API memvalidasi role, scope, filter, periode, dan jenis laporan saat permintaan diterima.
2. Ekspor besar masuk antrean; status dapat dipantau oleh peminta.
3. File disimpan privat, memiliki masa berlaku, dan otorisasi dicek kembali saat unduh.
4. Log menyimpan peminta, waktu, scope, format, dan hasil; isi kesehatan tidak dimasukkan ke log.
5. CSV/XLSX melakukan sanitasi formula; laporan menyertakan filter dan waktu snapshot.

### Rancangan audit dan pemulihan

- Audit untuk autentikasi penting, perubahan role/penugasan, perubahan master, perubahan pemeriksaan, finalisasi, publikasi, koreksi, akses sensitif, dan ekspor.
- Hindari log yang mencatat seluruh request body kesehatan; tetapkan kontrol akses serta retensi log.
- Backup database dan file terenkripsi, dengan prosedur restore teruji. Nilai RPO/RTO dan retensi menunggu persetujuan pemilik layanan.

## 8. Rancangan pengujian

| Lapisan | Cakupan desain |
|---|---|
| Unit/domain | Status pemeriksaan, transisi finalisasi, validasi, rumus, dan batas nilai |
| Database/integrasi | Foreign key, unique siswa-semester, enrollment historis, transaksi finalisasi, audit |
| API/otorisasi | Scope Puskesmas/sekolah, akses rapor siswa sendiri, IDOR, ekspor, token kedaluwarsa |
| Web end-to-end | Alur pemeriksaan, master sekolah, laporan, error, dan unduh ulang |
| Mobile end-to-end | Tautan profil, rapor terpublikasi, sesi berakhir, akun dinonaktifkan |
| Keamanan | Privilege escalation, brute force, input berbahaya, file ekspor, kebocoran log |
| Laporan | Rekonsiliasi agregat dengan data final, periode, filter, dan sanitasi spreadsheet |
| Operasional | Backup/restore, monitoring, rollback, pergantian operator dan pencabutan akses |

Semua test data selama pengembangan harus sintetis. Uji status gizi menggunakan test vector yang disahkan tenaga kesehatan; jangan membuat nilai ambang sendiri.

## 9. Risiko desain dan mitigasi

| Risiko desain | Mitigasi |
|---|---|
| Aturan status gizi belum diputuskan | Blok kalkulasi produksi sampai standar, variabel, dan test vector disahkan. |
| Operator sekolah mendapat akses terlalu luas | Matriks permission per resource/field dan policy backend; uji lintas sekolah. |
| Status “Tidak” tertukar dengan belum diperiksa | Enum/status pengisian eksplisit; nullable tidak disamakan dengan false. |
| Perubahan siswa memutus histori | Enrollment append-only dan snapshot konteks pemeriksaan. |
| File ekspor bocor | Private storage, otorisasi saat download, link singkat, expiry, dan audit. |
| Rapor siswa tertaut ke akun yang keliru | Verifikasi hubungan akun-siswa oleh proses yang disahkan; jangan hanya cocok nama. |
| Penyesuaian kebutuhan mengubah API berulang | Versioning `/api/v1`, kontrak OpenAPI, dan persetujuan perubahan kompatibilitas. |

## 10. Keputusan yang menahan desain final

Sebelum kontrak API, model indikator, dan wireframe pemeriksaan dibekukan, pemilik proses perlu menyetujui:

1. Standar status gizi dan kebutuhan jenis kelamin/umur untuk kalkulasi.
2. Bentuk input tekanan darah dan anemia.
3. Indikator gabungan alkohol/NAFZA tetap satu atau dipecah.
4. Kelengkapan odontogram pada MVP.
5. Identitas dan pencatatan petugas pemeriksa versus operator input.
6. Siapa yang finalisasi, siapa yang publikasi, dan apakah perlu pemeriksa kedua.
7. Field kesehatan yang dapat dilihat sekolah dan siswa.
8. Verifikasi akun siswa, dasar pemrosesan data anak, retensi, dan penghapusan.
9. Target jumlah pengguna/data, SLA, dan kebutuhan offline.

**Gerbang Phase 1:** desain UI, ERD, matriks akses, API, arsitektur, threat model, strategi impor, serta rencana uji ditinjau perwakilan backend, web, mobile, QA, keamanan, Puskesmas, sekolah, dan pemilik proses. Perubahan klinis/kebijakan yang belum disetujui tetap berstatus keputusan terbuka.
