# Kontrak Backend Fase 4 — Pemeriksaan dan Arsip

Semua endpoint memakai `/api/v1` dan bearer token Sanctum. Hak akses berlaku di Laravel API dan dibatasi ke sekolah dalam penugasan akun. Fase ini menyimpan data pemeriksaan dari formulir `indikator laos aplikasi.docx`; odontogram berbentuk diagram gigi tidak termasuk karena spesifikasi sumber belum tersedia.

## Status workflow

```text
draft --finalize--> finalized --publish--> published
                         ^                    |
                         +-- corrections -----+
```

- Hanya Operator Puskesmas yang memiliki permission pemeriksaan dan cakupan sekolah yang dapat membuat/mengubah draf, finalisasi, publikasi, atau koreksi.
- Perubahan draf hanya berlaku ketika status `draft`. Pemeriksaan mempunyai satu slot unik per siswa-semester; pemeriksaan ulang harus melalui alur koreksi, bukan record duplikat.
- Finalisasi mensyaratkan setiap indikator memiliki status eksplisit `recorded` atau `not_examined`. Nilai `false` tetap jawaban “Tidak”; indikator yang belum dikirim tetap belum lengkap.
- Publikasi merupakan aksi terpisah dari finalisasi. Sekolah dan siswa hanya dapat membaca pemeriksaan berstatus `published`; siswa hanya dapat membaca riwayat yang tertaut dan terverifikasi miliknya.
- Koreksi memerlukan alasan dan `expected_version`. Koreksi membuat revisi immutable, menaikkan versi, mengembalikan status ke `finalized`, dan menghapus `published_at`; hasil perlu dipublikasikan ulang agar versi terkoreksi terlihat oleh sekolah/siswa.
- Aturan apakah koreksi memerlukan persetujuan pihak kedua belum disepakati pada Fase 0; alur saat ini mencatat alasan/aktor dan membatasi hak ke Operator Puskesmas dalam scope. Persetujuan tambahan dapat ditambahkan setelah keputusan proses disahkan.

## Endpoint

| Method | Endpoint | Akses |
|---|---|---|
| POST | `/examinations` | Operator Puskesmas, permission `examinations.manage` |
| GET | `/examinations` | Pemegang `examinations.view`; Puskesmas dapat melihat semua status, sekolah hanya hasil terpublikasi |
| GET | `/examination-coverage?semester_id={id}` | Operator Puskesmas; sasaran siswa, status sudah/belum diperiksa, dan rekap cakupan |
| GET/PATCH | `/examinations/{id}` | GET sesuai scope/status; PATCH hanya draf oleh Operator Puskesmas |
| POST | `/examinations/{id}/finalize` | Permission `examinations.finalize` |
| POST | `/examinations/{id}/publish` | Permission `examinations.publish` |
| POST | `/examinations/{id}/corrections` | Permission `examinations.correct` |
| GET | `/examinations/{id}/revisions` | Operator Puskesmas sesuai scope |
| GET | `/students/{id}/health-history` | Operator sesuai scope; sekolah untuk hasil terpublikasi di sekolahnya; siswa hanya profil tertaut yang terverifikasi |

List mendukung filter `school_id`, `student_id`, `semester_id`, dan `status` (status hanya untuk Operator Puskesmas); maksimum 50 baris per halaman.
Coverage memerlukan `semester_id`, dengan filter opsional `school_id`, `school_class_id`, `status=checked|not_checked`, dan `per_page` 1–50. Sasaran dihitung dari enrollment yang rentang tanggalnya beririsan dengan semester. Respons cakupan hanya memuat identitas minimum serta status pemeriksaan, tidak memuat nilai indikator.

## Membuat dan memperbarui draf

Siswa harus mempunyai enrollment untuk sekolah/tahun ajaran semester yang dipilih, dan tanggal pemeriksaan berada dalam rentang semester serta tidak di masa depan. Sekolah dan kelas pemeriksaan disalin sebagai snapshot dari enrollment pada tanggal tersebut. `examiner_name` adalah identitas petugas pemeriksa; `examiner_user_id` opsional untuk menghubungkan akun Operator Puskesmas aktif yang memiliki cakupan ke sekolah itu. `entered_by_user_id` selalu diambil dari akun yang sedang login.

```json
{
  "student_id": 45,
  "semester_id": 12,
  "examined_at": "2025-09-10",
  "examiner_name": "Petugas UKS",
  "examiner_user_id": 7,
  "observations": {
    "breakfast": {"status": "recorded", "value": false},
    "smoking": {"status": "not_examined"},
    "height_cm": {"status": "recorded", "value": 150},
    "weight_kg": {"status": "recorded", "value": 50}
  }
}
```

