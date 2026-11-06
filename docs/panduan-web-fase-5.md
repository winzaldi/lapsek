# Panduan Operasional Web Fase 5

## Menyiapkan aplikasi

1. Jalankan API Laravel dan Next.js sesuai petunjuk di [README utama](../README.md).
2. Pastikan `LAOS_API_BASE_URL` pada `apps/web/.env.local` menunjuk ke API v1, misalnya `http://127.0.0.1:8000/api/v1`.
3. Admin Sistem awal dibuat dengan `php artisan laos:bootstrap-admin`. Setelah masuk, Admin dapat membuat Puskesmas, sekolah, periode, dan akun operator melalui menu administrasi.
4. Masuk memakai email dan kata sandi akun aktif. Token API disimpan pada cookie `HttpOnly` oleh server Next.js; jangan menyalin token ke browser atau log.

## Admin Sistem

- **Ringkasan** menampilkan jumlah Puskesmas, sekolah, akun pengguna, tahun ajaran, dan semester.
- **Puskesmas** membuat Puskesmas baru dan mengaktifkan/menonaktifkan catatan.
- **Sekolah** membuat sekolah dengan jenjang, jenis sekolah, NPSN opsional, alamat, dan Puskesmas pembina. Admin dapat mengaktifkan/menonaktifkan sekolah.
- **Tahun ajaran & semester** membuat tahun ajaran per sekolah dengan rentang tanggal dan status aktif, lalu membuat semester 1/2 di dalam tahun ajaran.
- **Akun & penugasan** membuat akun untuk semua peran, mengatur peran dan cakupan Puskesmas/sekolah, serta mengaktifkan/menonaktifkan akun. Penonaktifan akun mencabut token API-nya.
- **Akun Siswa** saat dibuat wajib ditautkan ke profil siswa yang ada dan Admin harus menyatakan identitas sudah diverifikasi. Pastikan prosedur identifikasi dilakukan sebelum menyetujui; tautan ini memberikan akses ke data kesehatan pribadi siswa.
- **Siswa & kelas** membuat data siswa dan enrollment ke sekolah/tahun ajaran, serta membuat kelas.

Urutan setup yang disarankan: buat Puskesmas → sekolah → tahun ajaran → semester → kelas/siswa → akun operator. Akun Operator Puskesmas diberi cakupan Puskesmas; akun Operator Sekolah/Guru diberi cakupan satu sekolah. Semua perubahan tetap divalidasi dan dicatat oleh API.

## Operator Puskesmas

1. Pilih sekolah binaan dan semester pemeriksaan. Pilihan hanya memuat sekolah dan periode dalam cakupan akun.
2. Ringkasan menampilkan jumlah sasaran, sudah diperiksa, belum diperiksa, dan cakupan.
3. Pada menu **Pemeriksaan**, pilih **Mulai pemeriksaan** di baris siswa yang belum diperiksa.
4. Isi tanggal dan nama petugas. Untuk setiap indikator, pilih **Sudah diperiksa** lalu isi hasil, atau pilih **Belum diperiksa** bila pemeriksaan memang tidak dilakukan. Nilai “Tidak” adalah hasil pemeriksaan, bukan status belum diperiksa.
5. Simpan draf. Tinjau kembali hasil, lalu lakukan **Finalisasi**. API akan menolak finalisasi jika ada indikator tanpa nilai atau status eksplisit.
6. Setelah pemeriksaan final ditinjau, pilih **Publikasikan** agar rapor dapat diakses sekolah.
7. Untuk hasil yang sudah diterbitkan, pilih **Koreksi**, isi alasan minimal 10 karakter, dan simpan. Koreksi membuat versi baru dan mencabut publikasi versi lama. Publikasikan ulang versi final yang telah dikoreksi.

## Operator Sekolah/Guru

- **Ringkasan** menampilkan jumlah siswa dengan enrollment aktif, kelas aktif, dan hasil pemeriksaan terpublikasi untuk semester yang dipilih.
- **Profil sekolah** menampilkan informasi sekolah dan Puskesmas pembina. Master sekolah hanya dapat diubah Admin Sistem.
- **Siswa & kelas** membuat siswa beserta enrollment, mengubah profil siswa, membuat/mengubah kelas, dan mengaktifkan/menonaktifkan kelas. Periksa sekolah, tahun ajaran, kelas, dan tanggal enrollment sebelum menyimpan.
- **Enrollment** menampilkan riwayat pendaftaran. Operator dapat mengakhiri enrollment atau memindahkan siswa; riwayat sebelumnya tidak dihapus. Tanggal dan konsistensi sekolah/periode tetap divalidasi API.
- **Pemeriksaan** hanya menampilkan hasil terpublikasi dalam cakupan sekolah. Pilih **Lihat hasil** untuk membaca detail indikator. Akun sekolah tidak dapat mengubah, memfinalisasi, memublikasikan, atau mengoreksi hasil klinis.

