# Runbook Operasi Produksi Fase 9

Runbook ini menyiapkan deployment yang mengikuti target awal WHM cPanel. **Akun/server dan versinya, domain/subdomain, region, SLA, RPO/RTO, backup provider, dan support desk belum ditetapkan.** Jangan mengarahkan data nyata ke aplikasi sampai pemilik proses/data dan penanggung jawab operasi menyetujui keputusan tersebut.

## 1. Pemilik, persetujuan, dan gerbang rilis

Sebelum rollout, catat:

- Pemilik proses LAOS, pemilik data, penanggung jawab aplikasi/API, web, mobile, database, keamanan/privasi, dan support/insiden.
- Wilayah Puskesmas dan sekolah pilot/rollout; target jumlah siswa, pengguna, puncak beban, wilayah hosting, perangkat dan browser yang didukung.
- Dasar pemrosesan data anak, pemberitahuan/persetujuan yang diperlukan, hak akses, retensi/pemusnahan, wilayah penyimpanan, dan prosedur insiden.
- SLA/ketersediaan, ambang alert, jam support, severity/eskalasi, RPO/RTO, cadangan terenkripsi, retensi backup, dan jadwal uji pemulihan.
- Keputusan provider/region/domain, MySQL managed/self-hosted, private object storage untuk ekspor, SMTP, log/monitoring, TLS/proxy, serta anggaran.
- Hasil Fase 8: CI di commit rilis hijau, UAT/security/performa/restore selesai atau memiliki penerimaan risiko formal, dan rollback diuji.

Gunakan daftar persetujuan dan kriteria go/no-go pada [Runbook QA dan Pilot Fase 8](./qa-pilot-fase-8.md). Keputusan/ambang yang belum disetujui tetap sebagai blocker; jangan mengasumsikan nilai SLA, legal, klinis, atau privasi.

## 2. Persiapan environment

### Isolasi dan rahasia

