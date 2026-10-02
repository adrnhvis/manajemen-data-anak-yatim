# Petunjuk Coding Agent
# Manajemen Data Anak Yatim - V1

Dokumen ini berisi aturan kerja praktis untuk developer junior dan AI agent. Aturan ini mengikuti keputusan V1 serta implementasi yang ada. Jangan menganggap fitur yang berstatus Planned/TBD sudah tersedia.

---

## 1. Source of Truth dan Urutan Membaca

Sebelum mengerjakan perubahan, baca sesuai urutan berikut:

1. Instruksi task terbaru dan batas scope yang dinyatakan user.
2. `docs/DECISIONS.md` untuk keputusan berstatus `LOCKED` dan daftar `TBD`.
3. `docs/PRD.md` untuk tujuan, aktor, scope, dan workflow bisnis.
4. `docs/DOMAIN.md` untuk entitas, relasi, status, dan aturan domain.
5. `docs/ARCHITECTURE.md` untuk layer aplikasi dan boundary teknis.
6. Kode yang relevan: model, enum, policy, service, migration, seeder, config, route, Filament provider, serta test terdekat.

Bedakan **requirement yang seharusnya berlaku** dari **perilaku yang saat ini terimplementasi**. Migration dan kode menunjukkan fakta implementasi, tetapi tidak otomatis menggantikan keputusan bisnis yang sudah `LOCKED`. Bila keduanya berbeda, jangan memilih diam-diam: jelaskan gap, tentukan file yang perlu diselaraskan, dan minta keputusan bila arah perubahan tidak jelas.

---

## 2. Sebelum Coding

- Uraikan behavior yang diminta, aktor, resource, status awal/akhir, batas wilayah, dan kondisi gagal.
- Temukan kode yang benar-benar memutuskan behavior; jangan berhenti pada halaman atau wiring yang hanya meneruskan input.
- Baca implementasi tetangga dan test/call site yang paling relevan.
- Sebelum edit, rumuskan singkat hipotesis tentang sumber masalah dan satu pemeriksaan terarah yang dapat membuktikan hipotesis itu salah.
- Buat perubahan sekecil mungkin untuk scope task. Jangan membuka refactor luas untuk “merapikan” area yang tidak diminta.
- Jika task hanya meminta dokumentasi, jangan mengubah source code.

Stack V1 adalah Laravel 13, Filament 5, Livewire/Blade, Eloquent ORM, Spatie Laravel Permission, PHP Backed Enum, SQLite untuk development, dan MySQL untuk production. Jangan menambah stack frontend atau state-management lain di luar keputusan V1.

---

## 3. Perubahan Database dan Migration

- Schema didefinisikan oleh migration. Perubahan schema dibuat dengan migration baru.
- Jangan mengedit migration lama yang mungkin sudah dipakai bersama pada database developer, staging, atau production. Jika migration belum pernah dipakai bersama dan perlu diperbaiki, konfirmasi dahulu sebelum mengubahnya.
- Sertakan foreign key, nullability, index, unique constraint, dan rollback yang sesuai dengan aturan domain.
- Jangan mengubah atau menghapus constraint uniqueness tanpa keputusan yang tercatat.
- Aturan data yang harus dipertahankan: `children.nik` unik; `(applications.child_id, applications.period_year)` unik; `households.kk_number` tidak dipaksa unik; `bank_accounts.account_number` tidak dipaksa unik.
- Child dan application adalah entitas berbeda. `period_year` adalah tahun anggaran/program bantuan.
- Uji migration dan constraint pada SQLite. Perubahan schema penting juga harus diperiksa pada MySQL karena itulah target production.
- **Jangan menebak tipe ID user:** migration awal memakai integer untuk `users.id`, sementara model `User` dan sejumlah foreign key mengindikasikan ULID. Ini tercatat sebagai `TBD`; hentikan asumsi baru tentang relasi user dan minta keputusan teknis sebelum menambah/mengubah foreign key terkait.

---

## 4. Model dan Relationship

