# Product Requirements Document (PRD)
# Manajemen Data Anak Yatim - V1

| Informasi Dokumen | Detail |
|---|---|
| Versi Dokumen | 1.0 |
| Tanggal | 2026-10-02 |
| Status | Final |
| Basis Sumber | Implementasi Laravel aktual + keputusan produk yang telah disepakati |
| Stack V1 | Laravel 13, Filament 5, Livewire/Blade, Spatie Permission, Eloquent, PHP-backed Enum |
| Catatan Teknis | React/Inertia bukan bagian dari V1 |

---

## 1. Tujuan Sistem

Sistem ini dirancang untuk mendukung pengelolaan data anak yatim secara internal oleh pemerintah, dengan fokus pada pengelolaan data, proses aplikasi bantuan, review, verifikasi, revisi, serta audit keputusan. Sistem harus menjadi alat operasional yang konsisten untuk menjaga kelengkapan data, akuntabilitas proses, dan keteraturan alur kerja antar level organisasi.

Tujuan utama V1 adalah:

- mengelola data anak dan status administratifnya secara terstruktur;
- membolehkan pengajuan bantuan berdasarkan `period_year` atau tahun anggaran/program bantuan;
- memastikan satu child hanya memiliki satu aplikasi per `period_year`;
- menyediakan alur review di level Kelurahan, Kecamatan, dan Kabupaten/Kesra;
- menjaga keputusan revisi dan penolakan sesuai aturan yang telah disepakati;
- menyediakan jejak audit yang jelas untuk setiap perubahan status.

---

## 2. Masalah Bisnis yang Diselesaikan

Dunia operasional pemerintahan membutuhkan sistem yang dapat menangani data anak yatim secara tertib dan terukur. Tanpa sistem yang terorganisir, pengelolaan data rentan terhadap:

- duplikasi data anak atau aplikasi pada tahun yang sama;
- kehilangan konteks ketika data diperbaiki atau ditolak;
- proses review yang tidak terdokumentasi dengan jelas;
- inkonsistensi keputusan antar level organisasi;
- tidak adanya standar bahwa revisi harus diproses ulang dan tidak diperlakukan seperti penolakan;
- tidak ada pemisahan yang jelas antara review Kecamatan dan verifikasi Kabupaten/Kesra.

Sistem ini dibuat untuk menjawab kebutuhan tersebut dengan pendekatan yang bersifat internal, administrasi, dan berorientasi akuntabilitas.

---

## 3. Ruang Lingkup V1

### 3.1 Dalam lingkup V1

- pengelolaan data dasar anak dan relasinya;
- pengelolaan aplikasi bantuan per child per `period_year`;
- alur review dan validasi oleh admin Kelurahan, Kecamatan, dan Kabupaten/Kesra;
- revisi data dan dokumen dengan status `REVISION_REQUIRED`;
- penolakan terminal dengan status `REJECTED`;
- audit trail perubahan status aplikasi;
- pengaturan akses berbasis role dan scope organisasi;
- pelaporan dasar untuk pengelolaan operasional dan administrasi.

### 3.2 Di luar lingkup V1

- aplikasi mobile native;
- React/Inertia sebagai front-end utama;
- integrasi ke bank, pembayaran, atau sistem keuangan eksternal;
- fitur analitik prediktif atau AI;
- pemrosesan bantuan lintas instansi yang tidak terikat pada hierarki pemerintah yang ditetapkan.

---

## 4. Aktor dan Tanggung Jawab

### 4.1 Role yang berlaku

| Role | Tanggung Jawab Utama |
|---|---|
| `Super Admin` | pengelolaan sistem, konfigurasi organisasi, akses pengguna, pengelolaan master data umum, pengawasan operasional global |
| `admin_kelurahan` | input data anak, pengajuan aplikasi, pengelolaan dokumen, revisi data yang dibutuhkan di wilayahnya |
| `admin_kecamatan` | review kelengkapan dan validasi data/dokumen sebelum diteruskan ke Kabupaten/Kesra |
| `admin_kabupaten` | verifikasi akhir, keputusan penerimaan, revisi lanjutan, dan penolakan terminal |

### 4.2 Hierarki organisasi

Sistem menggunakan struktur organisasi sebagai berikut:

- Kabupaten
  - Kecamatan
    - Kelurahan/Desa

Hal ini bersesuaian dengan model organisasi dan akses data yang telah ditetapkan: `regency → district → village`.

### 4.3 Data ownership

- Data dimiliki Kabupaten/Kesra.
- Unit kerja di bawahnya menganut prinsip akses sesuai wilayah dan peran.
- Akses tidak bebas lintas wilayah; scope harus sesuai organisasi pengguna dan wilayah kerja.

---

