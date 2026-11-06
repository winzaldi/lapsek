# Panduan Mobile Siswa LAOS — Fase 7

## Cakupan

Aplikasi Flutter mendukung akun Siswa: login email/kata sandi, pemulihan sesi saat aplikasi dibuka kembali, permintaan reset kata sandi, pemeriksaan tautan profil terverifikasi, rapor/history yang telah dipublikasikan, filter tahun ajaran dan semester, paginasi, refresh, detail indikator, logout, serta penanganan sesi kedaluwarsa dan jaringan gagal.

Aplikasi tidak menyimpan rapor ke cache lokal. Token Sanctum disimpan dengan `flutter_secure_storage` (Android encrypted storage dan iOS Keychain); logout menghapus token lokal meskipun server tidak dapat dihubungi. Profil siswa hanya berasal dari `student_id` pada `/auth/me`; aplikasi tidak mencari atau menautkan siswa berdasarkan nama. Jika tautan belum diverifikasi, aplikasi tidak meminta riwayat.

## Menjalankan Android Emulator

1. Pastikan API Laravel aktif dan database sudah dimigrasikan:

   ```powershell
   cd apps/api
   php artisan migrate
   php artisan serve --host 0.0.0.0 --port 8000
   ```

2. Pada terminal lain, jalankan aplikasi. Android Emulator mengakses host pengembang melalui `10.0.2.2`:

   ```powershell
   cd apps/mobile
   flutter pub get
   flutter run --dart-define=LAOS_API_BASE_URL=http://10.0.2.2:8000/api/v1
   ```

   Manifest mengizinkan HTTP hanya untuk varian Android debug. Jangan gunakan HTTP atau `usesCleartextTraffic` untuk build produksi.

3. Buat akun berperan Siswa di web Admin Sistem dan selesaikan verifikasi tautan akun ke profil siswa. Masuk dengan email/kata sandi akun tersebut.

## iOS dan deployment

- Atur `LAOS_API_BASE_URL` ke URL API HTTPS yang sertifikatnya dipercaya perangkat:

  ```sh
  flutter run --dart-define=LAOS_API_BASE_URL=https://api.example.sch.id/api/v1
  ```

- `LAOS_API_BASE_URL` harus berakhir pada prefix `/api/v1`; jangan memasukkan token/secret ke dalam `--dart-define`.
- iOS build dan signing memerlukan macOS/Xcode. Gunakan sertifikat dan profile distribusi organisasi; jangan mengaktifkan ATS exception untuk produksi.
- Untuk paket Android produksi, siapkan signing key organisasi di luar repository sebelum menjalankan `flutter build appbundle --release`. Kunci debug hanya untuk pengujian.
- Atur izin akun dan tautan siswa melalui Operator Sekolah/Admin Sistem; aplikasi mobile tidak menyediakan proses pengaitan identitas.

## Uji lokal

```powershell
cd apps/mobile
flutter analyze
flutter test
flutter build apk --debug --dart-define=LAOS_API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Tes widget menggunakan API/token store palsu dan tidak membutuhkan data kesehatan sungguhan. Uji integrasi perangkat harus menggunakan akun serta data sintetis:

1. Login akun siswa dengan tautan `verified`; pastikan histori dan detail hanya berisi siswa tersebut.
2. Coba buka riwayat dengan ID siswa lain lewat API; hasil harus 404 dan aplikasi tidak menawarkan input ID.
3. Login akun dengan tautan `pending`; aplikasi hanya menampilkan instruksi menghubungi sekolah dan tidak meminta endpoint riwayat.
4. Publikasikan hasil baru lalu refresh; draf dan hasil final yang belum dipublikasikan tidak tampil.
5. Uji tahun ajaran/semester, halaman histori berikutnya, offline, timeout, token kedaluwarsa, akun nonaktif, reset kata sandi, dan logout.
6. Setelah logout atau token kedaluwarsa, pastikan sesi/token lokal tidak dapat dipakai kembali. Jangan menguji dengan NISN/nama/data klinis produksi.

## Batas rilis

APK debug dapat dibangun dari Windows; pembuatan dan penandatanganan iOS memerlukan macOS/Xcode. Sebelum distribusi, pemilik produk perlu menyetujui kebijakan privasi, retensi, aksesibilitas dan perangkat/versi OS yang didukung serta melaksanakan UAT keamanan pada perangkat fisik.
