# TODO Pengembangan
# Manajemen Data Anak Yatim - V1

| Informasi | Detail |
|---|---|
| Versi | 1.0 |
| Tanggal audit | 2026-10-02 |
| Sumber | PRD, DOMAIN, ARCHITECTURE, DECISIONS, dan repository saat ini |

Backlog ini disusun berdasarkan codebase V1. Task `DONE` berarti implementasi dasarnya telah ditemukan pada source/migration/seeder saat audit; itu **tidak** berarti test perilakunya sudah lengkap atau pernah dijalankan. Cakupan test yang belum ada ditulis sebagai task tersendiri.

## Aturan Eksekusi

- Ambil satu task kecil per perubahan/PR. Jangan mengerjakan task dengan dependency belum selesai.
- Sebelum coding, baca `docs/AGENTS.md`, keputusan `LOCKED` terkait di `docs/DECISIONS.md`, lalu file yang disebut dalam task.
- Jangan mengubah source code untuk task dokumentasi. Jangan menganggap data demo sebagai master data produksi.
- Untuk perubahan workflow, gunakan `ApplicationWorkflowService`; untuk dokumen, gunakan `DocumentService`. Integrasi panel mengikuti `Policy -> Service -> Model/Database`.
- `BLOCKED` berarti task tidak aman dilanjutkan sebelum dependency atau keputusan terkait tersedia. Jangan menghapus status blocked dengan asumsi.
- Ubah status menjadi `IN PROGRESS` saat mulai dan `DONE` hanya setelah acceptance criteria serta test wajib terpenuhi. Laporkan test yang tidak dapat dijalankan.
- Jangan menjalankan command destructive pada database bersama. Gunakan database disposable untuk migration test.

---

## Fase 1 — Project Baseline

### PB-01: Verifikasi Baseline Laravel dan Panel Admin

- **Tujuan:** Catat baseline project yang sudah tersedia agar task tidak mengulang instalasi framework atau panel.
- **File/module kemungkinan disentuh:** `composer.json`, `app/Providers/Filament/AdminPanelProvider.php`, `config/database.php`, `phpunit.xml`.
- **Dependency:** Tidak ada.
- **Acceptance criteria:** Laravel 13 dan Filament 5 tercatat sebagai dependency; satu panel `admin` dengan login dan path `/admin` tersedia; default database development SQLite dan konfigurasi MySQL tersedia.
- **Test wajib dibuat/dijalankan:** Jalankan `composer validate` dan smoke check aplikasi (`php artisan about`) pada environment development. Test perilaku panel dicakup task `FIL-*`/`TEST-04`.
- **Status:** DONE

---

## Fase 2 — Database / Schema Completion

### DB-01: Domain Schema dan Constraint Inti

- **Tujuan:** Inventarisasi schema inti yang sudah dibuat agar tidak dibuat ulang.
- **File/module kemungkinan disentuh:** `database/migrations/*`, `app/Models/*`, `app/Enums/*`.
- **Dependency:** PB-01.
- **Acceptance criteria:** Migrations dan model tersedia untuk wilayah/organisasi, user/permission, child/keluarga/alamat, applications, dokumen/versi, rekening, workflow history, dan audit log; constraint NIK unik serta unique `(child_id, period_year)` tetap ada. Status `DONE` tidak menutup gap ID user atau `current_review_level` yang dicatat terpisah.
- **Test wajib dibuat/dijalankan:** Jalankan migration pada database SQLite disposable; cek constraint diuji melalui `TEST-04`. Jangan memakai database developer bersama untuk reset.
- **Status:** DONE

### DB-02: Sepakati Kontrak Persistensi `current_review_level`