## 5. Workflow Bisnis Utama

### 5.1 Alur umum

1. Admin Kelurahan mengelola data anak dan data rumah tangga terkait.
2. Admin Kelurahan mengajukan aplikasi bantuan untuk anak tertentu pada `period_year` yang relevan.
3. Aplikasi masuk ke proses review Kecamatan.
4. Kecamatan mengecek kelengkapan, konsistensi, dan alasan kelayakan data/dokumen.
5. Jika ada masalah, aplikasi masuk ke `REVISION_REQUIRED` dan dikembalikan ke Kelurahan.
6. Setelah data/dokumen dinyatakan cukup, aplikasi diteruskan ke Kabupaten/Kesra.
7. Kabupaten/Kesra melakukan verifikasi akhir.
8. Jika disetujui, aplikasi tetap tercatat sebagai status final yang sesuai ketentuan;
   jika ditolak, status menjadi `REJECTED` dan bersifat terminal.

### 5.2 Aturan transisi status yang ditetapkan

- `DRAFT` → `SUBMITTED` setelah aplikasi diajukan.
- `SUBMITTED` → `REVISION_REQUIRED` jika ada kesalahan data/dokumen.
- `SUBMITTED` / `VALIDATED` → `REJECTED` hanya bila keputusan akhir memang ditolak; tidak untuk koreksi data kecil.
- `VALIDATED` → `VERIFIED` saat Kabupaten/Kesra memverifikasi.
- `REVISION_REQUIRED` → `SUBMITTED` ketika perbaikan selesai dan aplikasi diajukan ulang.
- `REJECTED` bersifat terminal dan tidak dapat dilanjutkan.

### 5.3 Aturan revisi lintas level

- Kesalahan data atau dokumen harus menggunakan `REVISION_REQUIRED`, bukan `REJECTED`.
- Apabila revisi datang dari Kabupaten setelah Kelurahan memperbaiki data, maka aplikasi wajib kembali ke Kecamatan untuk review ulang.
- Dengan kata lain, revisi yang ditetapkan di level yang lebih tinggi tidak boleh bypass mekanisme review level sebelumnya.

### 5.4 `current_review_level`

`current_review_level` digunakan untuk membedakan tujuan review dan memastikan setiap keputusan review diarahkan ke level yang sesuai. Hal ini penting agar proses review tidak dibingungkan lintas level organisasi dan tetap sesuai dengan hirarki pemerintahan.

---

## 6. Pengelolaan Data Anak

### 6.1 Data dasar anak

Data anak harus mencakup identitas dasar seperti:

- nama;
- NIK;
- tempat lahir;
- tanggal lahir;
- jenis kelamin;
- alamat dan hubungan keluarga yang relevan;
- relasi keluarga atau keterkaitan rumah tangga.

### 6.2 Data relasional

Data anak terkait dengan:

- household;
- address;
- relationships;
- applications;
- document set terkait aplikasi.

### 6.3 Setiap child memiliki satu aplikasi per period_year

Kebijakan inti yang harus dipatuhi:

- satu child hanya boleh memiliki satu aplikasi untuk satu `period_year`;
- `period_year` adalah tahun anggaran atau tahun program bantuan;
- pengajuan ganda untuk tahun yang sama tidak diperkenankan;
- aturan ini diterapkan untuk menjaga integritas data dan mencegah duplikasi administrasi.

---

## 7. Pengajuan Bantuan Berdasarkan Tahun Anggaran / Program

### 7.1 Definisi tahun anggaran

`period_year` adalah titik referensi utama untuk program bantuan. Setiap aplikasi harus terkait dengan tahun anggaran/program yang sedang berjalan.

### 7.2 Kebutuhan bisnis

- Admin Kelurahan hanya dapat membuat atau mengajukan aplikasi dalam tahun yang sesuai dengan periode yang aktif;
- aplikasi dapat ditelusuri berdasarkan tahun bantuan yang diusulkan;
- history aplikasi harus tetap dapat ditampilkan per child dan per tahun.

### 7.3 Batasan

- aplikasi untuk tahun yang sama akan dicek keunikan (one child one application per period_year);
- aplikasi yang tidak relevan terhadap tahun yang sedang berjalan tidak boleh diproses secara otomatis.

---

## 8. Dokumen Persyaratan

Dokumen persyaratan harus didukung melalui model `Document` dan relasi `DocumentCategory` serta versioning yang sesuai. Pada V1, kebutuhan utama adalah:

- pengunggahan dokumen yang relevan per aplikasi;
- kemampuan revisi dokumen tanpa menimpa data versi lama;
- jejak perubahan dokumen yang dapat dipertanggungjawabkan;
- kontrol hak akses agar dokumen hanya diunggah/ditinjau oleh peran yang berwenang.