`observations` boleh dikirim sebagian saat draf. PATCH juga menerima `examined_at`, `examiner_name`, `examiner_user_id`, dan/atau sebagian `observations`. Untuk indikator `recorded`, `value` harus memenuhi skema indikator. Untuk `not_examined`, `value` harus kosong/tidak dikirim. Indikator yang tidak dikirim tidak diubah saat PATCH.

## Kamus indikator API

Kunci berbahasa Inggris stabil untuk integrasi klien; label UI dan opsi tampilan berbahasa Indonesia.

| Kelompok | Kunci | Nilai `recorded` |
|---|---|---|
| Umum | `blood_pressure` | Teks maks. 32 karakter; format/ambang belum ditentukan |
| Umum | `past_disease_history`, `family_disease_history` | Teks maks. 2.000 karakter; markup HTML ditolak |
| Umum | `personal_hygiene` | `good`, `fair`, `poor` |
| Gaya hidup | `breakfast`, `smoking`, `snacks`, `alcohol_narcotics` | Boolean JSON `true`/`false`; alkohol/NAFZA tetap satu indikator gabungan dari sumber |
| Gizi | `height_cm`, `weight_kg` | Angka positif, 0–2 angka desimal, batas teknis 999,99; satuan cm dan kg |
| Gizi | `nutrition_status` | `very_thin`, `thin`, `normal`, `overweight`, `obesity` |
| Gizi | `anemia` | Boolean JSON |
| Mata | `eyes_external` | `healthy`, `unhealthy` |
| Mata | `color_blindness`, `uses_glasses` | Boolean JSON |
| Telinga | `ears_external` | `healthy`, `infection`, `cerumen` |
| Telinga | `hearing` | `normal`, `impaired` |
| Gigi/mulut | `cleft_lip_palate`, `mouth_corner_lesion`, `mouth_ulcer`, `dirty_tongue`, `other_lesion`, `dental_caries`, `bleeding_gums`, `swollen_gums`, `dental_plaque`, `tartar`, `irregular_front_teeth` | Boolean JSON |

### Perhitungan dan keputusan klinis

- IMT dihitung server sebagai `berat_kg / (tinggi_cm / 100)^2`, dibulatkan 2 desimal, dan diberi `bmi_calculation_version: bmi-kg-m2-v1`. IMT kosong bila tinggi/berat belum dicatat atau tidak diperiksa.
- IMT tidak dipakai untuk mengklasifikasikan status gizi anak. `nutrition_status` adalah pilihan yang direkam petugas (`manual`), bukan rekomendasi/hasil algoritma otomatis.
- Ambang validitas klinis tinggi/berat/tekanan darah, metode/status anemia, algoritma gizi anak, dan test vector tetap menunggu persetujuan tenaga kesehatan/pemilik proses di Fase 0.
- Batas angka 999,99 adalah batas teknis penyimpanan, bukan rentang klinis.
- Tidak ada kolom/rule odontogram interaktif; yang disimpan hanya indikator gigi dan mulut di atas.

## Finalisasi, koreksi, dan riwayat

Finalisasi mengembalikan error validasi `observations` dan `missing_indicators` bila masih ada indikator tanpa nilai/status eksplisit. Finalisasi pertama membuat versi `1`. Snapshot versi menyimpan konteks sekolah/kelas, identitas pemeriksa/pencatat, IMT/versi rumus, dan seluruh observasi. Koreksi menyimpan snapshot versi berikutnya dan metadata audit; audit tidak menyalin nilai kesehatan.

```json
{
  "expected_version": 1,
  "reason": "Koreksi berdasarkan hasil ukur ulang",
  "observations": {
    "weight_kg": {"status": "recorded", "value": 49.5}
  }
}
```

`GET /students/{id}/health-history?semester_id=12` menampilkan histori terurut menurut tanggal. Operator Puskesmas dengan scope dapat melihat semua status pemeriksaan; sekolah hanya status published pada sekolah dalam scope; akun siswa memerlukan tautan `verified` yang cocok persis dengan ID siswa dan hanya melihat status published. Pembacaan detail, daftar, revisi, dan histori dicatat di audit log dengan aktor/resource/request ID tanpa menyalin isi kesehatan.

## Tes

```powershell
Set-Location apps/api
php artisan migrate --seed
php artisan test
vendor/bin/pint --test
```

Tes otomatis mencakup kalkulasi IMT, validasi periode/enrollment/duplikasi, daftar cakupan sudah/belum diperiksa, perbedaan nilai false dari `not_examined`, kelengkapan finalisasi, versi koreksi, publikasi, snapshot nama sekolah/kelas, dan akses sekolah/siswa.
