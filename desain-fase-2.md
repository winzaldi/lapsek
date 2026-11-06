# Desain Fase 2
## Fondasi Teknis dan Lingkungan — Laporan Online Sekolah

**Status:** Fondasi lokal telah diterapkan; deployment hosted staging dan beberapa keputusan operasional masih menunggu persetujuan.  
**Acuan:** [PRD Laporan Online Sekolah](./PRD-Laporan-Online-Sekolah.md), [Fase Pengembangan](./fase%20pengembangan.md), [Desain Fase 0](./desain-fase-0.md), dan [Desain Fase 1](./desain-fase-1.md).  
**Stack:** Laravel REST API, Next.js web, Flutter mobile, database relasional.  
**Batas fase:** menyiapkan fondasi dan deployment skeleton ke lingkungan nonproduksi; belum membangun fitur master data maupun pemeriksaan.

### Status penerapan (3 November 2026)

- Monorepo lokal sudah diinisialisasi pada branch `main`; aplikasi Laravel, Next.js, dan Flutter beserta quality gates dasar telah dibuat.
- Konfigurasi MySQL 8.4 Docker Compose dan workflow GitHub Actions tersedia. Docker daemon tidak berjalan saat verifikasi, sehingga container MySQL belum dijalankan.
- API memakai SQLite in-memory untuk tes CI; `pdo_mysql` tersedia pada toolchain PHP lokal.
- Hosted staging dan workflow deploy belum dibuat karena provider/region serta repository remote belum ditentukan.
- Validasi lokal: tes Laravel/Pint, lint/typecheck/build Next.js, Flutter analyze/test/build APK, dependency audit, dan validasi sintaks Compose.

## 1. Tujuan dan hasil Fase 2

Fase 2 menyiapkan fondasi yang konsisten agar pekerjaan backend, web, dan mobile dapat dikembangkan, diuji, dan dirilis dengan aman.

Hasil yang diharapkan:

- Struktur repository dan aplikasi awal disepakati serta dapat dijalankan oleh tim.
- Lingkungan development, test, dan staging dipisahkan.
- Build, lint, tes dasar, dan pemeriksaan dependency berjalan otomatis di CI.
- Laravel memiliki kerangka API versioned, respons error konsisten, logging terstruktur, dan request ID.
- Aplikasi skeleton dapat dideploy ulang ke staging melalui proses terdokumentasi.
- Secret tidak berada di source code; data kesehatan nyata tidak dipakai di development/test.
- Keputusan platform/infrastruktur yang belum diketahui tercatat sebagai ADR, bukan diputuskan diam-diam.

## 2. Batas ruang lingkup

### Termasuk

- Repository, struktur folder, branch/pull request workflow, dan konvensi konfigurasi.
- Skeleton Laravel, Next.js, dan Flutter beserta command build/test dasar.
- Konfigurasi environment, database kosong, migration baseline, dan seed sintetis.
- CI untuk format/lint, build, tes dasar, dan pemeriksaan dependency/secrets.
- Deployment staging dengan HTTPS, health check, log, dan monitoring dasar.
- Secret/config management, backup awal, serta dokumentasi setup dan deployment.

### Tidak termasuk

- Login dan role/permission lengkap, master data, pemeriksaan, laporan, atau aturan klinis.
- Migrasi data kesehatan produksi.
- Penggunaan data siswa nyata atau kredensial produksi dalam pengujian.
- Infrastruktur produksi atau target SLA final sebelum pemilik layanan menyetujuinya.
- Pemilihan cloud/vendor atau teknologi database tertentu tanpa keputusan pemilik sistem.

## 3. Keputusan arsitektur yang perlu dicatat

| Keputusan | Rekomendasi awal | Status/pihak yang menyetujui |
|---|---|---|
| Bentuk repository | Monorepo `apps/api`, `apps/web`, `apps/mobile`, `docs/`, dan konfigurasi CI bersama agar kontrak lintas klien mudah ditinjau. Pisahkan repo hanya bila kebijakan organisasi mengharuskannya. | Perlu persetujuan tim teknis |
| Database | Pilih satu database relasional yang didukung Laravel dan penyedia hosting; prioritaskan transaksi, foreign key, backup, dan dukungan operasional. | Perlu keputusan TI |
| Autentikasi | Tentukan strategi Laravel API dan cara token/sesi web/mobile sebelum implementasi fitur; Fase 2 hanya menyediakan titik integrasi skeleton. | Perlu tinjauan keamanan |
| Hosting/deployment | Gunakan staging terpisah dari produksi; provider dan region mengikuti persetujuan pemilik layanan/data. | Perlu keputusan TI/data |
| Penyimpanan ekspor | Siapkan interface/storage private atau emulator lokal; belum menyimpan file kesehatan nyata. | Perlu tinjauan keamanan |
| Queue/cache | Mulai dengan adapter yang sesuai untuk local dan staging; pilih service produksi berdasarkan beban dan operasi. | Perlu keputusan TI |
| Versi runtime | Tetapkan versi PHP, Laravel, Node.js, package manager, Flutter/Dart, dan database yang masih didukung, lalu pin pada konfigurasi tim. | Dicatat sebagai ADR sebelum skeleton dibuat |
| Kontrak API | Prefix `/api/v1`; dokumen OpenAPI disimpan dan direview bersama perubahan endpoint. | Mengikuti Desain Fase 1 |