- Buat environment production terpisah dari local/CI/staging; gunakan data sintetis di staging.
- Simpan `.env`/secret pada secret manager host, bukan di repository, image publik, tiket, log, atau command line. Batasi pembacaan pada service identity dan petugas yang disetujui; rotasi saat personel/provider berubah.
- Gunakan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain-yang-disetujui>`, `DB_CONNECTION=mysql`, password DB berhak minimum, TLS untuk koneksi publik, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, mailer delivery yang teruji, serta queue/cache/session persisten.
- Gunakan HTTPS untuk API dan web; pastikan reverse proxy/TLS, forwarded headers, trusted proxies, firewall, batas request/upload, security headers, DNS/certificate renewal, dan akses admin telah direview oleh pemilik platform.
- `FILESYSTEM_DISK` untuk laporan harus private (bukan `public`). Ekspor tidak boleh ditempatkan di webroot. Atur lifecycle/retensi sesuai kebijakan; aplikasi menghapus ekspor kedaluwarsa melalui scheduler, tetapi backup eksternal dan penghapusan permanen perlu kebijakan terpisah.
- Jangan menaruh email pribadi/data siswa pada metrik, nama file, log, atau alert. Pastikan hak akses file dan enkripsi storage/backup diverifikasi provider.

### Kesiapan Laravel

Jalankan sebagai service identity aplikasi dari `apps/api` pada environment yang telah disetujui:

```powershell
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan laos:check-production-readiness
php artisan migrate:status
```

`laos:check-production-readiness` hanya membaca konfigurasi, database, dan daftar migrasi. Exit code bukan nol berarti **no-go**; perintah tidak memperbaiki konfigurasi atau menerapkan migrasi. Periksa host, driver MySQL/MariaDB, akses database, backup, serta file ekspor secara terpisah.

Setelah backup terverifikasi dan persetujuan change window, jalankan tiap langkah secara terpisah. Hentikan saat ada exit code bukan nol; jangan lanjut ke migrasi/seeder atau membuka maintenance tanpa keputusan operator:

```powershell
php artisan down
```

```powershell
php artisan migrate --force
```

```powershell
php artisan db:seed --force
```

```powershell
php artisan optimize
```

Setelah smoke test dan keputusan membuka layanan:

```powershell
php artisan up
```

Seeder saat ini menyelaraskan role/permission referensi. Review diff seeder pada setiap rilis; jangan pernah menjalankan `migrate:fresh`, `migrate:refresh`, `db:wipe`, factory seeding, atau reset database produksi. Hindari deploy migrasi destructive/rename yang tidak kompatibel dengan versi aplikasi sebelumnya; gunakan pola expand/migrate/contract dalam beberapa rilis.

Bootstrap Admin Sistem pertama kali hanya setelah migrasi dan seeder berhasil, melalui sesi terminal terlindungi:

```powershell
php artisan laos:bootstrap-admin
```

Perintah meminta password secara tersembunyi dan hanya berjalan bila belum ada Admin Sistem. Jangan menaruh password pada command history/chat/log; verifikasi MFA/SSO bila tersedia di perimeter dan kelola password awal melalui kanal yang disetujui. Perintah ini satu kali, bukan mekanisme reset akun.

Build Next.js dengan `LAOS_API_BASE_URL` server-side menunjuk API HTTPS yang disetujui. Jangan memakai URL internal/debug HTTP atau mem-publish secret API ke `NEXT_PUBLIC_*`. Build Android/iOS memakai signing key/provisioning organisasi di luar repository; uji update, expiry, logout, dan versi OS yang disetujui sebelum distribusi.

### Profil WHM cPanel (provisional)

WHM/cPanel adalah jenis platform yang dipilih, tetapi fitur bergantung pada versi/tema, paket provider, OS, dan hak akun. Verifikasi setiap butir berikut kepada penyedia atau administrator WHM sebelum membeli/menyiapkan data:

- PHP CLI/web **8.4**, Composer 2/SSH Terminal, ekstensi `pdo_mysql`, dan MySQL/MariaDB versi yang kompatibel tersedia.
- Domain API dapat memakai document root tepat ke `apps/api/public` atau release `public`; **jangan** arahkan webroot ke root project Laravel, `.env`, `vendor`, atau `storage`.
- HTTPS/TLS, reverse proxy, secret environment, folder aplikasi/storage privat di luar document root, cron, backup serta pemulihan database/file tersedia dan dapat diuji.
- Next.js 16 membutuhkan Node `>=20.9.0` menurut lockfile. CI kini menguji Node 22 dan 24; halaman dokumentasi cPanel EasyApache yang tersedia mencantumkan Node 16/18/20/22 pada paket tertentu. Pastikan akun/fitur Web Apps atau Application Manager benar-benar menyediakan versi yang telah diuji. Jika hanya menyediakan Node versi lebih lama, **no-go** sampai runtime dan CI diubah serta diuji.
- Pastikan Passenger/Node app dapat menjalankan Next.js long-lived server, mengatur `PORT`/environment, restart aplikasi, dan menyajikan HTTPS. Jangan mengunggah isi repository ke `public_html` sebagai aplikasi statis.
- Worker queue harus berjalan persisten di bawah process manager yang diizinkan, atau provider harus mendukung cron terjadwal dengan `flock`/lock sejenis agar worker tidak bertumpuk. Jika tidak ada pilihan ini, ekspor asynchronous belum siap untuk pilot.

Pola direktori yang disarankan (ubah mengikuti host dan pastikan `public` adalah satu-satunya webroot Laravel):

```text
/home/<akun>/apps/laos-api/       # kode Laravel, .env, vendor, storage; tidak public
/home/<akun>/apps/laos-web/       # kode/build Next.js; dikelola Node app/Passenger
/home/<akun>/logs/                # log operasional terbatas, retention terkontrol
<domain-api>  -> laos-api/public  # konfigurasi domain/vhost cPanel
<domain-web>  -> aplikasi Next.js # Node app, bukan akses file source langsung
```

Jalankan deployment sebagai akun cPanel pemilik aplikasi, tidak sebagai `root`. Untuk akun tanpa Supervisor/systemd, contoh worker berkunci yang hanya dijalankan setelah provider menyetujui cron dan path PHP:

```sh
flock -n /home/<akun>/.laos-queue.lock \
  /path/php /home/<akun>/apps/laos-api/artisan queue:work \
  --stop-when-empty --queue=report-exports,default --tries=2 --timeout=300
```

Jadwalkan dengan interval yang disepakati untuk latensi ekspor; lock mencegah cron memulai worker kedua saat proses lama masih berjalan. Pastikan `flock` tersedia, job timeout/retry sesuai konfigurasi, output error masuk ke kanal support yang terlindungi, dan queue backlog/failed job dipantau. Jika tidak tersedia lock atau process manager, jangan jalankan cron worker paralel tanpa batas—gunakan hosting/worker terpisah yang disetujui.

Scheduler Laravel dapat dipanggil melalui cron tiap menit dari path aplikasi:

```sh
* * * * * cd /home/<akun>/apps/laos-api && /path/php artisan schedule:run
```

Atur notifikasi kegagalan ke kanal operasi yang aman; jangan membuang error ke `/dev/null` tanpa monitoring, dan jangan menyimpan output berisi data pribadi pada lokasi publik. Periksa `schedule:list` setelah deploy.

Panduan resmi cPanel: [Deploy a Website with Node.js](https://docs.cpanel.net/cpanel/meridian/websites/deploy-a-website-with-nodejs/), [Application Manager](https://docs.cpanel.net/cpanel/software/application-manager/), [Passenger Applications](https://docs.cpanel.net/knowledge-base/web-services/using-passenger-applications/), dan [Cron Jobs](https://docs.cpanel.net/cpanel/advanced/cron-jobs/). Fitur dan versi runtime harus dikonfirmasi ke host; dokumentasi tersebut bukan jaminan bahwa paket akun LAOS menyediakannya.

## 3. Tahapan rollout dan data awal

1. Buat release immutable dari commit/tag yang CI-nya hijau; catat checksum/artifact, dependency lockfiles, migrasi, versi runtime, dan change ticket.
2. Deploy dan smoke-test staging dengan seed sintetis, worker, scheduler, HTTPS, mail, log, alert, export private storage, backup, dan pemulihan.
3. Ambil backup database serta storage yang konsisten, verifikasi hasil/checksum, catat waktu dan pemilik sebelum mengubah produksi.
4. Terapkan release ke internal/admin terbatas; cek `/up`, `GET /api/v1/health`, login akun pilot, query scope, operasi pemeriksaan sintetis yang disetujui, worker ekspor, unduh, dan scheduler.
5. Migrasi referensi/data hanya dengan file tervalidasi, pemilik data, pemetaan kolom, deduplikasi, rekonsiliasi jumlah, dan persetujuan. Untuk siswa gunakan alur preview lalu commit; simpan ringkasan/hasil validasi, bukan CSV berisi data pribadi di tiket.
6. Buka bertahap pada satu wilayah Puskesmas/sekolah yang disetujui. Tahan cohort berikutnya sampai metrik kualitas, cakupan, antrean, error, dukungan, serta privasi memenuhi ambang yang telah ditetapkan.
7. Setiap penambahan wilayah memerlukan verifikasi scope/operator, periode akademik, school master, komunikasi, pelatihan, dan rollback cohort. Jangan mengaktifkan cakupan global atau akun bersama untuk mempercepat onboarding.

## 4. Worker, scheduler, dan health monitoring

Ekspor laporan berjalan di antrean `report-exports`, dengan job maksimum 2 percobaan dan timeout 300 detik. Jalankan worker persisten di bawah process supervisor/platform, dengan memory/time limit dan restart yang disepakati:

```powershell
php artisan queue:work --queue=report-exports,default --tries=2 --timeout=300
```

Jangan jalankan worker sebagai root. Pantau worker alive, queue depth/oldest job, failed jobs, status ekspor queued/processing/completed/failed, disk space, dan alarm kegagalan; jangan log isi ekspor. Saat release, restart worker melalui supervisor atau `php artisan queue:restart`, lalu verifikasi worker baru memuat release yang benar.

Jalankan scheduler Laravel melalui scheduler service yang persisten, umumnya tiap menit:

```powershell
php artisan schedule:run
```

Sebelum produksi, verifikasi `php artisan schedule:list` menampilkan `reports:purge-expired-exports`; uji dengan file sintetis kedaluwarsa dan pastikan scheduler serta storage lifecycle tidak bertentangan.

Hubungkan `/up` (framework) dan `/api/v1/health` (minimal status API) ke health check provider. Keduanya tidak membuktikan worker, mail, storage, backup, atau integrasi siap; pantau komponen tersebut terpisah dengan probe terbatas. Tetapkan alert untuk error/5xx, latency p95/p99, uptime, database connection/slow query, queue backlog/failure, storage usage, backup terakhir, sertifikat TLS, kegagalan login agregat, dan ekspor kedaluwarsa gagal. Ambang dan penerima alert harus disetujui.

Catat `request_id` untuk penelusuran insiden tanpa mengirim token, password, NISN, nama/rapor, nilai indikator, CSV, atau body sensitif ke log. Terapkan akses terbatas, retensi, enkripsi, dan review terjadwal atas log/audit. Audit aplikasi bukan pengganti log keamanan infrastruktur dan keduanya tidak boleh berisi payload kesehatan.

## 5. Onboarding, pergantian, dan offboarding operator

### Onboarding

1. Terima permintaan yang disetujui dengan identitas, peran, scope Puskesmas/sekolah, pemilik akun, masa penugasan, dan tanggal review.
2. Admin Sistem membuat akun individual memakai password kuat melalui alur yang disetujui; jangan membuat akun bersama atau memberi role lebih luas daripada tugas.
3. Verifikasi pivot role benar (`puskesmas` atau `school` dan ID scope tepat), akun dapat login, dan API menolak akses ke scope lain.
4. Kirim instruksi aktivasi secara privat, wajibkan perubahan kredensial sesuai kebijakan, catat tiket/aktor (tanpa password), dan jadwalkan review akses.

### Pergantian operator

1. Buat dan verifikasi operator pengganti dengan scope yang sama sebelum cutover; lakukan serah-terima proses dan backlog melalui prosedur organisasi.
2. Konfirmasi arsip pemeriksaan/ekspor berada pada scope institusi, bukan akun perorangan.
3. Nonaktifkan akun lama melalui Admin Sistem. Endpoint status menonaktifkan akun dan mencabut semua personal access token.
4. Uji ulang bahwa token lama mendapat penolakan, operator baru dapat bekerja hanya pada scope yang disetujui, dan audit event tercatat.
5. Hapus akses provider/SSO/email/VPN sesuai kebijakan; jangan menghapus arsip/audit milik institusi.

Perubahan role/scope dilakukan oleh Admin Sistem dan harus diuji segera. Jika ingin memaksa semua token dicabut saat perubahan role, nonaktifkan akun terlebih dahulu, ubah role/scope, kemudian aktifkan kembali setelah verifikasi; perubahan role saja tidak menjamin pencabutan semua token yang sudah terbit. Untuk kehilangan perangkat atau dugaan kompromi, nonaktifkan akun segera lalu ikuti prosedur insiden.

## 6. Backup, restore, patch, dan review akses

- Tetapkan pemilik backup, jadwal, RPO/RTO, retensi, lokasi, enkripsi, akses, dan alert bersama pemilik data/platform sebelum pilot.
- Backup harus mencakup database **dan** file ekspor yang masih harus tersedia. Pisahkan salinan dari environment utama, lindungi key terpisah, verifikasi keberhasilan/checksum, dan uji restore ke lingkungan terisolasi sesuai runbook Fase 8.
- Ambil backup terverifikasi sebelum migrasi besar/import awal. Jangan anggap snapshot otomatis sebagai backup teruji; ukur RPO/RTO aktual dan simpan berita acara.
- Review akses operator/admin dan akun layanan pada frekuensi yang disetujui serta setiap rotasi/perubahan organisasi. Cocokkan daftar akun aktif, scope, pemilik, status dan audit log; cabut akun yang tidak lagi diperlukan.
- Tinjau advisory Composer/npm/Flutter dan image/runtime secara berkala. Patch melalui branch/CI, staging, backup, change approval, rollout terbatas, dan pemantauan; jangan upgrade langsung di production.
- Arsip ekspor aplikasi kedaluwarsa setelah 7 hari secara default (`REPORT_EXPORT_RETENTION_DAYS`), subject to policy approval. Tentukan retensi berbeda untuk backup, audit, dan arsip pemeriksaan melalui proses hukum/pemilik data.

## 7. Insiden, rollback, dan pembelajaran

Support desk mencatat waktu, reporter/peran, environment, request ID, dampak, severity, gejala, release, dan langkah mitigasi; tidak meminta password/token atau menyalin data kesehatan ke tiket. Tentukan severity, on-call, kanal eskalasi, notifikasi pemilik data, persyaratan pelaporan insiden, dan batas waktu sesuai kebijakan sebelum pilot.

Untuk paparan data, akses lintas scope, akun kompromi, atau integritas arsip diragukan: hentikan cohort/fitur terdampak, nonaktifkan akun/token atau kredensial terkait, pertahankan bukti yang aman, beri tahu penanggung jawab sesuai prosedur, dan jangan melakukan investigasi/penyalinan data tanpa otorisasi. Dokumentasikan keputusan, dampak, komunikasi, remediasi, dan retest.

Rollback aplikasi gunakan artifact sebelumnya yang kompatibel dengan skema; rollback tidak berarti menghapus data atau menjalankan `migrate:rollback` otomatis. Jika migrasi tidak backward-compatible, hentikan rollout dan ikuti rencana forward-fix/restore yang telah disetujui. Putuskan restore database hanya oleh pemilik berwenang setelah menilai kehilangan perubahan sejak backup serta prosedur rekonsiliasi. Verifikasi smoke test dan minta keputusan go/no-go sebelum membuka akses kembali.

Dalam review berkala, evaluasi agregat (tanpa identitas siswa) seperti jumlah akun aktif per peran/scope, pemeriksaan dibuat/final/publikasi, cakupan per periode, koreksi/duplikasi, waktu ekspor, error/latency, antrean, backup/restore, tiket dukungan, dan insiden privasi. Pemilik produk memprioritaskan backlog dengan dampak, risiko, frekuensi, biaya, dan persetujuan; perluasan ke akses orang tua, offline, integrasi eksternal, atau data klinis tambahan memerlukan perubahan PRD dan review privasi/keamanan.

## Status Fase 9

**Yang tersedia:** readiness command tanpa side effect, CI/release gates, health endpoints, bootstrap Admin Sistem sekali jalan, audit/scope akun, pencabutan token saat akun dinonaktifkan, queue/scheduler untuk ekspor, serta runbook rollout dan operasi.

**Belum dikerjakan/dibuktikan:** provisioning akun/server WHM cPanel, kompatibilitas PHP/Node/worker/cron pada plan, pemilihan domain/subdomain dan region data, deployment hosted, backup job dan restore pada provider, monitoring/alert aktif, uji beban produksi-like, pelatihan, support desk, persetujuan legal/pemilik data, target SLA/RPO/RTO, dan rollout pilot. Fase 9 belum dapat dinyatakan selesai tanpa pemilik dan bukti untuk item tersebut.