- Gunakan model Eloquent yang sesuai dengan entitas domain; definisikan relasi dengan tipe relasi Eloquent dan foreign key eksplisit bila tidak mengikuti konvensi.
- Ikuti pola model setempat untuk fillable, casts, ULID, timestamps, dan soft delete. Jangan menyalin trait ID ke model sebelum memastikan tipe primary key migration cocok.
- Gunakan relasi model untuk akses antar-entitas; hindari menyimpan duplikasi nama/status parent langsung di `Child`.
- Ayah, ibu, dan wali direpresentasikan lewat `Person` dan `ChildRelationship`.
- Rekening terhubung ke `Application`, bukan menjadi atribut global child. Jangan menyimpulkan pemilik rekening dari relasi keluarga; `holder_type` dapat `unknown` atau nullable.
- ULID adalah identifier, bukan mekanisme authorization. Jangan gunakan ID yang sulit ditebak sebagai pengganti Policy, permission, atau organization scope.
- Jangan menambahkan kolom status yatim tersendiri sebagai sumber kebenaran baru tanpa keputusan; status yatim diturunkan dari relasi dan status parent. Aturan yang dipastikan: ayah deceased + ibu alive = yatim. Kasus lain masih TBD.

---

## 5. Enum

- Gunakan PHP Backed Enum untuk nilai domain terbatas seperti `ApplicationStatus`, `OrganizationUnitType`, `PersonStatus`, `RelationshipType`, `LivingWith`, `Gender`, dan `BankAccountHolderType`.
- Jangan menyebarkan string literal status/tipe di banyak tempat. Gunakan enum/cast ketika tersedia.
- Saat menambah/mengubah nilai, selaraskan enum, migration/schema, cast model, validasi, policy, service, seeder/factory, test, dan dokumentasi keputusan bila mengubah domain.
- Jangan mengasumsikan database enum, PHP enum, dan aturan transisi otomatis selalu sinkron; uji jalur baca/tulis dan transisinya.

---

## 6. Policy dan Authorization

Untuk aksi resource, pertahankan authorization invariant:

```text
permission AND organization scope AND workflow state
```

- Gunakan Laravel Policy untuk keputusan akses terhadap aksi/resource; permission berasal dari Spatie Laravel Permission.
- Jangan bypass Policy dari Filament Action, controller, job, atau service. Service untuk operasi sensitif juga memeriksa authorization di server-side.
- Jangan menganggap role sama dengan scope organisasi. Role menjawab aksi; scope membatasi wilayah/resource.
- Jangan memberi Super Admin kewenangan verifikasi bisnis hanya karena ia mengelola sistem. Seeder V1 tidak memberi `verify applications` kepada `super_admin`.
- Jangan menganggap tombol tersembunyi, menu tersembunyi, atau form disabled sebagai keamanan. Selalu periksa request/aksi di server.
- `viewAny` bukan filter data. Terapkan query scope untuk daftar dan Policy untuk resource individual.
- Jangan membuka akses karena ID/ULID diketahui. Periksa permission, scope, serta status yang mengizinkan aksi.

---

## 7. Organization Scope

- Scope child ditentukan melalui alamat anak dan hirarki `Regency -> District -> Village`, lalu dicocokkan dengan `OrganizationUnit` user.
- Gunakan `Child::scopeForUser()`, `Application::scopeForUser()`, `User::canAccessChild()`, dan `User::canAccessApplication()` atau pola yang sudah disepakati setelah memeriksa perilaku aktual.
- Saat membuat atau memindahkan alamat child, pastikan alamat baru juga masuk scope user; memeriksa alamat lama saja tidak cukup.
- Terapkan scope pada query collection dan pemeriksaan resource. Jangan mengandalkan Filament filter.
- Perilaku user tanpa `organization_unit_id` saat ini dapat memberi akses luas pada beberapa jalur. Jangan menyalin perilaku itu sebagai asumsi kebijakan; cek keputusan/Policy terkait dan tandai sebagai gap atau minta klarifikasi.
- Perubahan organisasi, unit aktif/nonaktif, atau aturan lintas wilayah harus tetap mengikuti keputusan domain, bukan disimpulkan dari nama role.