- **Tujuan:** Putuskan representasi data untuk target level review sebelum migration dibuat.
- **File/module kemungkinan disentuh:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/DOMAIN.md`; setelah keputusan, kemungkinan `Application`, enum, migration, dan workflow.
- **Dependency:** Tidak ada; perlu keputusan product/technical owner.
- **Acceptance criteria:** Catat nilai yang valid, makna `SUBMITTED -> Kecamatan` dan `VALIDATED -> Kabupaten`, kapan nilai diisi/diubah/dibersihkan, backfill data lama, serta bagaimana revisi Kabupaten mengembalikan target ke Kecamatan.
- **Test wajib dibuat/dijalankan:** Review keputusan terhadap PRD dan matriks transisi; tidak ada test kode sebelum kontrak disetujui.
- **Status:** TODO

### DB-03: Tambahkan Persistensi `current_review_level`

- **Tujuan:** Persistenkan target review agar status lifecycle dan tujuan antrean tidak dicampur.
- **File/module kemungkinan disentuh:** migration baru, `app/Models/Application.php`, enum yang disepakati, `ApplicationWorkflowService`, factories/seeders terkait.
- **Dependency:** DB-02.
- **Acceptance criteria:** Migration baru mengatur field/cast/constraint sesuai kontrak; backfill aman; service memperbarui level pada transisi yang disetujui; tidak ada logika routing yang hanya berada di Filament.
- **Test wajib dibuat/dijalankan:** Feature/service test untuk nilai awal, `SUBMITTED` ke Kecamatan, `VALIDATED` ke Kabupaten, revisi Kabupaten kembali ke Kecamatan, resubmit, rollback migration pada database disposable.
- **Status:** BLOCKED

### DB-04: Rekonsiliasi Tipe Primary Key User dan Foreign Key

- **Tujuan:** Selesaikan mismatch antara integer `users.id`, `HasUlids` di model `User`, dan foreign key user yang dideklarasikan ULID.
- **File/module kemungkinan disentuh:** migration baru, `app/Models/User.php`, migration FK `created_by`, `uploaded_by`, `acted_by`, audit log, factories/seeders.
- **Dependency:** Keputusan pemilik teknis tentang tipe ID canonical; lihat `DEC-33`.
- **Acceptance criteria:** Semua PK/FK user menggunakan tipe dan generator yang konsisten; data lama memiliki strategi migrasi; `DEC-33` diperbarui setelah keputusan implementasi disahkan.
- **Test wajib dibuat/dijalankan:** Migration, FK insert/read, relasi Eloquent, dan seeder diuji pada SQLite serta MySQL disposable.
- **Status:** BLOCKED

---

## Fase 3 — Master Administrative Data

### ADM-01: Fondasi Wilayah dan Hierarki Organization Unit

- **Tujuan:** Tandai model, migration, dan seed awal wilayah/hierarki yang telah ada.
- **File/module kemungkinan disentuh:** migrations provinces/regencies/districts/villages/organization_units, `database/seeders/RegionSeeder.php`, `database/seeders/OrganizationUnitSeeder.php`, `app/Models/{Province,Regency,District,Village,OrganizationUnit}.php`.
- **Dependency:** DB-01.
- **Acceptance criteria:** Model relasi wilayah ada; seed membentuk contoh hierarki Regency -> District -> Village dan OrganizationUnit parent-child. Seed saat ini hanya memuat subset wilayah, bukan bukti seluruh data administratif production.
- **Test wajib dibuat/dijalankan:** Test idempotensi seeder dan integritas parent/wilayah dicakup `TEST-04` sebelum data seeder dipakai sebagai production.
- **Status:** DONE

### ADM-02: Tentukan Cakupan dan Sumber Master Data Wilayah

- **Tujuan:** Pastikan apakah V1 memerlukan seluruh wilayah resmi atau hanya wilayah operasional tertentu, dan tetapkan sumber data yang disetujui.
- **File/module kemungkinan disentuh:** `docs/DECISIONS.md`, `docs/DOMAIN.md`, `database/seeders/RegionSeeder.php`, kemungkinan import/seeder baru.
- **Dependency:** Keputusan stakeholder tentang cakupan wilayah dan sumber resmi.
- **Acceptance criteria:** Sumber, tanggal/versi data, coverage wilayah, serta proses pembaruan dicatat; seed contoh tidak dipresentasikan sebagai data lengkap.
- **Test wajib dibuat/dijalankan:** Validasi jumlah/kode wilayah terhadap dataset sumber dan idempotensi import.
- **Status:** TODO

---

## Fase 4 — User / Role / Permission

### AUTH-01: Role dan Permission V1

- **Tujuan:** Catat role, permission, dan policy foundation yang sudah tersedia.
- **File/module kemungkinan disentuh:** `database/seeders/RoleAndPermissionSeeder.php`, `app/Models/User.php`, `app/Policies/*Policy.php`.
- **Dependency:** PB-01.
- **Acceptance criteria:** Role seeder mencakup `super_admin`, `admin_kabupaten`, `admin_kecamatan`, `admin_kelurahan`; `manage users` dan `manage master data` diberikan ke Super Admin; permission verifikasi bisnis tidak otomatis diberikan kepadanya.
- **Test wajib dibuat/dijalankan:** Test seeder permission dan otorisasi role dicakup `TEST-02`.
- **Status:** DONE

### AUTH-02: Perbaiki Signature UserPolicy

- **Tujuan:** Pastikan Policy user dapat di-parse dan membedakan user pemanggil dari user target.
- **File/module kemungkinan disentuh:** `app/Policies/UserPolicy.php`, `tests/Feature/Authorization/UserPolicyTest.php`.
- **Dependency:** AUTH-01.
- **Acceptance criteria:** Parameter actor dan target memiliki nama/posisi berbeda; seluruh method menggunakan permission `manage users`; user tanpa permission ditolak tanpa fatal error.
- **Test wajib dibuat/dijalankan:** Policy test untuk `viewAny`, `view`, `create`, `update`, `delete`, `restore`, dan `forceDelete`; jalankan test terfokus.
- **Status:** TODO

### AUTH-03: Sepakati Provisioning Akun dan Bootstrap Admin

- **Tujuan:** Putuskan cara membuat akun awal dan akun operasional tanpa mengasumsikan self-registration atau password default.
- **File/module kemungkinan disentuh:** `docs/DECISIONS.md`, `database/seeders/UserSeeder.php`, `database/seeders/DatabaseSeeder.php`, kemungkinan command/setup terpisah.
- **Dependency:** Keputusan pemilik produk/keamanan.
- **Acceptance criteria:** Bedakan seed demo dari bootstrap production; tetapkan pembuat akun, penetapan role/unit, pengiriman/penetapan credential awal, dan status akun aktif.
- **Test wajib dibuat/dijalankan:** Review keputusan; setelah implementasi, test bahwa seed production tidak membuat credential publik/default.
- **Status:** TODO

---

## Fase 5 — Child Master Data

### CHILD-01: Child, Keluarga, Household, Address, dan Policy Foundation

- **Tujuan:** Catat fondasi data child beserta relasi dan pembatasan akses yang sudah ada.
- **File/module kemungkinan disentuh:** `app/Models/{Child,Person,ChildRelationship,Household,Address}.php`, `app/Policies/{Child,Person,Household}Policy.php`, migrations terkait.
- **Dependency:** DB-01, ADM-01.
- **Acceptance criteria:** Child memiliki NIK unik dan relasi household/address; parent/wali melalui Person + ChildRelationship; ChildPolicy memeriksa permission, scope lokasi, serta perubahan address baru. Status yatim tidak disimpan terpisah; hanya aturan ayah deceased + ibu alive = yatim yang terkonfirmasi.
- **Test wajib dibuat/dijalankan:** Policy dan relation behavior dicakup `TEST-02`; jangan menambahkan klasifikasi orphan lain tanpa keputusan.
- **Status:** DONE

---

## Fase 6 — Application

### APP-01: Application Aggregate, Period Year, dan Policy Foundation

- **Tujuan:** Catat model/schema application yang telah ada sebelum menambahkan UI.
- **File/module kemungkinan disentuh:** `app/Models/Application.php`, `app/Policies/ApplicationPolicy.php`, migration applications, `app/Enums/ApplicationStatus.php`.
- **Dependency:** DB-01, CHILD-01.
- **Acceptance criteria:** Application terpisah dari Child; punya `period_year`, state, creator dan timestamp; unique `(child_id, period_year)`; policy membatasi create/update/delete/submit sesuai permission, scope, dan status.
- **Test wajib dibuat/dijalankan:** Constraint dan policy behavior dicakup `TEST-02` serta `TEST-04`.
- **Status:** DONE

---

## Fase 7 — Workflow

### WF-01: ApplicationWorkflowService dan Workflow History

- **Tujuan:** Catat workflow backend yang telah diimplementasikan.
- **File/module kemungkinan disentuh:** `app/Services/ApplicationWorkflowService.php`, `app/Models/WorkflowHistory.php`, `app/Exceptions/InvalidWorkflowTransitionException.php`, `app/Policies/ApplicationPolicy.php`.
- **Dependency:** APP-01.
- **Acceptance criteria:** Service memiliki submit, request revision, validate, verify, reject; memeriksa state dan Gate dalam transaksi dengan row lock; mengubah timestamp terkait; menulis WorkflowHistory dalam transaksi yang sama; note wajib untuk revision/reject; verified/rejected tidak memiliki transisi keluar.
- **Test wajib dibuat/dijalankan:** Test seluruh transisi sukses/gagal dan history ada di `TEST-01`; keberadaan service saja belum berarti behavior telah dites.
- **Status:** DONE

---

## Fase 8 — Review / Revision

### REV-01: Terapkan Revisi Kabupaten Kembali ke Kecamatan

- **Tujuan:** Lengkapi gap workflow agar revisi setelah validasi Kabupaten tidak melewati review Kecamatan.
- **File/module kemungkinan disentuh:** `app/Services/ApplicationWorkflowService.php`, `app/Policies/ApplicationPolicy.php`, `app/Models/Application.php`, migration/enum setelah DB-03.
- **Dependency:** DB-02 dan DB-03.
- **Acceptance criteria:** Reviewer Kabupaten dapat meminta revisi pada state/level yang disepakati; setelah Kelurahan memperbaiki dan submit ulang, target review kembali ke Kecamatan; validasi dan verifikasi berikutnya mengikuti urutan; note revisi tercatat.
- **Test wajib dibuat/dijalankan:** Service/feature test menolak bypass Kecamatan serta memeriksa semua perubahan status, level, actor, dan note (`TEST-01`).
- **Status:** BLOCKED

### REV-02: Gate Review dan Verifikasi Berdasarkan State

- **Tujuan:** Catat gate status action yang sudah ada di policy/service.
- **File/module kemungkinan disentuh:** `app/Policies/ApplicationPolicy.php`, `ApplicationWorkflowService.php`.
- **Dependency:** APP-01, WF-01.
- **Acceptance criteria:** `review`, `validate`, dan request revision dibatasi ke `submitted`; verify dibatasi ke `validated`; reject dibatasi ke `submitted`/`validated`; akses scope dan permission tetap diperiksa.
- **Test wajib dibuat/dijalankan:** Jalankan policy/service test dalam `TEST-01` dan `TEST-02` setelah tersedia.
- **Status:** DONE

---

## Fase 9 — Document Management

### DOC-01: DocumentService, Private Storage, Versioning, dan State Gate

- **Tujuan:** Catat implementasi inti dokumen yang ditemukan agar tidak dibangun ulang.
- **File/module kemungkinan disentuh:** `app/Services/DocumentService.php`, `app/Policies/DocumentPolicy.php`, `app/Models/{Document,DocumentCategory,DocumentVersion}.php`, `config/filesystems.php`.
- **Dependency:** DB-01, APP-01.
- **Acceptance criteria:** Service upload/replace/delete/download memakai disk `local` private; file divalidasi MIME/signature/ukuran; checksum dan versi tercatat; replace menambah versi; policy mengunci perubahan pada application `draft` atau `revision_required`; akses download diperiksa.
- **Test wajib dibuat/dijalankan:** Upload, replace, download, file invalid, batas ukuran, version order, state lock, dan cleanup dicakup `TEST-03`.
- **Status:** DONE

---

## Fase 10 — Filament UI

### FIL-01: Resource Wilayah dan Organization Unit

- **Tujuan:** Buat UI master administratif pada panel tunggal.
- **File/module kemungkinan disentuh:** `app/Filament/Resources/*`, `AdminPanelProvider.php`, `app/Policies/{MasterData,OrganizationUnit}Policy.php`.
- **Dependency:** ADM-02, AUTH-01.
- **Acceptance criteria:** Resource yang disetujui tersedia di panel `/admin`; aksi memeriksa Policy dan permission yang tepat (`manage master data` untuk master data, `manage organization` untuk unit organisasi); hierarki parent/wilayah tidak dapat disimpan dalam kombinasi tidak valid; tidak membuat panel per role.
- **Test wajib dibuat/dijalankan:** Filament/Livewire feature test list, create/update, permission denial, dan parent/wilayah integrity (`TEST-05`).
- **Status:** TODO

### FIL-02: User dan Role Administration

- **Tujuan:** Sediakan pengelolaan akun internal oleh Super Admin.
- **File/module kemungkinan disentuh:** `app/Filament/Resources/*User*`, role resource bila disetujui, `UserPolicy`, `RolePolicy`, `OrganizationUnitPolicy`.
- **Dependency:** AUTH-03, AUTH-01, DB-04.
- **Acceptance criteria:** User create/update/activate/deactivate hanya melalui permission `manage users`; role dan organization unit ditetapkan eksplisit; credential tidak ditampilkan kembali; Super Admin tidak otomatis memperoleh business verification.
- **Test wajib dibuat/dijalankan:** Filament action test Super Admin allowed, role lain denied, password hashed, dan unit assignment (`TEST-05`).
- **Status:** BLOCKED

### FIL-03: Child, Household, dan Address Resources

- **Tujuan:** Bangun pengelolaan data child dan alamat melalui panel.
- **File/module kemungkinan disentuh:** `app/Filament/Resources/*Child*`, household/address resource atau relation manager, `ChildPolicy`.
- **Dependency:** CHILD-01, ADM-01.
- **Acceptance criteria:** Form memakai relasi Eloquent yang ada; NIK duplikat ditolak; create/update alamat memeriksa scope alamat baru; query daftar dibatasi scope user; tidak menambah business rules orphan yang belum disepakati.
- **Test wajib dibuat/dijalankan:** Filament/Livewire test CRUD, NIK duplikat, user luar-scope, dan pindah alamat lintas-scope (`TEST-05`).
- **Status:** DONE

### FIL-04: Person dan Child Relationship Management

- **Tujuan:** Sediakan input orang tua/wali dan relasinya dengan child.
- **File/module kemungkinan disentuh:** `app/Filament/Resources/*Person*`, child relation manager, `PersonPolicy`, `ChildPolicy`.
- **Dependency:** CHILD-01, FIL-03.
- **Acceptance criteria:** Relasi memakai `relationship_type` enum yang tersedia; data orang dan child tidak diduplikasi; jumlah relasi ayah/ibu/wali tidak dipaksakan di UI sebelum aturan jumlah disepakati.
- **Test wajib dibuat/dijalankan:** Test create/edit relasi, policy scope, dan status Person valid.
- **Status:** DONE

### FIL-05: Application CRUD dan Period Year

- **Tujuan:** Buat UI pembuatan dan pembaruan data aplikasi tanpa menaruh workflow di form.
- **File/module kemungkinan disentuh:** `app/Filament/Resources/*Application*`, `ApplicationPolicy`, `Application` model.
- **Dependency:** APP-01, FIL-03.
- **Acceptance criteria:** Create memilih child dalam scope; `period_year` disimpan sebagai tahun program; duplicate child/year ditolak oleh database dan ditampilkan sebagai error yang dapat dipahami; perubahan data hanya pada state yang diizinkan Policy.
- **Test wajib dibuat/dijalankan:** Filament create/update tests, duplicate pair rejection, cross-scope denial, dan locked-state edit denial (`TEST-05`).
- **Status:** DONE

### FIL-06: Workflow Actions Melalui Policy dan ApplicationWorkflowService

- **Tujuan:** Integrasikan submit/review/validate/verify/reject/revision ke satu panel.
- **File/module kemungkinan disentuh:** Application Resource/Actions di `app/Filament/Resources/*`, `ApplicationPolicy`, `ApplicationWorkflowService`.
- **Dependency:** WF-01, REV-01, FIL-05.
- **Acceptance criteria:** Action hanya mempresentasikan input dan hasil; setiap action melakukan Policy check dan memanggil `ApplicationWorkflowService`; Filament tidak mengubah status atau menulis WorkflowHistory langsung; status/level dan note tampil dari data tersimpan.
- **Test wajib dibuat/dijalankan:** Livewire action tests untuk allowed/denied actions, transaksi/history, note wajib, dan urutan review (`TEST-05`).
- **Status:** DONE

### FIL-07: Document Category dan Document Actions

- **Tujuan:** Integrasikan upload, replace, delete, dan download ke satu panel.
- **File/module kemungkinan disentuh:** Document/Application Resource relation manager, `DocumentPolicy`, `DocumentService`.
- **Dependency:** DOC-01, FIL-05.
- **Acceptance criteria:** Semua operasi file memanggil `DocumentService`; visibility bukan kontrol keamanan; download tetap privat; UI menampilkan versi dan validasi yang berasal dari service/category.
- **Test wajib dibuat/dijalankan:** Filament/Livewire file action tests dan authorization untuk tiap operasi (`TEST-05`, `TEST-03`).
- **Status:** DONE
- **Test wajib dibuat/dijalankan:** Filament/Livewire file action tests dan authorization untuk tiap operasi (`TEST-05`, `TEST-03`).
- **Status:** TODO

---

## Fase 11 — Dashboard

### DASH-01: Tetapkan KPI dan Scope Dashboard

- **Tujuan:** Pisahkan kebutuhan ringkasan dashboard dari daftar laporan rinci.
- **File/module kemungkinan disentuh:** `docs/PRD.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`.
- **Dependency:** Konfirmasi product owner untuk KPI dan akses per role.
- **Acceptance criteria:** Setiap KPI memiliki definisi, filter `period_year`, scope wilayah, role yang boleh melihat, serta sumber data; bedakan kebutuhan Super Admin, Kabupaten, Kecamatan, dan Kelurahan.
- **Test wajib dibuat/dijalankan:** Review definisi dan contoh hitung; belum ada test kode sebelum KPI disetujui.
- **Status:** TODO

### DASH-02: Implementasikan Dashboard Filament V1

- **Tujuan:** Tambahkan ringkasan operasional yang sudah disetujui pada dashboard panel.
- **File/module kemungkinan disentuh:** `app/Filament/Pages/*`, `app/Filament/Widgets/*`, query/service agregasi yang diperlukan.
- **Dependency:** DASH-01, FIL-01, AUTH-01.
- **Acceptance criteria:** KPI cocok dengan definisi yang disetujui, query menghormati organization scope, dan dashboard tidak melakukan mutasi workflow.
- **Test wajib dibuat/dijalankan:** Widget/page test untuk nilai agregat, tahun, dan isolasi antarwilayah.
- **Status:** BLOCKED

---

## Fase 12 — Reporting

### REP-01: Tetapkan Laporan, Filter, dan Export Scope

- **Tujuan:** Konfirmasi daftar laporan PRD dan hak export sebelum membangun layar.
- **File/module kemungkinan disentuh:** `docs/PRD.md`, `docs/DECISIONS.md`, permission/report policy.
- **Dependency:** Konfirmasi stakeholder; DASH-01 untuk KPI yang tumpang tindih.
- **Acceptance criteria:** Definisi laporan mencakup jumlah aplikasi per tahun/status/wilayah, revisi/penolakan, rentang waktu, role, filter, format export, dan batas data pribadi.
- **Test wajib dibuat/dijalankan:** Review sample output dan validasi matriks permission; belum ada test kode sebelum scope disetujui.
- **Status:** TODO

### REP-02: Bangun Report Pages dan Export Terotorisasi

- **Tujuan:** Implementasikan laporan yang telah disepakati tanpa melewati scope query.
- **File/module kemungkinan disentuh:** `app/Filament/Pages/*`, `app/Policies/*`, query/service reporting, permission seeder.
- **Dependency:** REP-01, FIL-01.
- **Acceptance criteria:** Hasil dan export mematuhi filter role/wilayah dan `period_year`; export hanya untuk permission yang disetujui; tidak mengekspos data sensitif yang tidak dibutuhkan.
- **Test wajib dibuat/dijalankan:** Feature test agregat, export, permission, filter tahun, dan isolasi wilayah.
- **Status:** BLOCKED

---

## Fase 13 — Audit

### AUD-01: WorkflowHistory pada Setiap Transisi

- **Tujuan:** Tandai audit transisi workflow yang telah ditangani service.
- **File/module kemungkinan disentuh:** `ApplicationWorkflowService`, `WorkflowHistory`, migration history.
- **Dependency:** WF-01.
- **Acceptance criteria:** Setiap operasi workflow yang sukses menulis satu record actor/action/from/to/note/time dalam transaksi yang sama; kegagalan tidak meninggalkan transisi parsial.
- **Test wajib dibuat/dijalankan:** Assertion history pada setiap transisi dicakup `TEST-01`.
- **Status:** DONE

### AUD-02: Tentukan Event dan Data untuk General AuditLog

- **Tujuan:** Sepakati perubahan non-workflow apa yang wajib dicatat, field sensitif yang harus disamarkan, akses, dan retensi.
- **File/module kemungkinan disentuh:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `app/Models/AuditLog.php`, `AuditLogPolicy`.
- **Dependency:** Keputusan product/security owner.
- **Acceptance criteria:** Event matrix meliputi perubahan user/role, master data, child, dokumen, serta akses file sesuai keputusan; `WorkflowHistory` tetap terpisah; retention dan redaction dinyatakan.
- **Test wajib dibuat/dijalankan:** Review event matrix dan redaction checklist; belum ada test kode sebelum keputusan disetujui.
- **Status:** TODO

### AUD-03: Implementasikan General AuditLog Writer

- **Tujuan:** Isi `AuditLog` hanya untuk event yang disetujui pada AUD-02.
- **File/module kemungkinan disentuh:** observer/listener/service audit baru, provider, model/policy terkait.
- **Dependency:** AUD-02.
- **Acceptance criteria:** Actor/action/subject/old-new values tercatat sesuai event matrix; secret dan data file tidak bocor; kegagalan audit punya perilaku yang ditetapkan; tidak menggandakan WorkflowHistory.
- **Test wajib dibuat/dijalankan:** Test event capture, redaction, actor nullability, dan larangan akses log.
- **Status:** BLOCKED

---

## Fase 14 — Security Review

### SEC-01: Review Permission, Organization Scope, dan Resource Access

- **Tujuan:** Cari jalur baca/tulis yang hanya mengandalkan role atau visibility UI.
- **File/module kemungkinan disentuh:** semua `app/Policies/*`, `User.php`, query scopes Child/Application, resource Filament setelah tersedia.
- **Dependency:** AUTH-01; ulangi setelah FIL-01 sampai FIL-07 selesai.
- **Acceptance criteria:** Aksi resource menerapkan `permission AND organization scope AND workflow state`; `viewAny` disertai query scoping; user tanpa organization unit tidak mendapat akses luas tanpa keputusan eksplisit.
- **Test wajib dibuat/dijalankan:** Authorization matrix dalam `TEST-02` dan regression tests untuk setiap temuan yang diperbaiki.
- **Status:** TODO

### SEC-02: Amankan Seeder dan Credential Demo

- **Tujuan:** Pastikan credential contoh dari `UserSeeder` tidak dapat menjadi akses production.
- **File/module kemungkinan disentuh:** `database/seeders/UserSeeder.php`, `DatabaseSeeder.php`, setup/bootstrap account sesuai AUTH-03, konfigurasi deployment.
- **Dependency:** AUTH-03.
- **Acceptance criteria:** Tidak ada password default yang diketahui pada user production; seeder demo dibatasi ke environment yang sesuai atau strategi credential disetujui; secret tidak masuk repository/log.
- **Test wajib dibuat/dijalankan:** Test environment guard, hash password, dan larangan seeding credential demo pada konfigurasi production.
- **Status:** BLOCKED

### SEC-03: Tinjauan Keamanan Dokumen Privat

- **Tujuan:** Verifikasi jalur upload/download dan konfigurasi storage sebelum production.
- **File/module kemungkinan disentuh:** `DocumentService`, `DocumentPolicy`, `config/filesystems.php`, Filament document actions, production config.
- **Dependency:** DOC-01, FIL-07.
- **Acceptance criteria:** File tidak dapat diakses langsung dari public disk; download memerlukan izin dan scope; signature/size/divergent MIME ditolak; error tidak membocorkan path/isi sensitif.
- **Test wajib dibuat/dijalankan:** Security feature tests dan checklist konfigurasi storage (`TEST-03`).
- **Status:** TODO

---

## Fase 15 — Testing

### TEST-01: Workflow Service Feature Tests

- **Tujuan:** Tutup gap test untuk state transition dan audit history.
- **File/module kemungkinan disentuh:** `tests/Feature/Workflow/*`, `ApplicationWorkflowService`, factories.
- **Dependency:** WF-01; REV-01 setelah tidak blocked.
- **Acceptance criteria:** Test transition valid/invalid, note wajib, terminal states, permission denial, concurrency behavior yang dapat diuji, timestamps, dan atomic WorkflowHistory.
- **Test wajib dibuat/dijalankan:** Jalankan test baru terfokus lalu `php artisan test`.
- **Status:** TODO

### TEST-02: Policy dan Organization Scope Tests

- **Tujuan:** Buktikan permission, scope wilayah, dan workflow state diperiksa bersamaan.
- **File/module kemungkinan disentuh:** `tests/Feature/Authorization/*`, `User`, `Child`, `Application`, policies, factories.
- **Dependency:** AUTH-01, CHILD-01, APP-01.
- **Acceptance criteria:** Uji user desa/kecamatan/kabupaten, super admin, wilayah berbeda, resource tidak ditemukan, dan aksi pada state yang salah. Kasus user tanpa unit dikarakterisasi, tetapi expected access tidak dikunci sampai ada keputusan stakeholder.
- **Test wajib dibuat/dijalankan:** Jalankan authorization suite terfokus dan `php artisan test`.
- **Status:** TODO

### TEST-03: DocumentService Feature Tests

- **Tujuan:** Buktikan file privat, validasi, versioning, locking, dan authorization.
- **File/module kemungkinan disentuh:** `tests/Feature/Documents/*`, `DocumentService`, `DocumentPolicy`, `Storage::fake` setup.
- **Dependency:** DOC-01.
- **Acceptance criteria:** Uji upload PDF/JPEG yang sah, ekstensi/signature/MIME salah, size limit, replace menambah nomor versi, versi lama dipertahankan, status terkunci, download scoped, dan file cleanup saat metadata gagal.
- **Test wajib dibuat/dijalankan:** Jalankan suite document terfokus lalu `php artisan test`.
- **Status:** TODO

### TEST-04: Migration, Constraint, Seeder, dan MySQL Compatibility

- **Tujuan:** Verifikasi schema pada SQLite development dan MySQL production.
- **File/module kemungkinan disentuh:** `tests/Feature/Database/*`, migrations, seeders, CI/test configuration.
- **Dependency:** DB-03 dan DB-04 untuk test final; DB-01 untuk test awal SQLite.
- **Acceptance criteria:** Uji migration bersih, FK, unique NIK/application/version, seed idempotency, user PK/FK, serta migration round-trip pada database disposable; catat perbedaan engine.
- **Test wajib dibuat/dijalankan:** SQLite suite lokal dan MySQL service/CI job sebelum production.
- **Status:** TODO

### TEST-05: Filament Resource dan Action Tests

- **Tujuan:** Pastikan UI tidak melewati Policy, Service, atau scope query.
- **File/module kemungkinan disentuh:** `tests/Feature/Filament/*`, resources/pages/widgets, policy/service.
- **Dependency:** FIL-01 sampai FIL-07.
- **Acceptance criteria:** Uji list scoping, form validation, denied action, workflow actions melalui ApplicationWorkflowService, document actions melalui DocumentService, dan error feedback.
- **Test wajib dibuat/dijalankan:** Jalankan test tiap resource/action yang disentuh dan seluruh `php artisan test`.
- **Status:** BLOCKED

---

## Fase 16 — Production Readiness

### PROD-01: Gate Migration dan Smoke Test MySQL Production-like

- **Tujuan:** Pastikan schema, FK, seeder minimum, dan query inti dapat berjalan pada engine production.
- **File/module kemungkinan disentuh:** migration, seeder, `config/database.php`, deployment validation script.
- **Dependency:** DB-04, TEST-04.
- **Acceptance criteria:** Migration dari database kosong berhasil; unique/FK, login seed/bootstrap, query scope, workflow transition, serta rollback prosedural tervalidasi; tidak menggunakan database production untuk test.
- **Test wajib dibuat/dijalankan:** MySQL disposable/CI test; lampirkan hasil pada release checklist.
- **Status:** BLOCKED

### PROD-02: Private Storage, Backup, dan Restore Plan

- **Tujuan:** Tentukan penyimpanan file persisten dan pemulihan dokumen pada deployment production.
- **File/module kemungkinan disentuh:** `config/filesystems.php`, deployment config, backup/restore runbook, `docs/ARCHITECTURE.md`.
- **Dependency:** SEC-03; keputusan storage production.
- **Acceptance criteria:** Root storage tetap privat; retensi, backup, restore, akses antar-instance, dan recovery file orphan ditetapkan dan diuji; tidak ada dokumen sensitif di public path.
- **Test wajib dibuat/dijalankan:** Restore drill pada environment non-production dan verifikasi download policy.
- **Status:** TODO

### PROD-03: Production Configuration dan Release Runbook

- **Tujuan:** Siapkan prosedur deployment Laravel/MySQL yang dapat diulang dan diaudit.
- **File/module kemungkinan disentuh:** environment/deployment config, build scripts, `docs/ARCHITECTURE.md`, runbook deployment.
- **Dependency:** PROD-01, PROD-02, SEC-02.
- **Acceptance criteria:** Secret berada di luar repository; debug nonaktif; HTTPS, least-privilege DB, asset build, migration release, log/monitoring, rollback, backup, dan smoke check terdokumentasi; tidak ada perubahan otomatis pada data production.
- **Test wajib dibuat/dijalankan:** Deploy ke staging bersih, jalankan smoke test login/panel/workflow/file, dan uji prosedur rollback.
- **Status:** BLOCKED

---

## Urutan Prioritas

1. `DB-02`, `AUTH-03`, `ADM-02`, `DASH-01`, `REP-01`, dan `AUD-02`: tetapkan keputusan yang masih TBD.
2. `DB-03` dan `DB-04`: selesaikan schema blocker melalui migration baru dan data strategy yang disetujui.
3. `REV-01`: implementasikan aturan revisi lintas level pada workflow/service, bukan pada UI.
4. `FIL-01` sampai `FIL-07`: bangun UI kecil per resource/action menggunakan Policy dan Service.
5. `TEST-01` sampai `TEST-05`, `SEC-01` sampai `SEC-03`: validasi fungsional dan keamanan.
6. `PROD-01` sampai `PROD-03`: lakukan readiness gate setelah schema dan test selesai.

*Perbarui status dan acceptance criteria task ini setelah verifikasi nyata; jangan menghapus riwayat keputusan atau menandai task DONE hanya karena file kerangkanya sudah dibuat.*