### 8.1 Prinsip pengelolaan dokumen

- Dokumen harus dapat dikelola per aplikasi.
- Kesalahan data/dokumen tidak otomatis menghasilkan penolakan terminal.
- Revisinya harus direkam, bukan dihapus secara diam-diam.
- File versi lama harus tetap tersedia untuk audit dan pembanding.

### 8.2 Kategori dokumen

Dokumen yang termasuk dalam siklus aplikasi harus dapat dikategorikan dan dikelola secara bermakna, dengan sistem untuk melihat status, versi, dan riwayat upload.

---

## 9. Review Kecamatan

Kecamatan adalah level review operasional yang berada di tengah antara kelurahan dan kabupaten.

### 9.1 Tanggung jawab utama

- mengecek kelengkapan data;
- memastikan data dan dokumen konsisten dan dapat diproses;
- menilai kelayakan awal aplikasi;
- mengembalikan aplikasi ke Kelurahan bila ada kekurangan atau kesalahan data;
- menyiapkan aplikasi untuk verifikasi berikutnya di Kabupaten/Kesra.

### 9.2 Kriteria review

Review Kecamatan harus fokus pada:

- validitas data anak;
- konsistensi data rumah tangga / alamat;
- kelengkapan dokumen;
- kesesuaian aplikasi dengan area kerja dan periode program;
- apakah data siap untuk diverifikasi di Kabupaten/Kesra.

---

## 10. Verifikasi Kabupaten / Kesra

Kabupaten/Kesra adalah level verifikasi akhir dan pengambilan keputusan yang lebih tinggi.

### 10.1 Tanggung jawab utama

- memeriksa hasil review Kecamatan;
- memastikan keputusan final didasarkan pada data yang valid;
- menolak aplikasi hanya bila keputusan final memang tidak memenuhi syarat;
- menilai apakah kesalahan data atau dokumen harus dikembalikan sebagai `REVISION_REQUIRED` bukan `REJECTED`.

### 10.2 Aturan penting

- `REJECTED` bersifat terminal.
- Data atau dokumen yang salah harus diproses lewat `REVISION_REQUIRED`.
- Revisi dari Kabupaten setelah Kelurahan memperbaiki data wajib melewati Kecamatan kembali.

---

## 11. Revisi, Rejection, dan Auditability

### 11.1 Revisi data / dokumen

Revisi data atau dokumen harus dilakukan pada status `REVISION_REQUIRED` dan tidak diperlakukan sebagai penolakan terminal.

Tujuan revisi:

- memperbaiki kesalahan administrasi;
- melengkapi dokumen yang belum sesuai;
- memperbaiki data yang tidak konsisten.

### 11.2 Rejection

Penolakan terminal hanya sah bila data atau syarat yang ada benar-benar tidak memenuhi ketentuan akhir. Dalam situasi ini, status menjadi `REJECTED` dan tidak dapat dilanjutkan.

### 11.3 Auditability

Setiap perubahan status harus dicatat secara otomatis dan konsisten. Model `WorkflowHistory` saat ini merepresentasikan kebutuhan ini dengan kolom seperti:

- `application_id`;
- `from_status`;
- `to_status`;
- `action`;
- `note`;
- `acted_by`;
- `acted_at`.

Hal ini memungkinkan traceability dari keputusan review sampai keputusan terakhir.

---

## 12. Authorization Secara Konseptual

### 12.1 Prinsip otorisasi

Otorisasi didasarkan pada kombinasi:

- role pengguna;
- scope organisasi;
- kemampuan untuk mengakses data child atau aplikasi tertentu;
- status aplikasi yang sedang diproses.

### 12.2 Model akses

- `super_admin` memiliki visibilitas sistem dan konfigurasi tinggi.
- `admin_kelurahan` hanya mengakses area pendataan dan aplikasi dalam wilayah yang menjadi tanggung jawabnya.
- `admin_kecamatan` mengakses data yang relevan dengan level reviewnya.
- `admin_kabupaten` dapat mengakses aplikasi yang sudah masuk ke level verifikasi akhir sesuai peran.

### 12.3 Pengecekan hak akses

Dalam implementasi saat ini, pemikiran autentikasi dan otorisasi didukung oleh:

- Spatie Permission;
- policy seperti `ApplicationPolicy`;
- scope data `canAccessChild` dan `canAccessApplication`.

---

## 13. Reporting

### 13.1 Laporan yang diperlukan V1

Laporan dasar harus memungkinkan pengelolaan operasional seperti:

- jumlah aplikasi per `period_year`;
- jumlah aplikasi per status;
- jumlah data anak per wilayah;
- jumlah aplikasi yang masuk ke review dan verifikasi;
- jumlah revisi dan penolakan per unit organisasi;
- performa penyelesaian proses di Kelurahan, Kecamatan, dan Kabupaten/Kesra.

### 13.2 Pemanfaat laporan

- Super Admin: melihat ringkasan sistem dan pola status secara umum.
- Kabupaten/Kesra: melihat kepatuhan proses dan keputusan akhir.
- Kecamatan/Kelurahan: memantau pekerjaan operasional per wilayah.

---

## 14. Kebutuhan Non-Fungsional

### 14.1 Teknologi V1

- Laravel 13
- Filament 5
- Livewire/Blade
- Spatie Permission
- Eloquent ORM
- PHP-backed Enum
- React/Inertia tidak termasuk dalam V1

### 14.2 Kebutuhan kualitas

- sistem harus mudah dioperasikan oleh admin pemerintah internal;
- status dan transisi harus jelas dan konsisten;
- proses audit harus dapat ditelusuri;
- akses harus dibatasi sesuai organisasi dan role;
- perubahan status tidak boleh melanggar aturan workflow yang ditetapkan.

---

## 15. Security Requirements

- Sistem internal pemerintah harus menjaga data sensitif sesuai kebijakan internal.
- Akses harus dibatasi hanya untuk peran dan wilayah yang relevan.
- Data dokumen harus disimpan dengan mekanisme yang aman dan tidak dibuka secara luas.
- Setiap status perubahan harus tercatat melalui audit trail.
- Penolakan terminal dan revisi harus dibedakan secara jelas untuk menjaga integritas proses.

---

## 16. Batasan dan Out-of-Scope V1

- sistem internal pemerintah, bukan portal publik;
- tidak mencakup integrasi ke sistem keuangan, bank, atau transfer dana;
- tidak mencakup modul mobile native;
- tidak mencakup implementasi React/Inertia;
- tidak mencakup analitik lanjutan atau integrasi dengan sistem eksternal di V1.

---

## 17. Success Criteria

Sistem V1 dianggap berhasil jika:

1. data anak dikelola dengan struktur yang konsisten dan terikat pada unit organisasi yang benar;
2. aplikasi bantuan dapat dikelola per `period_year` dan tidak terjadi duplikasi per child untuk tahun yang sama;
3. review Kecamatan dan verifikasi Kabupaten/Kesra berjalan sesuai alur yang ditetapkan;
4. revisi menggunakan `REVISION_REQUIRED`, sementara `REJECTED` tetap terminal;
5. semua perubahan status terdokumentasi dengan traceable audit trail;
6. role dan otorisasi mengikuti hierarki organisasi `regency → district → village`;
7. pemerintah dapat menggunakan sistem sebagai alat operasional administrasi internal yang andal.

---

## 18. Requirement Classification

### Confirmed requirements

- Sistem adalah sistem internal pemerintah.
- Data dimiliki Kabupaten/Kesra.
- Role: Super Admin, admin Kelurahan, admin Kecamatan, admin Kabupaten.
- Hierarki organisasi: regency → district → village.
- Satu child hanya memiliki satu application untuk satu `period_year`.
- `period_year` adalah tahun anggaran/program bantuan.
- `REJECTED` bersifat terminal.
- Kesalahan data/dokumen menggunakan `REVISION_REQUIRED`, bukan `REJECTED`.
- Revisi dari Kabupaten setelah diperbaiki Kelurahan wajib melewati Kecamatan kembali.
- `current_review_level` digunakan untuk membedakan tujuan review.
- V1 menggunakan Laravel 13, Filament 5, Livewire/Blade, Spatie Permission, Eloquent, PHP-backed Enum.
- React/Inertia bukan bagian dari V1.

### Assumptions

- Pengelolaan data dilakukan oleh unit kerja pemerintah sesuai wilayah administrasi.
- Data anak disimpan dan diproses di lingkungan internal yang relevan dengan otoritas pemerintah.
- Super Admin adalah pengelola sistem dan bukan pihak yang menjalankan review harian.
- `WorkflowHistory` menjadi mekanisme utama untuk audit status aplikasi.

### TBD

- Detail struktur data anak yang lebih spesifik masih perlu disepakati lebih lanjut di level implementasi.
- Spesifikasi dokumen program tertentu per bantuan belum sepenuhnya dijabarkan pada V1.
- Rancangan laporan dan dashboard final untuk tiap role masih perlu dikonfirmasi.
- Kriteria detail kebutuhan program multi-tahun atau program khusus belum ditetapkan.
- Perlu konfirmasi apakah ada kebutuhan UI tambahan untuk laporan, notifikasi, atau operasional khusus di luar proses inti V1.

---

*Dokumen PRD final untuk project manajemen data anak yatim - V1*