---

## 8. Workflow Application

- `Application.status` menyatakan state lifecycle aplikasi. Ia berbeda dari `current_review_level`, yang menyatakan tujuan review.
- Semua perubahan workflow harus melalui `ApplicationWorkflowService`: submit, request revision, validate, verify, dan reject.
- Jangan mengubah `status`, timestamp workflow, atau menulis `WorkflowHistory` secara langsung dari UI/model callback/controller.
- Pertahankan transisi yang disetujui: `DRAFT -> SUBMITTED`, `SUBMITTED -> REVISION_REQUIRED` atau `VALIDATED`, `VALIDATED -> VERIFIED` atau `REJECTED`, dan `REVISION_REQUIRED -> SUBMITTED`.
- `REJECTED` dan `VERIFIED` adalah status terminal. Kesalahan yang masih dapat diperbaiki menggunakan `REVISION_REQUIRED`, bukan `REJECTED`.
- Note untuk request revision dan reject wajib tidak kosong.
- Decision log mewajibkan revisi dari Kabupaten kembali melewati Kecamatan. Kode saat ini belum mendukung request revision dari `VALIDATED` dan belum menyimpan `current_review_level`. Jangan membuat routing ad hoc di UI; jika task menyentuh gap ini, implementasikan pada workflow/service dan schema setelah detail representasinya disepakati, lalu dokumentasikan.
- Pertahankan transaksi dan row lock pada perubahan status serta catatan history atomik. Tambahkan test untuk transisi valid/invalid, otorisasi, note, terminal state, dan history.

---

## 9. Service Layer

- `ApplicationWorkflowService` adalah boundary business operation workflow aplikasi.
- `DocumentService` adalah boundary operasi dokumen.
- Jangan bypass service dengan menulis langsung ke model dari Filament Action/controller untuk workflow, file, versioning, atau operasi multi-record yang punya invariant.
- Service mengatur urutan operasi, authorization server-side, transaksi, perubahan model, serta side effect. Jangan menaruh dependency Filament/UI di dalam service.
- Untuk operasi baru lintas beberapa model atau melibatkan storage/audit, cari service boundary terdekat. Buat service baru hanya bila memang ada operasi bisnis yang kohesif dan belum memiliki owner.
- Jangan memindahkan seluruh CRUD sederhana ke abstraksi baru tanpa kebutuhan; ikuti pola terdekat dan jaga scope tetap kecil.

---

## 10. Filament

- V1 memakai satu panel di `/admin`, didefinisikan oleh `AdminPanelProvider`.
- Provider saat ini menemukan resource, page, dan widget di `app/Filament/Resources`, `app/Filament/Pages`, dan `app/Filament/Widgets`. Direktori/resource operasional belum ada; verifikasi struktur sebelum mengasumsikan fitur telah dibuat.
- Resource dan Action bertanggung jawab pada presentasi, input, feedback, serta visibilitas yang sesuai Policy.
- Workflow Action harus memanggil Policy dan `ApplicationWorkflowService`; operasi file harus memanggil `DocumentService`.
- Jangan menulis transisi status, authorization final, query lintas-scope, atau pengelolaan file langsung dalam Filament Action.
- Visibilitas komponen hanyalah UX; service/policy/query harus tetap melindungi operasi di server.
- Jangan menambah panel kedua per role tanpa keputusan produk baru.

---

## 11. Dokumen dan File

- Semua operasi upload, replace, delete, dan download dokumen melalui `DocumentService`.
- Dokumen disimpan pada disk private `local` (`storage/app/private`), bukan disk/path publik.
- Jangan membuat URL publik atau symbolic link publik untuk dokumen sensitif. Download harus melewati pemeriksaan permission dan organization scope.
- Pertahankan validasi extension/MIME/signature, ukuran berdasarkan `DocumentCategory`, checksum, actor, dan waktu upload.
- Replace membuat `DocumentVersion` baru; jangan overwrite atau menghapus versi sebelumnya sebagai efek samping replace.
- Perubahan dokumen hanya diperbolehkan pada application `draft` atau `revision_required`, sesuai `DocumentPolicy`.
- Database transaction tidak membuat operasi filesystem atomik. Pertahankan cleanup/compensating behavior yang ada dan pikirkan kegagalan file maupun database. Jangan mengklaim file telah tersimpan hanya karena metadata berhasil.
- Retensi, file orphan cleanup, disk production bersama, dan penghapusan permanen belum diputuskan; jangan membuat kebijakan sendiri.