Setiap keputusan harus mempunyai ID ADR, konteks, opsi, keputusan, konsekuensi, pemilik, dan tanggal. Jangan memasukkan kredensial atau data sensitif dalam ADR.

## 4. Struktur repository konseptual

```text
laos/
├── apps/
│   ├── api/                 # Laravel REST API
│   ├── web/                 # Next.js
│   └── mobile/              # Flutter
├── contracts/
│   └── openapi/             # Kontrak API versioned
├── docs/
│   ├── adr/
│   ├── setup/
│   └── operations/
├── infrastructure/
│   ├── ci/
│   └── deployment/          # Template/config non-secret
├── .editorconfig
├── .gitignore
└── README.md
```

Struktur ini konseptual. Bila setiap aplikasi berada di repository terpisah, kontrak API, ADR, versi toolchain, dan kebijakan CI tetap harus mempunyai sumber yang jelas.

### Aturan repository

- Branch utama dilindungi; perubahan masuk melalui pull request dengan review.
- CI wajib lulus sebelum merge untuk semua aplikasi yang terdampak.
- Gunakan lockfile dan versi toolchain yang dipin; hindari rentang dependency yang tidak terkendali.
- `.env`, private key, token, dump database, file ekspor, dan data siswa tidak boleh masuk repository.
- Sediakan `.env.example` berisi nama variabel dan nilai placeholder saja.
- Fixture/seed harus berupa data sintetis yang jelas bukan identitas nyata.
- Hindari menyimpan output build, cache, atau artefak aplikasi ke repository kecuali diperlukan oleh toolchain.

## 5. Konfigurasi lingkungan

| Lingkungan | Penggunaan | Data yang diizinkan | Akses |
|---|---|---|---|
| Local development | Pengembangan tiap pengembang | Data sintetis saja | Akun lokal; tidak memakai secret layanan produksi |
| CI/test | Tes otomatis dan integrasi | Factory/fixture sintetis | Secret CI minimal dan berumur terbatas |
| Staging | Uji integrasi, UAT, dan deployment rehearsal | Data sintetis atau data yang secara formal dianonimkan dan diotorisasi | Pengguna uji; akses dibatasi dan dicatat |
| Production | Operasional sebenarnya | Data sesuai kebijakan yang disetujui | Akses least privilege, monitoring, audit, dan prosedur insiden |

### Variabel konfigurasi konseptual

- Environment aplikasi dan URL publik.
- Database host/name/user/password dan SSL mode sesuai platform.
- Key aplikasi dan konfigurasi token/session.
- CORS allowlist, trusted hosts, cookie/security settings.
- Queue/cache connection.
- Private object storage bucket/container dan kredensial runtime.
- Mail/SMS provider jika kelak disetujui.
- Log level, retention, dan monitoring/telemetry endpoint.
- Backup schedule, retensi, serta key reference.

Nilai rahasia hanya diberikan melalui secret store/environment runtime. `.env.example` tidak berisi nilai aktif. Akses ke secret dibatasi per environment dan dicatat. Rotasi credential harus terdokumentasi.

## 6. Fondasi aplikasi

### 6.1 Laravel API

Siapkan skeleton API dengan:

- API prefix `/api/v1` dan endpoint `GET /api/v1/health` yang hanya menampilkan status layanan minimum, tidak membuka versi rahasia atau detail infrastruktur.
- Format respons sukses/error konsisten sesuai rancangan Fase 1.
- Middleware request/correlation ID, validasi input, rate limit baseline, dan security headers sesuai deployment.
- Logging terstruktur dengan redaksi authorization header, cookie, kredensial, dan data kesehatan.
- Pemisahan konfigurasi per environment dan validasi konfigurasi wajib saat aplikasi mulai.
- Koneksi database kosong, migration baseline, dan seed sintetis yang aman dijalankan pada local/test.
- Storage lokal untuk development; adapter staging menggunakan penyimpanan privat jika service tersedia.
- Queue skeleton bila diperlukan untuk membuktikan konfigurasi worker; belum ada proses ekspor klinis.

Fase ini tidak mengimplementasikan autentikasi/otorisasi domain lengkap. Endpoint placeholder tidak boleh memberi kesan seolah-olah data telah diamankan oleh role yang belum dibuat.

