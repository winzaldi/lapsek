# ADR 0001: Baseline pengembangan LAOS

- **Status:** Diterapkan untuk fondasi lokal; hosting/produksi tetap terbuka
- **Tanggal:** 2026-11-03
- **Konteks:** Workspace awal hanya berisi dokumen kebutuhan. Fase 2 memerlukan skeleton API, web, mobile, quality gates, dan cara menjalankan komponen secara lokal.

## Keputusan

- Gunakan satu monorepo dengan Laravel, Next.js, dan Flutter dalam direktori `apps/`.
- Baseline toolchain: PHP 8.4, Composer 2, Node.js 24/npm, Flutter 3.41.4 stable. Versi package aplikasi mengikuti lockfile hasil scaffold.
- Sediakan PostgreSQL 17 melalui Docker Compose untuk pengembangan lokal; aplikasi tetap dapat dijalankan tanpa Docker jika memakai konfigurasi database lokal yang didukung Laravel.
- Gunakan GitHub Actions untuk lint/typecheck/test/build dan secret scan.
- ESLint dipatok pada major 9 karena paket plugin ESLint Next.js pada scaffold ini membatasi peer compatibility hingga major 9; dependency `minimatch` dioverride ke versi patched yang kompatibel, lalu tree diverifikasi tanpa peer error.
- Siapkan hanya konfigurasi staging template; jangan deploy hosted sampai provider, region, repository remote, dan persetujuan pemilik data tersedia.
- Development, CI, dan staging tidak menggunakan data kesehatan nyata.

## Konsekuensi

- Docker daemon diperlukan hanya saat menjalankan PostgreSQL container; tidak diperlukan untuk lint/test aplikasi yang memakai konfigurasi lokal SQLite atau test runner masing-masing.
- Workflow GitHub Actions baru aktif setelah repository dipublikasikan/dihubungkan ke GitHub.
- CI memakai runner GitHub dan action pihak ketiga yang ditentukan oleh versi pada workflow; pembaruan action harus direview.
- `npm audit`, ESLint, TypeScript, dan production build harus lulus setelah pembaruan override; jangan menaikkan ESLint major sebelum peer dependencies Next.js mendukungnya.
- Perubahan versi runtime atau layanan harus dibuat melalui ADR dan diselaraskan pada README, CI, serta konfigurasi lokal.

## Belum diputuskan

- Provider/region staging dan production, domain, serta deployment/rollback hosted.
- Strategi autentikasi web/mobile.
- SLA, RPO/RTO, retensi backup, dan layanan monitoring.
- Apakah PostgreSQL juga menjadi database CI/staging setelah desain infrastruktur final.