---

## 12. Audit Trail

- Transisi status dicatat di `WorkflowHistory` oleh `ApplicationWorkflowService` dalam transaksi yang sama dengan perubahan status.
- `AuditLog` adalah mekanisme terpisah untuk perubahan umum; jangan menggantikan workflow history dengan audit log umum.
- Tabel/model `AuditLog` tersedia, tetapi writer otomatis belum teridentifikasi. Jangan menyatakan semua perubahan tercatat sampai jalur pencatatannya benar-benar dibuat dan diuji.
- Jangan mencatat password, token, isi dokumen, atau data sensitif yang tidak diperlukan. Batasi akses ke log dan periksa redaksi data sebelum menyimpan old/new values.
- Jika memperluas cakupan audit, tetapkan event, actor, field sensitif, akses, dan retensi; dokumentasikan keputusan besar.

---

## 13. Testing

- Jalankan test yang paling dekat dengan behavior yang diubah setelah edit pertama; jangan menunggu sampai akhir untuk validasi pertama.
- Jalankan test terkait dan suite penuh jika blast radius atau gate repository memerlukannya. Command yang tersedia: `php artisan test` atau `composer test`; test terfokus dapat dijalankan dengan path/filter PHPUnit yang sesuai.
- Gunakan SQLite in-memory untuk feedback lokal cepat (`phpunit.xml`). Untuk perubahan migration, locking, foreign key, atau engine-specific behavior, rencanakan pemeriksaan MySQL juga.
- Tambahkan/ubah test untuk behavior yang diubah, termasuk jalur gagal dan batas authorization. Jangan sekadar menguji happy path.
- Jangan mematikan, menghapus, melemahkan assertion, atau mengubah test agar test tampak pass.
- Saat ini suite berisi test contoh, bukan cakupan domain lengkap. Jangan menganggap tidak adanya test gagal berarti workflow atau authorization sudah tervalidasi.
- Jalankan formatter/linter yang relevan bila file PHP disentuh, misalnya `vendor/bin/pint --test`; jangan melakukan format massal pada file yang tidak terkait.
- Jika test gagal, bedakan kegagalan yang disebabkan perubahan dari kegagalan lama; perbaiki hanya yang terkait task dan laporkan sisanya.

---

## 14. Security

- Perlakukan NIK, data keluarga, nomor KK, rekening, dan dokumen sebagai data sensitif.
- Terapkan authorization server-side pada baca, perubahan, download, dan export; cek scope wilayah pada setiap jalur.
- Jangan mempercayai input hidden, ID dari browser, nama file, MIME dari client, atau visibility UI. Validasi file melalui mekanisme `DocumentService`.
- Jangan mengekspos storage private, secret, password, token, atau detail sensitif melalui log/error/response.
- Pertahankan hashing password dari model dan perlindungan middleware sesi panel.
- Perubahan security-sensitive harus disertai test yang membuktikan akses ditolak pada scope/state/permission yang salah.

---

## 15. Aturan Migration yang Dibagikan

- Anggap migration yang sudah ada di shared environment sebagai immutable. Buat migration korektif baru untuk mengubah schema.
- Sebelum membuat migration, periksa constraint, data yang sudah ada, relasi soft delete, urutan migration, dampak rollback, serta kompatibilitas SQLite/MySQL.
- Jangan drop/rename kolom atau constraint tanpa strategi migrasi data dan konfirmasi dampak.
- Jangan menjalankan perintah destructive pada database bersama/production. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau seeder yang menghapus/mengganti data tanpa instruksi dan persetujuan eksplisit.
- Jika migration gagal karena mismatch ID atau tipe FK yang telah diketahui, jangan menambahkan workaround lokal; hentikan dan minta keputusan yang konsisten.

