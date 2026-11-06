# Kontrak API Fase 6 — Laporan

Semua endpoint berada di bawah `/api/v1`, memerlukan autentikasi dan permission `examinations.view`. Admin Sistem memiliki permission tersebut secara implisit; Operator Puskesmas dan Operator Sekolah/Guru dibatasi oleh scope. Akun Siswa tidak dapat menggunakan endpoint rekap atau ekspor.

## Filter bersama

| Parameter | Wajib | Keterangan |
|---|---:|---|
| `academic_year` | Ya | Label `YYYY-YYYY`, misalnya `2025-2026`; hanya tahun ajaran pada sekolah yang dapat diakses yang dihitung. |
| `semester` | Tidak | `1` atau `2`. Jika tidak dikirim, laporan mencakup satu tahun ajaran. |
| `school_id` | Tidak | Batasi ke sekolah tertentu dalam scope akun. |
| `school_class_id` | Tidak | Batasi ke kelas dalam sekolah yang dapat diakses dan cocok dengan `school_id` bila diberikan. |
| `level` | Tidak | Salah satu dari `sd`, `smp`, `sma`, `slb`. |

## Rekap dan grafik

`GET /reports/summary`

Menghasilkan `period`, `summary` (`total`, `checked`, `not_checked`, `coverage_percent`), `by_school`, `by_class`, dan metadata `privacy`. Sasaran berasal dari enrollment yang rentang tanggalnya beririsan dengan tahun ajaran atau semester terpilih. Admin Sistem dan Operator Puskesmas menghitung pemeriksaan berstatus `finalized` atau `published`; Operator Sekolah/Guru hanya menghitung `published`, agar keberadaan hasil yang belum dibuka ke sekolah tidak terungkap. Draf tidak dimasukkan. Per sekolah/kelas, `total` menghitung enrollment yang cocok dengan filter. Saat rekap lintas sekolah, data pemeriksaan dibatasi ke sekolah dalam scope.

Grafik hanya menerima kelompok dengan minimal 5 sasaran (`privacy.minimum_group_size`). Kelompok di bawah ambang tidak disertakan dalam daftar dan jumlahnya hanya diberikan sebagai jumlah grup yang disembunyikan. Angka agregat total tetap tersedia. Ambang 5 adalah nilai awal implementasi dan harus disetujui pemilik kebijakan privasi sebelum produksi.

## Ekspor CSV

`GET /reports/examinations.csv`

Menerapkan filter bersama dan mengembalikan CSV ber-BOM UTF-8 dengan metadata pemeriksaan dan indikator. Secara default Operator Sekolah hanya memperoleh pemeriksaan `published`; akun sekolah tidak dapat memilih status `finalized`. Admin Sistem dan Operator Puskesmas dapat memilih `status=finalized` atau `status=published`; tanpa parameter keduanya disertakan. Draf tidak pernah diekspor.

Endpoint CSV langsung ini dipertahankan untuk kompatibilitas dan dibatasi 5.000 baris; jika terlampaui API mengembalikan `422` dan meminta filter dipersempit. Nilai sel yang berpotensi ditafsirkan sebagai formula spreadsheet diawali apostrof. Permintaan ekspor dicatat di `audit_logs`.

## Ekspor tersimpan

- `POST /reports/exports` menerima filter bersama, `format` (`csv`, `xlsx`, atau `pdf`), dan `status` opsional (`finalized` atau `published`). Membalas `202` dengan metadata ekspor dan `status` antrean.
- `GET /reports/exports` menampilkan riwayat paginasi (20 per halaman) yang masih berada dalam cakupan sekolah akun aktif.
- `GET /reports/exports/{id}` memeriksa ulang cakupan dan status pekerjaan.
- `GET /reports/exports/{id}/download` hanya mengirim file selesai melalui endpoint autentikasi; file tidak memakai URL publik atau URL bertanda tangan.

Pekerjaan memakai queue Laravel `report-exports` pada koneksi database, lalu menyimpan file di disk `local` privat (`storage/app/private`). Jalankan worker dengan `php artisan queue:work --queue=report-exports,default`; `DB_QUEUE_RETRY_AFTER` sebaiknya lebih besar dari timeout pekerjaan (default 360 detik). Data dan permission peminta diperiksa ulang saat pekerjaan berjalan dan cakupan penerima diperiksa kembali saat riwayat/unduhan diakses. Admin Sistem dapat mengakses seluruh cakupan; operator hanya dapat mengakses file yang sekolah targetnya masih berada di cakupan mereka. Riwayat dan berkas tidak terikat pada akun operator pembuat, sehingga dapat diteruskan ke petugas pengganti yang berwenang.

CSV dan XLSX dibatasi 5.000 pemeriksaan; PDF dibatasi 100 pemeriksaan. Formula spreadsheet dinetralkan dan sel XLSX ditulis sebagai teks. PDF memakai HTML ter-escape tanpa resource remote. Metadata ekspor mencatat filter, peminta, waktu snapshot, jumlah baris, status, dan masa berlaku; audit log mencatat permintaan, selesai, dan setiap unduhan tanpa menyimpan indikator kesehatan. Retensi file default tujuh hari. Jalankan scheduler Laravel (`php artisan schedule:work` lokal atau `schedule:run` dari cron) untuk menjalankan `reports:purge-expired-exports`; metadata riwayat tetap ada setelah file dihapus.

Web menyediakan cetak ringkasan/rapor individu melalui dialog peramban dan ekspor file PDF/XLSX/CSV terantre dengan riwayat unduhan. Ambang privasi grafik (5 sasaran) dan jadwal retensi tujuh hari adalah nilai awal implementasi yang harus disetujui pemilik kebijakan sebelum produksi.
