# Runbook QA dan Pilot Fase 8

Dokumen ini memisahkan validasi yang dapat diulang di repository dari aktivitas yang memerlukan lingkungan, persetujuan, dan penanggung jawab organisasi. Jangan gunakan data kesehatan nyata untuk pengujian lokal atau CI.

## Status dan batas validasi

| Area | Bukti otomatis yang tersedia | Validasi yang masih diperlukan |
|---|---|---|
| API/regresi | Feature tests untuk role/scope, impor, login, enrollment, pemeriksaan, duplikasi, finalisasi, koreksi, histori siswa, laporan, ekspor, dan retensi | Kontrak dengan integrasi eksternal bila ada |
| Web | Lint, TypeScript, build, dan audit dependency di CI | UAT per role, browser/perangkat target, aksesibilitas manual |
| Mobile | Analyze, widget tests untuk login/tautan/sesi expired, dan APK debug di CI | UAT perangkat Android/iOS, aksesibilitas, jaringan lemah; iOS memerlukan macOS/Xcode |
| Keamanan | Tes otorisasi negatif, rate limit login, scope unduhan ekspor, input, dan sanitasi spreadsheet; secret/dependency scan di CI | Review konfigurasi deployment, pengelolaan secret, log, TLS, akses penyedia, dan keputusan risiko formal |
| Performa | Belum ada hasil benchmark beban yang disetujui | Jalankan skenario di bawah terhadap staging dan setujui target sebelum pilot |
| Backup/restore | Belum ada bukti restore lingkungan | Simulasikan backup dan restore staging terisolasi, cocokkan integritas, lalu simpan berita acara |
| Pilot/UAT | Belum dilakukan | Penetapan wilayah/sekolah, persetujuan pemilik data, peserta, pelatihan, kanal bantuan, UAT, monitoring, dan go/no-go |

Tes otomatis tidak membuktikan keamanan deployment atau kesiapan operasional. Jangan menyatakan pilot, backup/restore, atau go-live telah lulus hanya berdasarkan CI.

## Menjalankan pemeriksaan regresi

Jalankan dari root repository di PowerShell:

```powershell
Set-Location apps/api
composer validate --strict
composer audit --no-interaction
php artisan test

Set-Location ..\web
npm ci
npm run lint
npx tsc --noEmit
npm audit --audit-level=high
npm run build

Set-Location ..\mobile
flutter pub get
flutter analyze --fatal-infos
flutter test
flutter build apk --debug
```

CI menjalankan pemeriksaan yang sama pada pull request dan push. Gunakan hasil CI terbaru pada commit kandidat; catat commit SHA, waktu, runner, dan tautan/log run pada berita acara. Dependency audit dapat memerlukan remediasi terpisah; jangan mengabaikan advisory hanya untuk membuat pipeline hijau.

### Cakupan risiko teruji

- **Akses lintas sekolah/Puskesmas dan siswa (IDOR):** Operator di luar scope ditolak; siswa hanya dapat membuka histori miliknya dan hasil yang terpublikasi.
- **Privilege escalation:** Route dilindungi role/permission dan tes feature menguji jalur operator terbatas.
- **Pemeriksaan:** Periode/enrollment divalidasi; satu pemeriksaan per siswa/semester; semua indikator harus eksplisit sebelum finalisasi; koreksi memerlukan alasan dan versi terkini serta menyimpan snapshot.
- **Ekspor/file:** Cakupan diperiksa saat melihat/mengunduh; file privat dan memiliki masa berlaku; nilai formula dinetralkan; uji mencakup unduhan di luar scope.
- **Brute force:** Login dibatasi lima percobaan per menit per email dan alamat IP.
- **Privasi laporan:** Grup kecil disupresi; role sekolah tidak dapat mengekspor status pemeriksaan yang belum dipublikasikan.
- **Mobile:** Akun tanpa tautan terverifikasi tidak meminta histori; sesi 401 dibersihkan; alur login/rapor diuji dengan fake API/store.

Coverage di atas bukan penetration test. Pengujian tambahan yang berisiko atau menyentuh data nyata hanya boleh dilakukan pada staging yang diizinkan, oleh personel berwenang, dengan batas dan jadwal yang disetujui.

## Rencana uji performa staging

1. Pemilik layanan menetapkan target p95/p99, error rate, konkurensi, volume data, dan batas resource sebelum pengujian; jangan mengarang target setelah melihat hasil.
2. Buat snapshot sintetis yang mewakili volume siswa, pemeriksaan per semester, dan ukuran arsip ekspor. Jangan menyalin PII/kesehatan produksi ke staging tanpa persetujuan dan proses anonimisasi.
3. Warm-up, lalu ukur login, pencarian siswa, coverage, summary/grafik, permintaan ekspor queued, pemrosesan worker, status ekspor, dan unduhan.
4. Tingkatkan beban bertahap dalam batas yang disetujui. Pantau p95/p99, throughput, timeout/5xx, CPU, memori, DB connection/slow query, queue depth, dan disk.
5. Hentikan tes jika error/latensi melampaui batas penghentian yang disepakati atau membebani layanan bersama. Jangan melakukan load test ke produksi.
6. Simpan konfigurasi generator, seed/volume data sintetis, hasil mentah, grafik, batas yang disetujui, dan rekomendasi kapasitas.

**Status:** belum dijalankan; target, staging, kapasitas, dan jendela tes harus disetujui.

## Simulasi backup dan restore

Lakukan hanya pada database dan object/file storage staging yang terisolasi, dengan persetujuan pemilik platform:

1. Catat versi aplikasi, migrasi, versi MySQL, konfigurasi backup, waktu RPO/RTO yang disetujui, dan pemilik pelaksanaan.
2. Ambil backup konsisten database serta file ekspor yang masih berlaku; simpan pada lokasi terenkripsi dengan akses terbatas. Jangan menaruh backup atau credential ke Git.
3. Verifikasi ukuran/checksum, keberhasilan job, dan keterbacaan backup tanpa mempublikasikan kontennya.
4. Pulihkan ke instance kosong yang terisolasi; jangan pernah menimpa atau memulihkan ke produksi selama latihan.
5. Jalankan migrasi/penyesuaian yang terdokumentasi dan pemeriksaan integritas: hitung record penting, relasi enrollment/pemeriksaan/observasi/revisi, status publikasi, audit log, dan keberadaan file ekspor.
6. Jalankan smoke tests API/role serta verifikasi ekspor privat/TTL. Pastikan token/sesi lama tidak menjadi jalan masuk setelah restore; terapkan prosedur pencabutan/rotasi yang disetujui.
7. Catat durasi, RPO/RTO aktual, selisih/integritas, kendala, bukti yang aman, dan tindak lanjut. Bersihkan instance latihan memakai prosedur platform yang disetujui.

**Status:** belum diuji. Latihan gagal atau data tak konsisten adalah no-go sampai pemulihan diulang dan disetujui.

## UAT dan pilot terbatas

### Prasyarat pilot

- Pemilik proses, pemilik data, penanggung jawab teknis, support desk, dan petugas insiden ditunjuk; wilayah Puskesmas/sekolah, peserta, jadwal, dan kanal bantuan disetujui.
- Perjanjian/persetujuan pemrosesan data, dasar akses, kebijakan retensi, mekanisme koreksi, perangkat dan jaringan target disetujui sebelum data digunakan.
- Gunakan data sintetis terlebih dahulu. Skenario data nyata hanya dengan otorisasi serta anonimisasi yang memadai.
- Operator diberi cakupan least privilege; akun uji/pilot memiliki pemilik dan prosedur penghentian akses.
- Worker antrean, scheduler, HTTPS, backup, monitoring, log redaction, dukungan, incident response, dan rollback telah diverifikasi di environment pilot.
- Release pilot dan konfigurasi dicatat, rencana pemulihan/rollback diuji, serta pemilik yang berwenang dapat menghentikan pilot.

### Skenario UAT minimum

| Peran | Skenario | Hasil yang diharapkan |
|---|---|---|
| Admin Sistem | Buat akun/operator dan tetapkan scope | Hanya role/scope yang disetujui; perubahan tercatat pada audit |
| Operator Puskesmas | Cari siswa, isi pemeriksaan, simpan draft, lengkapi indikator, finalisasi, koreksi dengan alasan, publikasikan | Validasi, status, snapshot versi, dan histori operator benar |
| Operator Sekolah/Guru | Kelola siswa/enrollment dalam sekolahnya dan lihat laporan | Sekolah lain tidak dapat diakses; data yang belum dipublikasikan tidak tampil |
| Siswa | Masuk, lihat rapor lintas semester/tahun yang tersedia, keluar, sesi berakhir, akun belum tertaut | Hanya histori milik siswa yang terverifikasi dan telah dipublikasikan |
| Pelaporan | Filter, grafik, unduh CSV/XLSX/PDF, buka ulang arsip, akses dari akun di luar scope | Hasil sesuai filter; grup kecil tersupresi; formula netral; akses luar scope ditolak |
| Operasional | Simulasikan jaringan gagal, antrean berhenti, restore, laporan insiden, dan rollback | Pesan aman, pemulihan mengikuti runbook, ada penanggung jawab dan jejak audit |

Catat versi aplikasi, peran tester (bukan kata sandi), skenario, hasil aktual/harapan, bukti yang sudah disamarkan, severity, issue ID, pemilik, due date, retest, dan persetujuan. Jangan mencatat token, kredensial, NISN, atau data kesehatan yang dapat mengidentifikasi siswa ke tiket/log.

## Monitoring, isu, dan keputusan

Selama pilot, lakukan review harian dengan jumlah/tingkat gagal pemeriksaan dan impor, duplikasi, koreksi, cakupan, antrean/ekspor, error/latensi, permintaan bantuan, temuan akses/privasi, dan tindakan tindak lanjut. Gunakan agregat minimum yang disetujui; jangan masukkan isi rapor atau identitas siswa ke dashboard operasional umum.

**No-go / hentikan atau rollback** bila ada akses lintas scope, paparan hasil yang belum dipublikasi, kebocoran data/secret, temuan kritis/tinggi yang tidak ditangani atau tidak diterima secara formal, integritas data/restore gagal, backup atau support tidak tersedia, error melewati ambang penghentian, atau pemilik proses menarik persetujuan.

**Go** hanya setelah:
- CI hijau pada commit kandidat dan uji manual UAT yang disepakati lulus.
- Tidak ada kerentanan kritis/tinggi terbuka kecuali risiko diterima secara formal oleh pejabat berwenang dengan mitigasi dan masa berlaku.
- Hasil performa memenuhi target yang disetujui; backup-restore, incident response, dukungan, dan rollback terbukti.
- Isu tersisa memiliki severity, pemilik, tenggat, mitigasi, dan persetujuan risiko.
- Pemilik proses, pemilik data, keamanan, dan operasi menandatangani keputusan serta cakupan rilis.

### Berita acara go/no-go

- Environment/wilayah/sekolah:
- Build commit/tag:
- Periode pilot dan volume sintetis/terotorisasi:
- Hasil CI/UAT/performa/restore:
- Isu terbuka dan penerimaan risiko:
- Keputusan dan alasan:
- Rencana rollout/rollback:
- Persetujuan pemilik proses/data/keamanan/operasi:
- Tanggal dan tautan bukti:
