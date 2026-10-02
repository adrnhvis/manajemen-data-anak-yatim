# Arsitektur Sistem
# Manajemen Data Anak Yatim - V1

| Informasi Dokumen | Detail |
|---|---|
| Versi Dokumen | 1.0 |
| Tanggal | 2026-10-02 |
| Status | Acuan arsitektur V1; bagian yang belum ada diberi label Planned/TBD |
| Sumber | `docs/PRD.md`, `docs/DOMAIN.md`, dan implementasi repository saat ini |

---

## 1. Architectural Overview

Aplikasi V1 adalah monolith Laravel untuk administrasi internal. Satu aplikasi menangani autentikasi berbasis sesi, panel administrasi, aturan otorisasi, operasi bisnis, persistence, dan penyimpanan dokumen. Tidak ada API bisnis terpisah yang terdaftar pada routing saat ini.

Alur tanggung jawab untuk operasi bisnis:

```text
Pengguna
  -> Filament (presentation/admin, Livewire, Blade)
  -> Policy (izin aksi dan batas resource)
  -> Service (operasi bisnis dan konsistensi)
  -> Model / Eloquent
  -> Database dan private file storage
```

Urutan ini menjelaskan pembagian tanggung jawab. Service juga memeriksa otorisasi ketika operasi sensitif dipanggil, sehingga pemanggilan service tidak bergantung pada UI sebagai satu-satunya penjaga akses.

### Stack V1

- Laravel 13
- Filament 5 sebagai panel admin
- Livewire untuk interaksi panel dan Blade untuk rendering
- Eloquent ORM
- Spatie Laravel Permission untuk role dan permission
- PHP Backed Enum untuk nilai domain terbatas
- SQLite untuk development dan konfigurasi test lokal
- MySQL untuk production

---

## 2. Application Layers

### 2.1 Filament, Livewire, dan Blade: presentation/admin layer

`AdminPanelProvider` mendefinisikan panel `admin`, path `/admin`, login, middleware sesi/web, serta discovery untuk resources, pages, dan widgets. Filament merender antarmuka dengan Blade dan memakai Livewire untuk interaksi.

**Kondisi saat ini:** provider panel tersedia, tetapi direktori `app/Filament/Resources`, `Pages`, dan `Widgets` belum ada di repository. Resource dan alur administrasi operasional karena itu masih **Planned**; jangan menganggap fitur CRUD atau action workflow sudah tersedia.

Tanggung jawab presentation layer:

- mengumpulkan input dan menampilkan hasil operasi;
- menampilkan affordance berdasarkan policy;
- memanggil service untuk operasi bisnis;
- tidak menentukan sendiri transisi status, menulis history, atau mengelola versi dokumen.

### 2.2 Policy: authorization boundary

Policy mengevaluasi apakah user boleh menjalankan aksi terhadap resource. Policy yang ada antara lain `ApplicationPolicy`, `ChildPolicy`, dan `DocumentPolicy`.

Keputusan aksi terhadap resource harus memenuhi invariant:

```text
permission AND organization scope AND workflow state
```

Ketiganya diperlukan bersama-sama untuk operasi yang terikat pada resource:

- **Permission:** hak aksi dari Spatie Permission, misalnya `validate applications`.
- **Organization scope:** resource berada dalam wilayah kerja user, melalui `User::canAccessChild()` / `canAccessApplication()`.
- **Workflow state:** status aplikasi memungkinkan aksi yang diminta, misalnya hanya `validated` yang dapat diverifikasi.

Policy juga membatasi pembuatan atau pemindahan alamat child ke wilayah yang masih berada dalam scope user. Untuk query daftar, filter collection seperti `scopeForUser()` tetap diperlukan; `viewAny` hanya memastikan izin melihat dan tidak dengan sendirinya menyaring setiap row.

### 2.3 Service: business operation boundary

Service mengorkestrasi operasi yang harus konsisten lintas model, permission, file storage, dan audit trail.

- `ApplicationWorkflowService` adalah satu-satunya boundary untuk operasi workflow aplikasi: submit, request revision, validate, verify, dan reject.
- `DocumentService` adalah boundary untuk upload, replace, delete, dan download dokumen.

Workflow business operation harus selalu melalui `ApplicationWorkflowService`. Operasi dokumen harus selalu melalui `DocumentService`. Filament Action atau callback UI tidak boleh mengubah status, membuat workflow history, menulis versi, atau mengelola file secara langsung. Jika service dipanggil dari jalur lain, service tetap bertanggung jawab menjaga invariant dan memeriksa otorisasi.

**Kondisi saat ini:** kedua service ada. Belum ada Filament resources yang dapat diverifikasi sebagai pemanggil service; integrasi UI tersebut berstatus **Planned**.

### 2.4 Model dan Eloquent: domain representation serta persistence

Model di `app/Models` merepresentasikan data dan relasi domain: `Child`, `Person`, `Household`, `Address`, `Application`, `Document`, `DocumentVersion`, `OrganizationUnit`, `WorkflowHistory`, dan lainnya.

Eloquent bertanggung jawab untuk query, relasi, casts, soft delete, dan persistence. Constraint database tetap menjadi penjaga integritas yang berlaku untuk seluruh jalur tulis. Model bukan tempat menaruh alur multi-langkah UI atau menggantikan service workflow.

### 2.5 Enum

PHP Backed Enum memberi tipe domain eksplisit bagi status dan kategori terbatas, termasuk `ApplicationStatus`, `OrganizationUnitType`, `PersonStatus`, `RelationshipType`, `LivingWith`, `Gender`, dan `BankAccountHolderType`.

Enum digunakan melalui casts model dan nilai database yang sesuai. Tambahkan nilai baru dengan menyelaraskan enum, migrasi/schema, validasi, policy, dan workflow terkait.

### 2.6 Exception

`InvalidWorkflowTransitionException` menandai transisi yang tidak diizinkan, termasuk status asal yang salah atau kegagalan otorisasi dalam workflow. `ValidationException` digunakan untuk input yang tidak valid (misalnya note wajib atau file tidak memenuhi aturan), sementara kegagalan penyimpanan file menghasilkan `RuntimeException`.

Exception dipropagasikan ke Laravel untuk ditangani sebagai error/validation response. Pemetaan khusus untuk pengalaman pengguna Filament merupakan **Planned** bersama UI operasionalnya.

---

## 3. Aturan Komunikasi Antar-Layer dan Dependency Direction

Dependency mengalir dari lapisan luar ke dalam:

```text
Filament -> Policy / Service -> Model -> Eloquent -> Database
                                  Service -> Storage
```

Aturan komunikasi:

1. Filament boleh menggunakan policy untuk visibilitas/otorisasi aksi dan memanggil service untuk operasi bisnis.
2. Policy boleh membaca user, resource, permission, scope organisasi, serta state; policy tidak menulis data atau menjalankan transisi.
3. Service mengatur urutan operasi, validasi domain yang lintas entitas, transaksi, otorisasi server-side, dan side effect yang dibutuhkan.
4. Model mendefinisikan representasi, relasi, casts, dan operasi persistence; model tidak memanggil Filament.
5. Model tidak bergantung pada presentation layer. Service juga tidak bergantung pada komponen UI.
6. Operasi workflow tidak boleh ditulis sebagai logika langsung dalam Filament Action atau controller.
7. Operasi file dokumen tidak boleh melewati `DocumentService`.

Controller dan route Laravel yang tersedia saat ini hanya mencakup route `/` dan endpoint health bawaan. Penambahan HTTP surface selain panel adalah **Planned** dan tetap harus menggunakan policy/service yang sama, bukan membuat aturan bisnis duplikat.

---

## 4. Database Architecture

### 4.1 Engine per environment

- **Development:** SQLite, dengan default `DB_CONNECTION=sqlite` dan database file `database/database.sqlite`.
- **Testing:** SQLite in-memory (`:memory:`) sesuai `phpunit.xml`.
- **Production:** MySQL, dipilih melalui konfigurasi environment (`DB_CONNECTION=mysql`). Konfigurasi koneksi MySQL tersedia di `config/database.php`.

Migrasi Laravel adalah sumber schema. Gunakan constraint untuk relasi dan integritas data, termasuk unique `children.nik`, unique `(child_id, period_year)` pada applications, unique kategori dokumen, serta unique `(document_id, version_number)` untuk versi.

### 4.2 Domain data utama

- Data administrasi: `provinces`, `regencies`, `districts`, `villages`, dan `organization_units`.
- Identitas dan keluarga: `children`, `persons`, `child_relationships`, `households`, dan `addresses`.
- Proses bantuan: `applications`, `workflow_histories`, dan `bank_accounts`.
- Dokumen: `document_categories`, `documents`, dan `document_versions`.
- Sistem dan audit: `users`, tabel Spatie Permission, serta `audit_logs`.

ULID dipakai oleh sebagian besar entitas domain. **Perlu ditangani sebelum production:** migrasi awal `users` membuat primary key integer, sedangkan model `User` memakai `HasUlids` dan beberapa foreign key ke user dideklarasikan sebagai `foreignUlid`. Konsistensi tipe primary/foreign key ini harus direkonsiliasi dan diuji pada kedua engine database; dokumen ini tidak menganggap mismatch tersebut telah diselesaikan.

### 4.3 Organization scoping

`OrganizationUnit` memetakan tingkat `regency`, `district`, atau `village` dan mempunyai hubungan parent-child. Wilayah administrasi referensi dibentuk `Regency -> District -> Village`; unit organisasi menghubungkan user ke cakupan wilayah tersebut.

Query child menggunakan alamat dan relasi wilayah untuk membatasi hasil. Scope application mengikuti scope child. Implementasi mengenali `super_admin` atau user tanpa `organization_unit_id` sebagai tanpa filter wilayah pada beberapa query; perilaku user tanpa unit harus dikonfirmasi/diatur eksplisit sebelum dipakai sebagai pola akses production.

Jangan mengandalkan filter UI untuk isolasi data. Terapkan scope pada query dan pemeriksaan resource pada policy/service.

---

## 5. Authorization Architecture

Otorisasi memakai Spatie Laravel Permission, model `User`, policy Laravel, dan pemeriksaan scope wilayah.

Garis besar permission dari seeder:

- `admin_kelurahan`: entri child, aplikasi, dan dokumen;
- `admin_kecamatan`: review, validasi, dan permintaan revisi;
- `admin_kabupaten`: verifikasi, penolakan, dan laporan;
- `super_admin`: administrasi sistem dan visibilitas umum tertentu; tidak diberi permission `verify applications` oleh seeder.

Nama role bukan pengganti permission checks. Untuk aksi resource, permission harus dipadukan dengan organization scope dan status yang diperbolehkan, sesuai invariant di bagian 2.2. Akses global super admin tidak otomatis berarti memiliki kewenangan keputusan bisnis.

**TBD:** cakupan organisasi bagi user tanpa unit, aturan peran lintas unit, dan perilaku ketika unit nonaktif perlu disepakati serta diuji sebagai aturan eksplisit.

---

## 6. Workflow Architecture

### 6.1 Application state

`Application.status` adalah state aplikasi saat ini. Nilai Backed Enum yang didukung:

```text
draft -> submitted -> validated -> verified
                    -> revision_required -> submitted
                    -> rejected
             validated -> rejected
```

Transisi otoritatif berada pada `ApplicationWorkflowService`, bukan pada komponen presentation. Service menjalankan transaksi database, mengunci row aplikasi (`lockForUpdate`), memeriksa status asal dan policy, mengubah state/timestamp, lalu menulis `WorkflowHistory` dalam transaksi yang sama.

`request_revision` dan `reject` memerlukan note non-kosong. `rejected` tidak memiliki transisi keluar. Status `verified` merupakan hasil akhir positif yang tersedia dalam implementasi.

### 6.2 `current_review_level`

`status` menjawab **state siklus hidup aplikasi**. `current_review_level` menjawab **level organisasi yang menjadi tujuan review berikutnya**. Keduanya bukan konsep yang sama.

Pemetaan proses yang disepakati:

- `SUBMITTED` -> menunggu review Kecamatan;
- `VALIDATED` -> menunggu verifikasi Kabupaten/Kesra.

Penanda level dibutuhkan agar antrean dan kewenangan tujuan review dapat dinyatakan secara eksplisit, terutama saat aplikasi direvisi pada level yang lebih tinggi dan harus kembali melalui Kecamatan. Dengan begitu revisi tidak cukup dipahami sebagai perubahan state saja; tujuan pemrosesan berikutnya juga harus terjaga.

**Kondisi saat ini:** tidak ada kolom atau enum `current_review_level` di model/migrasi. Status saat ini secara implisit membedakan dua tahap utama di atas, tetapi tidak menyimpan tujuan review terpisah. Implementasi persistensi/routing `current_review_level`, termasuk aturan revisi Kabupaten kembali ke Kecamatan, adalah **Planned/TBD** dan harus ditambahkan melalui perubahan schema dan workflow sebelum dianggap ditegakkan oleh aplikasi.

---

## 7. Document Storage and Versioning

`DocumentService` menggunakan disk Laravel `local`, yang dikonfigurasi ke `storage/app/private`. File disimpan dengan path di bawah `applications/{application_id}/documents/{document_id}/...`; download melalui service setelah policy memeriksa akses dokumen. File tidak seharusnya disajikan langsung dari direktori publik.

Saat upload/replace, service:

- memeriksa policy dan status aplikasi;
- memvalidasi ekstensi, MIME yang diizinkan kategori, signature konten, dan batas ukuran;
- menyimpan checksum SHA-256;
- membuat `DocumentVersion` bernomor dan memperbarui metadata versi terkini pada `Document`;
- menggunakan unique `(document_id, version_number)` untuk mencegah nomor versi duplikat.

`Document::currentVersion()` memilih versi dengan nomor tertinggi. Replace mengunci record dokumen saat menentukan nomor versi.

**Batas transaksi:** transaksi database tidak dapat membuat operasi filesystem menjadi atomik. Service melakukan cleanup file baru jika penulisan metadata versi gagal, tetapi kebijakan pemulihan/retensi untuk kegagalan proses lain dan file yatim masih **TBD**. Delete saat ini melakukan soft delete record; penghapusan fisik file dan masa retensi tidak dinyatakan sebagai perilaku otomatis.

**Production:** disk lokal private tersedia saat ini. Shared/persistent storage lintas instance, backup file, enkripsi at-rest, dan kebijakan retensi merupakan keputusan deployment **Planned/TBD**; jangan mengekspos dokumen dengan symbolic link public.

---

## 8. Audit Architecture

Ada dua jenis jejak yang berbeda:

1. **Workflow history:** `ApplicationWorkflowService` menulis `WorkflowHistory` pada setiap transisi status dengan status asal/tujuan, action, note, actor, dan waktu. Penulisan history berada pada transaksi yang sama dengan perubahan aplikasi.
2. **General audit log:** tabel/model `AuditLog` menyediakan target polymorphic, user, action, old/new values, IP, dan user agent.

**Kondisi saat ini:** tabel dan model audit umum tersedia, tetapi tidak ditemukan observer/listener atau writer otomatis yang mengisi `AuditLog`. Jangan menyatakan seluruh perubahan data tercatat otomatis. Mekanisme pencatatan perubahan umum, kebijakan akses log, dan retensinya berstatus **Planned/TBD**. Jangan menggantikan `WorkflowHistory` dengan audit log umum untuk transisi aplikasi.

---

## 9. Transaction Boundaries

- **Workflow aplikasi:** satu transaksi database mencakup row lock, validasi transisi/otorisasi pada state terkunci, update `Application`, dan insert `WorkflowHistory`. Kegagalan harus membatalkan keseluruhan perubahan database.
- **Upload dokumen:** metadata dokumen, file, versi, dan metadata versi terkini dikoordinasikan oleh `DocumentService`; file storage tidak ikut transaksi database. Service membersihkan file baru pada kegagalan penyimpanan record versi.
- **Replace dokumen:** row dokumen dikunci saat nomor versi berikutnya dipilih; unique index database menjadi pengaman tambahan.
- **CRUD data lain:** model dan policy tersedia, tetapi service khusus untuk operasi lintas entitas umum belum terlihat. Jika operasi tersebut memiliki invariant multi-record atau side effect, tambahkan service boundary daripada menyebarkan transaksi ke UI.

SQLite tidak menyediakan semantik konkurensi identik dengan MySQL untuk semua skenario row locking. Uji workflow concurrency dan migrasi juga pada MySQL sebelum produksi.

---

## 10. Testing Architecture

PHPUnit membagi suite `Unit` dan `Feature`; `phpunit.xml` mengatur SQLite in-memory. **Kondisi saat ini:** file test yang tersedia hanya test contoh Laravel/PHPUnit, belum mencakup aturan domain.

Test yang perlu ditambahkan sebelum menganggap arsitektur terverifikasi:

- unit/service test seluruh transisi valid dan invalid, note wajib, terminal rejection, policy denial, serta history yang tercatat;
- feature test authorization invariant: permission AND organization scope AND workflow state;
- test batas akses wilayah untuk child dan application di tingkat desa, kecamatan, kabupaten, dan admin global;
- test upload/replace/download/delete, signature/MIME/size, checksum, version ordering, dan cleanup ketika metadata gagal;
- test constraint unik, foreign keys, dan migrasi dengan SQLite serta MySQL;
- test panel/resource setelah UI Filament tersedia.

Test fungsional workflow dan dokumen adalah **Planned**. SQLite in-memory merupakan feedback cepat, bukan pengganti validasi kompatibilitas MySQL production.

---

## 11. Security Architecture

- Panel admin memakai autentikasi Laravel/Filament dan middleware sesi web yang didefinisikan provider panel.
- Semua aksi sensitif memerlukan pemeriksaan server-side melalui policy/service; sembunyikan tombol di UI hanya sebagai affordance, bukan kontrol keamanan.
- Batasi data pada scope organisasi dan periksa ulang akses dokumen saat download.
- Dokumen disimpan di disk private dan diakses melalui service terotorisasi.
- Terapkan validasi konten file, batas ukuran kategori, checksum, dan nama/path storage yang tidak berasal langsung dari path pengguna.
- Password memakai cast hashing pada model `User`; kredensial dan konfigurasi environment tidak boleh dimasukkan ke repository.
- Terapkan HTTPS, least privilege untuk akun database/storage, backup terlindungi, patch dependency, dan pengamanan secret di lingkungan production.

**TBD:** kebijakan retensi data sensitif, enkripsi/rotasi backup, audit akses baca/download, rate limiting panel, pemulihan bencana, dan aturan lockout/session operasional perlu ditetapkan.

---

## 12. Deployment Architecture

### Local development

- PHP dan Composer untuk aplikasi Laravel.
- SQLite file sebagai database development.
- Build asset melalui Vite sesuai konfigurasi aplikasi.
- Private document storage pada disk lokal.

### Production target

- Deploy satu aplikasi Laravel 13 dengan runtime PHP yang kompatibel dan web server yang mendukung front controller `public/index.php`.
- Gunakan MySQL sebagai database production dan jalankan migrasi terkontrol saat release.
- Build asset production, konfigurasi environment/secret di luar repository, aktifkan HTTPS, dan gunakan akun database dengan hak minimum.
- Pastikan private document storage persisten dan tercakup backup/restore.
- Aktifkan konfigurasi production Laravel yang sesuai (debug nonaktif, cache konfigurasi/routes/views bila relevan, log dan monitoring operasional).

**Kondisi saat ini:** konfigurasi aplikasi mendukung SQLite dan MySQL serta private local disk, tetapi tidak ada manifest/infrastruktur deployment production yang menetapkan web server, hosting, storage bersama, backup, monitoring, atau strategi release. Detail tersebut tetap **Planned/TBD** dan tidak boleh dianggap telah tersedia hanya karena direkomendasikan di sini.

---

## 13. Implemented vs Planned/TBD

### Implemented

- Laravel application dan Filament admin panel provider dengan login dan middleware sesi.
- Model Eloquent, relasi, schema migrations, enums, roles/permissions, policies, serta scope organisasi.
- `ApplicationWorkflowService` dengan transaksi, row lock, Gate checks, status update, dan workflow history.
- `DocumentService` dengan private local storage, validasi file, versioning, policy checks, dan download.
- SQLite default development/test serta konfigurasi koneksi MySQL.

### Planned/TBD

- Filament resources/pages operasional dan integrasi action dengan policy/service.
- Persistensi dan routing eksplisit `current_review_level` serta penerapan revisi Kabupaten kembali melalui Kecamatan.
- Writer/observer untuk general `AuditLog` dan kebijakan retensi audit.
- Test domain dan feature yang mencakup authorization, workflow, file storage, serta kompatibilitas MySQL.
- Rekonsiliasi tipe primary key `users` dengan model dan foreign key yang merujuk kepadanya.
- Production deployment topology, private/shared storage, backup, monitoring, dan kebijakan keamanan operasional.

---

*Dokumen ini adalah acuan arsitektur V1 yang membedakan implementasi repository saat ini dari pekerjaan yang masih direncanakan.*
