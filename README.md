# LAOS — Laporan Online Sekolah

Monorepo aplikasi Laporan Online Sekolah:

- `apps/api`: Laravel REST API
- `apps/web`: Next.js
- `apps/mobile`: Flutter
- `docs/`: PRD dan rancangan fase

Backend Fase 3 dan 4 menyediakan autentikasi API, role/scope, master data sekolah/siswa, serta alur pemeriksaan dan arsip. Web Fase 5 menyediakan workspace Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, dan Siswa. Operator Sekolah dapat mengelola profil siswa, kelas, dan enrollment; siswa dapat melihat histori pemeriksaan yang telah dipublikasikan dan tertaut ke akunnya. Implementasi Fase 6 menyediakan rekap tahunan/semester, grafik cakupan, cetak laporan, serta ekspor CSV/XLSX/PDF melalui antrean dengan penyimpanan privat 7 hari dan riwayat unduhan ber-scope. Worker antrean dan scheduler harus aktif pada deployment; kebijakan ambang privasi dan retensi perlu disetujui sebelum produksi.

Mobile Flutter Fase 7 menyediakan login siswa, sesi/token aman, verifikasi tautan siswa, rapor dan histori hasil publikasi dengan filter semester/tahun ajaran, serta logout dan pemulihan sesi. Lihat [Panduan Mobile Fase 7](./docs/panduan-mobile-fase-7.md); build/signing iOS dan distribusi produksi memerlukan macOS/Xcode serta konfigurasi organisasi.

Fase 8 menyediakan regression/security tests otomatis dan runbook QA/UAT/pilot. Uji lapangan, beban produksi, dan pemulihan backup perlu dijalankan serta ditandatangani pemilik lingkungan sebelum keputusan go-live. Lihat [Runbook QA dan Pilot Fase 8](./docs/qa-pilot-fase-8.md).

Fase 9 menyediakan perintah pemeriksaan konfigurasi produksi Laravel, prosedur rollout bertahap, pengelolaan akun/operator, monitoring, backup/restore, insiden, dan rollback. Target WHM cPanel 138.0.19 dengan PHP 8.4, MySQL 8.*, dan Node.js 22 telah dipilih; domain, region, worker queue, SLA, retensi, serta RPO/RTO belum ditetapkan sehingga deployment produksi belum dilakukan. Lihat [Runbook Operasi Produksi Fase 9](./docs/operasi-produksi-fase-9.md).

## Prasyarat

- PHP 8.4 dan Composer 2
- Node.js 22 atau 24 dan npm (CI memvalidasi keduanya)
- Flutter 3.41.4 stable serta Android SDK untuk membangun APK
- Docker Desktop hanya diperlukan untuk MySQL lokal melalui Compose

Versi di atas adalah baseline proyek dan dapat diperbarui melalui keputusan teknis yang direview.

## Setup lokal

### 1. Laravel API

```powershell
Set-Location apps/api
composer install
if (-not (Test-Path .env)) {
  Copy-Item .env.example .env
  php artisan key:generate
}
php artisan migrate --seed
php artisan laos:bootstrap-admin
php artisan serve
```

Perintah bootstrap meminta nama, email, dan kata sandi secara interaktif; perintah hanya berhasil bila Admin Sistem belum ada. Jangan mengisi akun produksi dengan data contoh. API lokal berjalan pada `http://127.0.0.1:8000`. Health check: `GET /api/v1/health`.

Reset kata sandi memerlukan konfigurasi mailer yang dapat mengirim email. Token API berlaku 8 jam secara default (`SANCTUM_TOKEN_EXPIRATION`) dan dicabut saat logout, reset kata sandi, atau akun dinonaktifkan. Terapkan seluruh migrasi termasuk tabel token dan jalankan `php artisan db:seed` setelah memperbarui daftar role/permission.

### 2. Next.js web

Pada terminal lain:

```powershell
Set-Location apps/web
npm ci
if (-not (Test-Path .env.local)) { Copy-Item .env.example .env.local }
npm run dev
```

Web berjalan pada `http://localhost:3000`. Atur `LAOS_API_BASE_URL` di `apps/web/.env.local`, biasanya `http://127.0.0.1:8000/api/v1`. URL ini hanya digunakan server Next.js; token API disimpan dalam cookie `HttpOnly` dan tidak dikirim ke JavaScript browser.

