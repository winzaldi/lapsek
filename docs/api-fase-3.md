# Kontrak Backend Fase 3 — LAOS

Seluruh endpoint memakai prefix `/api/v1`. Kecuali health check dan endpoint autentikasi, request memerlukan `Authorization: Bearer <token>`. JSON adalah format request/response; paginasi mengikuti format Laravel. Token Sanctum berlaku 8 jam secara default dan hanya token sesi saat ini yang dicabut oleh logout.

## Setup dan akun pertama

```powershell
Set-Location apps/api
php artisan migrate --seed
php artisan laos:bootstrap-admin
```

`laos:bootstrap-admin` membuat Admin Sistem pertama secara interaktif, hanya jika belum ada Admin Sistem. Tidak ada endpoint publik untuk bootstrap. Jangan gunakan seeder factory atau data nyata di lingkungan produksi.

## Autentikasi

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/auth/login` | Email, password, `device_name`; rate limit 5 percobaan per menit per IP+email |
| POST | `/auth/logout` | Mencabut token aktif |
| GET | `/auth/me` | Profil pengguna, role, dan `student_id` jika tertaut |
| POST | `/auth/forgot-password` | Mengirim tautan reset; respons tidak membocorkan keberadaan email |
| POST | `/auth/reset-password` | Memvalidasi token dan password minimal 12 karakter; mencabut semua token lama |

Reset kata sandi menggunakan password broker Laravel dan membutuhkan mailer yang berfungsi. Akun nonaktif tidak dapat login dan semua tokennya dicabut saat dinonaktifkan.

## Role dan cakupan

| Role | Scope assignment | Batas data |
|---|---|---|
| `system_admin` | Global | Mengelola akun dan master global |
| `puskesmas_operator` | `puskesmas` + ID Puskesmas | Sekolah di bawah Puskesmas yang ditugaskan |
| `school_operator` | `school` + ID sekolah | Hanya sekolah yang ditugaskan |
| `student` | Profil siswa tertaut, tanpa scope sekolah umum | Fitur rapor siswa menyusul pada fase laporan |

Role/permission diperiksa server-side; ID sekolah yang dikirim client tidak memperluas akses. Role dan scope diberikan oleh Admin Sistem. Tidak tersedia peran Admin Puskesmas.

## Master data dan enrollment

| Method | Endpoint | Role/permission |
|---|---|---|
| GET/POST/PATCH | `/health-centers`, `/health-centers/{id}` | Admin Sistem |
| GET | `/schools` | Operator sesuai scope, Admin Sistem |
| POST/PATCH | `/schools`, `/schools/{id}` | Admin Sistem |
| GET/POST | `/academic-years`, `/semesters` | GET sesuai scope; pembuatan Admin Sistem |
| GET/POST/PATCH | `/classes`, `/classes/{id}` | Sesuai scope; perubahan memerlukan `students.manage` |
| GET/POST/PATCH | `/students`, `/students/{id}` | Sesuai scope dan `students.view`/`students.manage` |
| GET/POST | `/enrollments` | Sesuai scope |
| PATCH/POST | `/enrollments/{id}/close`, `/enrollments/{id}/transfer` | Sesuai scope |

Tahun ajaran berformat `YYYY-YYYY`, per sekolah. Semester bernomor 1 atau 2 dan tanggalnya harus berada di dalam tahun ajaran. `level` sekolah memakai `sd`, `smp`, `sma`, atau `slb`; `school_type` memakai `regular`, `equivalent`, atau `special_needs`.

Enrollment lama tidak ditimpa. Perpindahan/kenaikan kelas menggunakan endpoint `transfer` agar enrollment lama ditutup dan yang baru dibuat dalam satu transaksi. User harus memiliki akses ke sekolah sumber dan tujuan. Hanya satu enrollment aktif per siswa yang diizinkan aplikasi.

Contoh body pembuatan siswa (sekaligus enrollment awal):

```json
{
  "school_id": 1,
  "school_class_id": 4,
  "academic_year_id": 2,
  "nisn": "0123456789",
  "name": "Siswa Contoh",
  "birth_date": "2018-05-01",
  "sex": null,
  "starts_on": "2025-07-14"
}
```

`nisn`, `birth_date`, dan `sex` dapat kosong saat entri manual. Nilai `sex` belum dibatasi enum; finalisasi istilah/kebijakan data tetap keputusan Fase 0.

## Akun pengguna dan tautan siswa

| Method | Endpoint | Role/permission |
|---|---|---|
| GET/POST | `/users` | Admin Sistem |
| PUT/PATCH | `/users/{id}/role`, `/users/{id}/status` | Admin Sistem |
| POST | `/student-account-links` | Admin Sistem; membuat tautan berstatus `pending` |
| POST | `/student-account-links/{id}/verify` | Pihak yang memiliki `student-links.verify` dan akses ke sekolah enrollment siswa |

Pengguna siswa dibuat dengan role `student` tanpa scope, kemudian ditautkan ke satu profil. Tautan yang diverifikasi merekam waktu dan pengguna verifier. Perubahan peran akun yang telah tertaut ditolak. Bootstrap dan pengelolaan akun hanya tersedia untuk Admin Sistem.

## Impor CSV siswa

1. `POST /student-imports/preview` dengan `school_id`, `academic_year_id`, dan file `csv`.
2. Tinjau jumlah serta error per baris di response; file tidak disimpan di server.
3. Jika tidak ada error, kirim ulang file identik ke `POST /student-imports/commit` beserta `preview_token`.

Header wajib: `nisn,name,birth_date,sex,class_name`. Format tanggal `YYYY-MM-DD`; NISN wajib untuk impor agar deduplikasi aman. Maksimum 1.000 baris dan ukuran 5 MB. Token preview ditandatangani, terikat pada akun/scope/hash file, dan kedaluwarsa setelah 10 menit. Commit bersifat all-or-nothing; NISN aktif, duplikat, kelas tak valid, atau data profil yang berbeda menghasilkan error dan tidak membuat data parsial. Entri manual tetap dapat dibuat tanpa NISN.

## Audit dan privasi

`GET /audit-logs` hanya untuk Admin Sistem. Log mencatat aktor, tindakan, resource, request ID, dan metadata minimum; tidak mencatat password, token, NISN, atau isi kesehatan. Respons endpoint siswa memuat data pribadi sehingga klien wajib membatasi akses, tidak menyimpan token pada penyimpanan browser yang dapat dibaca script, dan tidak menaruh data siswa pada log.

## Pemeriksaan

Validasi fase ini dapat dijalankan dengan:

```powershell
Set-Location apps/api
php artisan test
vendor/bin/pint --test
composer audit
```

API fase ini belum memuat hasil pemeriksaan, laporan rapor, grafik, unduhan laporan, UI operator, maupun integrasi Flutter. Aturan klinis tidak di-hard-code; implementasinya berada di Fase 4 setelah persetujuan Fase 0.