## Siswa

- Siswa hanya melihat histori yang tertaut dengan akun melalui tautan identitas berstatus terverifikasi.
- Halaman rapor menampilkan hasil per semester yang sudah dipublikasikan Puskesmas dan detail indikatornya. Akun siswa tidak dapat mencari profil siswa lain.
- Jika akun belum tertaut, minta bantuan Operator Sekolah/Admin Sistem; aplikasi tidak mencocokkan profil berdasarkan nama.

## Batas implementasi dan dukungan

- API tetap menjadi sumber otorisasi; menyembunyikan kontrol pada web bukan pengganti pemeriksaan scope/peran di backend.
- Tabel saat ini menampilkan halaman pertama (maksimum 50 item API); kontrol navigasi halaman belum tersedia di web.
- Rekap agregat, grafik, cetak ringkasan/rapor individu, dan ekspor CSV/XLSX/PDF tersedia pada menu/rapor **Laporan** sebagai Fase 6. Ekspor dibuat melalui antrean, disimpan privat selama 7 hari, dan dapat diunduh ulang dari riwayat selama cakupan akun masih berwenang. Worker `report-exports` dan Laravel scheduler harus aktif pada deployment. Ambang privasi dan masa retensi perlu persetujuan sebelum produksi; aplikasi Flutter siswa belum tersedia.
- UI master memungkinkan membuat dan mengaktifkan/menonaktifkan Puskesmas/sekolah; pengeditan rinci data master yang sudah dibuat, navigasi paginasi, dan alur reset kata sandi oleh Admin belum tersedia.
- Aturan klinis yang belum disetujui tidak dihitung otomatis. Status gizi dicatat sebagaimana diinput petugas dan IMT mengikuti aturan API.
- Sebelum pemakaian operasional, lakukan UAT dengan data sintetis bersama petugas Puskesmas dan sekolah; jangan memakai data kesehatan nyata untuk demo.

## Skenario pemeriksaan manual

1. Masuk sebagai Operator Puskesmas dengan cakupan sekolah tertentu; pastikan sekolah di luar cakupan tidak dapat dipilih.
2. Pilih semester, buat draf siswa sasaran, isi indikator sebagai “Tidak” dan “Belum diperiksa” pada dua indikator berbeda, lalu simpan.
3. Finalisasikan dan publikasikan. Verifikasi akun sekolah hanya melihat hasil setelah publikasi.
4. Koreksi satu indikator dengan alasan, pastikan hasil lama tidak kembali tampil sebagai terpublikasi, lalu publikasikan versi baru.
5. Masuk sebagai Operator Sekolah dari sekolah lain; pastikan rapor sekolah sebelumnya tidak terlihat dan API tetap menolak akses lintas scope.
6. Sebagai Operator Sekolah, ubah profil siswa dan kelas; akhiri enrollment dan uji perpindahan dengan tanggal/periode tujuan valid dan tidak valid.
7. Masuk sebagai Admin Sistem; buat Puskesmas, sekolah, tahun ajaran, semester, dan akun dengan cakupan berbeda. Pastikan akun sekolah hanya melihat sekolah tugasnya, dan akun Puskesmas melihat sekolah binaannya.
8. Buat akun Siswa dari profil yang identitasnya telah diverifikasi; pastikan tautan menjadi verified, siswa hanya melihat rapor miliknya, dan akun siswa lain tidak dapat diakses.

## Fase 6: Laporan

- Admin Sistem dan Operator Puskesmas dapat melihat rekap lintas sekolah dalam cakupannya; Operator Sekolah/Guru hanya melihat sekolah tugasnya. Siswa tetap melihat rapor individunya di halaman **Rapor kesehatan saya**.
- Pilih sekolah (atau semua sekolah dalam cakupan), tahun ajaran, semester atau satu tahun, jenjang, dan kelas. Operator Puskesmas/Admin menghitung pemeriksaan **Final** atau **Terpublikasi**; Operator Sekolah hanya menghitung hasil **Terpublikasi**. Draf dan hasil yang belum boleh dilihat tidak masuk rekap.
- Grafik menampilkan cakupan pemeriksaan per sekolah/kelas. Grup dengan kurang dari 5 sasaran disembunyikan sebagai perlindungan kelompok kecil. Ambang ini adalah default implementasi dan perlu dikonfirmasi pemilik kebijakan sebelum produksi.
- Ekspor CSV mematuhi filter/cakupan, membatasi maksimal 5.000 pemeriksaan, menetralkan nilai yang berpotensi menjadi formula spreadsheet, dan mencatat permintaan ekspor pada audit log. Operator Sekolah hanya dapat mengunduh hasil terpublikasi.
- Ekspor saat ini dikirim langsung saat diminta dan tidak disimpan di server. Jika unduhan gagal karena batas baris, sempitkan filter sekolah, kelas, atau periode.