Panduan penggunaan web tersedia di [Panduan Operasional Web Fase 5](./docs/panduan-web-fase-5.md). Web operasional tersedia untuk Admin Sistem, Operator Puskesmas, Operator Sekolah/Guru, dan Siswa.

### 3. Flutter mobile

```powershell
Set-Location apps/mobile
flutter pub get
flutter analyze
flutter test
flutter run
```

Atur URL API per environment pada tahap integrasi berikutnya. Jangan menaruh credential atau data kesehatan di source code, log, atau konfigurasi aplikasi.

### 4. MySQL lokal dengan Docker

Docker Desktop harus berjalan. Atur password root dan password aplikasi lokal pada sesi PowerShell, lalu jalankan Compose dari root repository:

```powershell
$env:MYSQL_ROOT_PASSWORD = Read-Host "Password root MySQL lokal"
$env:MYSQL_PASSWORD = Read-Host "Password user aplikasi MySQL lokal"
docker compose up -d mysql
docker compose ps
```

Container membuat database `laos` dan user aplikasi `laos`. Koneksi Laravel lokal: driver `mysql`, host `127.0.0.1`, port `3306`, database/user `laos`, dan `DB_PASSWORD` sama dengan nilai `MYSQL_PASSWORD` yang dimasukkan di shell. Salin `.env.example` menjadi `.env` bila belum ada, lalu isi `DB_PASSWORD` lokal dengan password tersebut. Jangan gunakan password root aplikasi; root hanya untuk administrasi container.

Gunakan password berbeda dari akun lain dan jangan menaruhnya pada command line, repository, atau file `.env.example`. File `.env` lokal diabaikan Git. Ekstensi PHP `pdo_mysql` harus aktif. Tes CI tetap menggunakan SQLite in-memory, sehingga tidak membutuhkan container database.

Hentikan layanan dengan `docker compose down`. Data MySQL lokal disimpan pada named volume `mysql-data`. Jangan gunakan `docker compose down -v` kecuali memang ingin menghapus seluruh data database lokal. Volume PostgreSQL lama (jika pernah dibuat) tidak digunakan dan tidak dihapus oleh konfigurasi ini.

## Pemeriksaan sebelum pull request

API:

```powershell
Set-Location apps/api
composer validate --strict
php artisan test
composer audit
```

Web:

```powershell
Set-Location apps/web
npm ci
npm run lint
npx tsc --noEmit
npm audit --audit-level=high
npm run build
```

Mobile:

```powershell
Set-Location apps/mobile
flutter pub get
dart format --set-exit-if-changed .
flutter analyze --fatal-infos
flutter test
flutter build apk --debug
```

GitHub Actions menjalankan job yang sama atau setara untuk API, web, mobile, dan pemindaian secret.

## Lingkungan dan data

- Gunakan data sintetis saja pada local, CI, dan staging kecuali ada persetujuan formal untuk data yang dianonimkan.
- Jangan mengirim `.env`, private key, token, dump database, laporan ekspor, atau data siswa ke Git.
- Secret CI/staging harus disimpan pada secret store lingkungan masing-masing dan memiliki hak minimum.
- GitHub Actions melakukan CI, tetapi deploy hosted staging belum dikonfigurasi karena provider, region, dan repository remote belum ditetapkan.
- Secret scan memakai Gitleaks Action. Untuk repository organisasi, tambahkan `GITLEAKS_LICENSE` sebagai GitHub Actions secret sesuai ketentuan penyedianya.

## Dokumentasi

- [PRD](./PRD-Laporan-Online-Sekolah.md)
- [Fase pengembangan](./fase%20pengembangan.md)
- [Desain Fase 0](./desain-fase-0.md)
- [Desain Fase 1](./desain-fase-1.md)
- [Desain Fase 2](./desain-fase-2.md)
- [Kontrak API Fase 3](./docs/api-fase-3.md)
- [Kontrak API Fase 4](./docs/api-fase-4.md)
- [Kontrak API Fase 6](./docs/api-fase-6.md)
- [Runbook QA dan Pilot Fase 8](./docs/qa-pilot-fase-8.md)
- [Runbook Operasi Produksi Fase 9](./docs/operasi-produksi-fase-9.md)