### 6.2 Next.js web

- Halaman skeleton, routing/layout dasar, halaman status aplikasi, dan konfigurasi API base URL dari environment.
- Tidak menyimpan secret server-side pada bundle browser.
- Error API ditangani tanpa menampilkan stack trace, token, atau isi respons sensitif.
- Lint, typecheck, unit/build test dasar dimasukkan ke CI.
- Belum menampilkan data siswa/pemeriksaan atau menganggap menu sebagai kontrol akses.

### 6.3 Flutter mobile

- Aplikasi skeleton dengan konfigurasi base URL per environment dan halaman status/koneksi dasar.
- Siapkan lint/analyzer, test runner, dan build check untuk target yang disepakati.
- Siapkan abstraksi penyimpanan token yang aman untuk fase autentikasi berikutnya; jangan menaruh token/data sensitif di log atau plain preferences.
- Tidak mengakses data kesehatan nyata pada Fase 2.
- Target platform serta versi minimum OS harus disetujui sebelum pipeline distribusi ditetapkan.

## 7. CI dan quality gates

### Alur pull request

```text
Pull request
  ├─ cek secret dan file terlarang
  ├─ format/lint/typecheck per aplikasi terdampak
  ├─ tes unit/smoke yang tersedia
  ├─ build API, web, dan mobile sesuai runner
  ├─ pemeriksaan dependency dan konfigurasi
  └─ hasil wajib lulus sebelum merge
```

### Job minimum yang direncanakan

| Job | API Laravel | Next.js | Flutter |
|---|---|---|---|
| Toolchain | Versi PHP/Composer dipin | Versi Node/package manager dipin | Versi Flutter/Dart dipin |
| Dependency | Install dari lockfile | Install dari lockfile | Resolve dari lockfile |
| Quality | Formatter/lint yang disepakati | Lint + typecheck | `dart format --set-exit-if-changed` + analyzer |
| Test | Tes smoke/unit skeleton | Tes unit/smoke skeleton | Unit/widget smoke skeleton |
| Build | Boot/autoload/build artifact bila berlaku | Production build | Build target CI yang disepakati |
| Security hygiene | Secret scan, dependency audit, static checks yang tersedia | Sama | Sama |

### Ketentuan pipeline

- CI tidak menggunakan database atau secret production.
- Dependency audit memberi hasil yang dapat ditindaklanjuti; exception harus punya alasan, pemilik, dan masa review.
- Build/test yang gagal menghentikan merge; jangan mengubah pipeline agar menutupi kegagalan.
- Artefak deployment dibuat dari commit yang sama dan dapat ditelusuri ke commit/build ID.
- Runner dan artefak tidak boleh mencetak environment variable sensitif.
- Cache dependency tidak boleh mengandung file konfigurasi rahasia.

## 8. Deployment staging

### Komponen minimal

- Runtime aplikasi untuk API dan web; aplikasi mobile dibangun/disalurkan melalui jalur internal yang sesuai.
- Database staging dengan akses jaringan terbatas dan kredensial khusus staging.
- HTTPS, DNS/URL staging, dan konfigurasi CORS/trusted hosts terbatas.
- Storage staging privat dan queue worker bila komponen tersebut dipakai.
- Health check, log terstruktur, error monitoring, dan metrik dasar.
- Backup staging bila disyaratkan kebijakan, tanpa menjadikannya pengganti backup produksi.

### Alur deployment

1. Pull request lulus seluruh quality gate dan di-merge.
2. CI membangun artefak dari commit tertentu dan menyimpan metadata commit/build.
3. Deployment staging menggunakan konfigurasi dan secret staging; tidak menyalin `.env` lokal.
4. Jalankan pemeriksaan health, smoke test, koneksi database, dan verifikasi log.
5. Catat versi yang terpasang, waktu deployment, hasil pemeriksaan, serta prosedur rollback.
6. Untuk perubahan schema kelak, gunakan strategi migrasi yang direview; jangan menghapus/menimpa data melalui deployment otomatis.

### Strategi rollback

- Simpan artefak build sebelumnya yang masih valid.
- Rollback aplikasi dilakukan ke artefak yang diketahui baik.
- Perubahan database harus kompatibel dengan versi aplikasi sebelumnya atau mempunyai rencana pemulihan khusus.
- Uji rollback skeleton saat staging tersedia; rollback tidak boleh mengandalkan penghapusan data.

## 9. Logging, monitoring, backup, dan dukungan

