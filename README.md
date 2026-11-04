# LAOS — Laporan Online Sekolah

Monorepo aplikasi Laporan Online Sekolah:

- `apps/api`: Laravel REST API
- `apps/web`: Next.js
- `apps/mobile`: Flutter
- `docs/`: PRD dan rancangan fase

Fase saat ini menyiapkan fondasi teknis. Fitur autentikasi domain, master data, dan pemeriksaan belum diimplementasikan.

## Prasyarat

- PHP 8.4 dan Composer 2
- Node.js 24 dan npm
- Flutter 3.41.4 stable serta Android SDK untuk membangun APK
- Docker Desktop hanya diperlukan untuk PostgreSQL lokal melalui Compose

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
php artisan serve
```

API lokal berjalan pada `http://127.0.0.1:8000`. Health check skeleton: `GET /api/v1/health`.

### 2. Next.js web

Pada terminal lain:

```powershell
Set-Location apps/web
npm ci
if (-not (Test-Path .env.local)) { Copy-Item .env.example .env.local }
npm run dev
```

Web berjalan pada `http://localhost:3000`. Base URL API lokal diatur melalui `NEXT_PUBLIC_API_BASE_URL`; variabel `NEXT_PUBLIC_*` hanya untuk nilai yang aman ditampilkan pada browser.

### 3. Flutter mobile

```powershell
Set-Location apps/mobile
flutter pub get
flutter analyze
flutter test
flutter run
```

Atur URL API per environment pada tahap integrasi berikutnya. Jangan menaruh credential atau data kesehatan di source code, log, atau konfigurasi aplikasi.

### 4. PostgreSQL lokal dengan Docker

Docker Desktop harus berjalan. Isi `POSTGRES_PASSWORD` pada proses PowerShell, lalu jalankan Compose dari root repository:

```powershell
$env:POSTGRES_PASSWORD = Read-Host "Password PostgreSQL lokal"
docker compose up -d postgres
docker compose ps
```

Default koneksi: database `laos`, user `laos`, host `127.0.0.1`, port `5432`. Untuk memakai PostgreSQL dari Laravel, sesuaikan konfigurasi `.env` lokal (`DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, dan kredensial yang sama). Nilai password hanya untuk lingkungan lokal, tidak boleh dipakai di staging/production atau dikomit.

Ekstensi PDO PostgreSQL (`pdo_pgsql`) harus aktif pada PHP lokal sebelum Laravel menggunakan database ini. SQLite tetap menjadi default untuk menjalankan API tanpa Docker.

Hentikan layanan dengan `docker compose down`. Data lokal disimpan pada named volume; `docker compose down -v` menghapus volume dan seluruh data lokal tersebut.

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