---

## 16. Git dan Diff

- Sebelum edit, periksa `git status` dan pahami perubahan yang sudah ada pada file target. Perubahan yang tidak dibuat oleh task dianggap milik user.
- Jangan revert, reset, checkout, atau membersihkan perubahan yang tidak terkait. Jangan mengubah file lain hanya karena worktree sudah dirty.
- Setelah edit, periksa diff dan pastikan hanya file dalam scope task berubah. Jangan stage atau commit kecuali diminta.
- Jangan melakukan rename/reformat massal atau memperbaiki issue unrelated dalam task sempit.

---

## 17. Requirement yang Belum Jelas

- Jangan mengarang aturan eligibility, klasifikasi orphan di luar kasus yang dipastikan, data wajib, pemilik rekening, periode aktif, atau routing review yang belum didefinisikan.
- Tandai area tersebut sebagai TBD. Ajukan pertanyaan singkat bila keputusan memengaruhi schema, authorization, workflow, data sensitif, atau perilaku yang sulit dibalik.
- Bila task dapat dikerjakan tanpa keputusan itu, implementasikan bagian yang jelas saja dan jangan memperluas scope.
- Keputusan bisnis/arsitektur baru harus dicatat pada `docs/DECISIONS.md` dan dokumen terkait setelah disetujui.

---

## 18. Bila Implementasi Berbeda dari Dokumentasi

- Jangan memperlakukan perbedaan sebagai izin otomatis untuk mengubah salah satunya.
- Gunakan migration, model, policy, service, config, dan test untuk menjelaskan perilaku aktual; gunakan `LOCKED` decision/PRD untuk menjelaskan perilaku yang seharusnya berlaku.
- Tunjukkan dengan jelas fakta aktual, requirement yang bertentangan, risiko, serta file yang perlu diselaraskan.
- Jika keputusan sudah jelas dan task memberi wewenang untuk memperbaiki implementasi, ubah kode pada boundary yang benar, tambahkan test, dan perbarui dokumentasi bila keputusan berubah.
- Jika arah penyelesaian tidak jelas atau memerlukan perubahan keputusan, jangan menebak; tandai gap/TBD dan minta keputusan.

---

## 19. Hindari Refactor di Luar Scope

- Ubah hanya slice yang dibutuhkan untuk menyelesaikan behavior task.
- Jangan mengganti arsitektur, menambah package, mengubah public API, atau memindahkan file tanpa kebutuhan langsung dan alasan yang dapat diuji.
- Jangan memperbaiki semua ketidakkonsistenan yang ditemukan saat inspeksi. Catat issue terpisah jika tidak memblokir task.
- Untuk perubahan besar/cross-layer, pecah pekerjaan menjadi langkah kecil, validasi tiap langkah, dan dokumentasikan keputusan serta dampak.
- Model/agent dengan konteks terbatas harus menerima task yang kecil, file/boundary yang jelas, acceptance criteria, dan test verifikasi yang eksplisit.

---

## 20. Completion Checklist

Sebelum menyatakan task selesai, pastikan:

- [ ] Perilaku yang diminta cocok dengan PRD dan keputusan `LOCKED`.
- [ ] Implementasi berada pada layer yang tepat; Policy dan Service tidak dilewati.
- [ ] Organization scope serta workflow state tetap diperiksa server-side.
- [ ] Migration/model/enum/relations konsisten dan constraint lama yang disepakati tetap terjaga.
- [ ] Dokumen dan audit trail mengikuti boundary serta aturan retensi yang telah diputuskan.
- [ ] Test terarah sudah dijalankan; hasil dan test gap dilaporkan dengan jujur.
- [ ] Diff hanya berisi perubahan dalam scope; perubahan user yang sudah ada tetap utuh.
- [ ] Dokumentasi keputusan/arsitektur diperbarui bila perubahan mengubah kontrak atau keputusan penting.
- [ ] Tidak ada requirement ambigu yang diselesaikan lewat asumsi tersembunyi.