- Log aplikasi memuat timestamp, severity, service, environment, request ID, dan kode error.
- Redaksi token, password, cookie, data kesehatan, serta isi request/response yang sensitif.
- Monitoring dasar meliputi ketersediaan endpoint health, error rate, waktu respons, status worker, kapasitas database, dan kegagalan deployment.
- Alert mempunyai pemilik dan langkah tindak lanjut; jangan mengirim detail kesehatan melalui kanal alert.
- Backup dan restore perlu diuji dengan data sintetis di staging sebelum proses produksi ditetapkan.
- Nilai RPO/RTO, retensi, lokasi backup, key management, dan jadwal uji pemulihan menunggu persetujuan pemilik layanan/data.
- Siapkan runbook singkat untuk deployment gagal, konfigurasi hilang, database tidak tersedia, dan kebocoran secret.

## 10. Checklist implementasi

### Sebelum mulai

- [ ] Keputusan Fase 0 yang memengaruhi stack/deployment telah dicatat.
- [ ] Desain Fase 1 dan kontrak awal direview oleh tim teknis.
- [ ] Pemilik repository, pipeline, staging, dan secret management ditetapkan.
- [ ] Runtime dan versi toolchain dipilih dan dicatat pada ADR.
- [ ] Provider/region dan perlakuan data staging disetujui.

### Pembangunan skeleton

- [ ] Struktur repository disepakati dan README setup tersedia.
- [ ] `.gitignore` dan secret scanning mencegah file/secret sensitif masuk.
- [ ] Laravel, Next.js, dan Flutter skeleton dapat dijalankan dengan langkah terdokumentasi.
- [ ] `.env.example` hanya berisi placeholder; validasi konfigurasi memberi error jelas jika variabel wajib hilang.
- [ ] Seed dan fixture menggunakan data sintetis.
- [ ] Health check tidak membocorkan detail internal.
- [ ] Logging terstruktur dan redaksi informasi sensitif diuji.

### CI dan staging

- [ ] Lint/format, typecheck/analyzer, tes smoke, dan build berjalan otomatis.
- [ ] Dependency dan secret scan berjalan pada pull request.
- [ ] Merge ditolak bila quality gate wajib gagal.
- [ ] Deployment staging menggunakan secret dan database khusus staging.
- [ ] HTTPS, CORS/trusted hosts, storage privat, dan akses jaringan dikonfigurasi.
- [ ] Smoke test pascadeployment dan identifikasi versi build berhasil.
- [ ] Rollback artefak aplikasi telah diuji atau disimulasikan.
- [ ] Runbook deployment, insiden konfigurasi, dan kontak pemilik tersedia.

## 11. Kriteria keluar Fase 2

Fase 2 selesai jika semua hal berikut terpenuhi:

- Build lokal dan CI untuk aplikasi yang termasuk scope berhasil dengan versi toolchain yang dicatat.
- CI menjalankan quality gate dan tidak menggunakan data/secret produksi.
- Skeleton API dan web dapat di-deploy ulang ke staging secara terdokumentasi.
- Health check, logging, redaksi, monitoring, dan smoke test staging bekerja.
- Konfigurasi dan secret terpisah antar lingkungan; tidak ada secret di repository/build browser.
- Tidak ada data kesehatan nyata pada development, CI, atau staging kecuali melalui proses formal yang disetujui.
- Backup/restore awal dan rollback telah diuji atau ada pengecualian tertulis beserta pemilik dan tenggat.
- Pemilik teknis menerima ADR dan dokumentasi setup/operasional.

## 12. Handoff ke Fase 3

Setelah gerbang Fase 2 disetujui, tim backend dapat memulai:

- autentikasi dan pengelolaan sesi;
- role dan permission Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, serta Siswa;
- scope Puskesmas/sekolah dan policy server-side;
- master data, periode akademik, kelas, siswa, dan enrollment historis;
- audit perubahan master serta seed data sintetis.

Fase 3 tetap harus mengikuti keputusan Fase 0 dan kontrak arsitektur Fase 1. Jika keputusan klinis masih terbuka, jangan membangun kalkulasi klinis atau memakai data nyata sebagai jalan pintas.

## 13. Keputusan yang perlu disetujui sebelum implementasi

| Keputusan | Pemilik | Status |
|---|---|---|
| Monorepo atau repository terpisah | Pemilik teknis | Belum disetujui |
| Versi PHP/Laravel, Node.js/Next.js, Flutter/Dart | Pemilik teknis | Belum disetujui |
| Database relasional dan layanan hosting | TI/pemilik layanan | Belum disetujui |
| Strategi autentikasi/token untuk web dan mobile | Pemilik teknis/keamanan | Belum disetujui |
| CI provider, runner, dan dependency scanning | Pemilik teknis/keamanan | Belum disetujui |
| Staging, region, dan aturan penggunaan data | Pemilik layanan/data | Belum disetujui |
| Object storage, queue, cache, dan mail provider | TI/pemilik layanan | Belum disetujui |
| Target RPO/RTO, retensi backup, dan SLA | Pemilik layanan/data | Belum disetujui |
| Platform dan versi minimum Android/iOS | Pemilik produk/mobile | Belum disetujui |
