# Domain Model
# Manajemen Data Anak Yatim - V1

| Informasi Dokumen | Detail |
|---|---|
| Versi Dokumen | 1.0 |
| Tanggal | 2026-10-02 |
| Status | Final |
| Sumber Fakta | Implementasi Laravel aktual + PRD final |
| Basis Teknis | Laravel 13, Filament 5, Livewire/Blade, Spatie Permission, Eloquent, PHP-backed Enum |
| Catatan | Dokumen ini menggambarkan domain yang didukung oleh schema dan kode saat ini; tidak menambah model baru di source code |

---

## 1. Tujuan Dokumen Domain

Dokumen ini memperjelas konsep bisnis, entitas utama, hubungan, dan aturan operasional yang benar-benar terwakili dalam implementasi saat ini. Dokumen ini bukan spesifikasi produk yang dibayangkan secara umum, melainkan representasi domain yang terikat pada model, enum, policy, dan migrasi yang ada di repo.

Secara khusus, dokumen ini menjelaskan:

- entitas utama dan keterkaitannya;
- status dan transisi aplikasi;
- batas akses berdasarkan organisasi dan role;
- aturan bisnis yang sudah tertanam dalam schema dan workflow;
- konsep review level dan aturan status yang masih bersifat konseptual;
- area yang masih TBD karena belum ada representasi data yang jelas di codebase saat ini.

---

## 2. Sumber Kebenaran Domain

Sumber yang dipakai sebagai otoritas utama adalah:

- PRD final di `docs/PRD.md`;
- model Eloquent di `app/Models/*`;
- enum di `app/Enums/*`;
- policy di `app/Policies/ApplicationPolicy.php`;
- workflow service di `app/Services/ApplicationWorkflowService.php`;
- migrasi di `database/migrations/*`;
- seeder peran dan permission di `database/seeders/RoleAndPermissionSeeder.php`.

Semua aturan berikut harus dibaca sebagai representasi implementasi yang ada, bukan sebagai asumsi ideologis.

---

## 3. Gambaran Umum Domain

Domain ini berfokus pada pengelolaan data anak yatim untuk kebutuhan administrasi internal pemerintah. Struktur domain utama adalah:

- wilayah administrasi: Kabupaten → Kecamatan → Kelurahan/Desa;
- pengguna internal yang bekerja dalam unit organisasi;
- data anak dan keluarga;
- aplikasi bantuan per anak per tahun program;
- dokumen pendukung dan versi dokumen;
- audit trail perubahan status.

Ruang lingkup bisnis paling jelas terlihat pada relasi berikut:

- `User` memiliki satu `OrganizationUnit` dan banyak role/permission;
- `Child` terikat ke `Household` dan `Address`;
- `Child` memiliki banyak `ChildRelationship` ke `Person` (orang tua / wali);
- `Application` terikat ke `Child` dan `User` pembuat;
- `Application` memiliki banyak `Document`, `BankAccount`, `WorkflowHistory`;
- `OrganizationUnit` membentuk struktur hirarki berbasis wilayah.

---

## 4. Entitas Domain Utama

### 4.1 OrganizationUnit

`OrganizationUnit` adalah representasi unit organisasi operasional.

Atribut utama:

- `parent_id` untuk hirarki organisasi;
- `name`;
- `type` dengan nilai:
  - `regency`
  - `district`
  - `village`
- `regency_id`, `district_id`, `village_id` sebagai mapping ke data wilayah;
- `is_active`.

Hierarki yang terdefinisi:

- Kabupaten
  - Kecamatan
    - Kelurahan/Desa

Implementasi akses data mengikuti hierarki ini. Scope pengguna dan child disaring dengan aturan berdasarkan `organization_unit_id` dan `village_id/district_id/regency_id`.

### 4.2 User

`User` adalah pengguna internal yang masuk ke sistem dengan `HasRoles` dan permission dari Spatie Permission.

Atribut penting:

- `name`;
- `email`;
- `password`;
- `organization_unit_id`;
- `is_active`.

Hubungan penting:

- `organizationUnit()` → `OrganizationUnit`;
- `applications()` → aplikasi yang dibuat oleh user sebagai creator;
- `workflowHistories()` → perubahan status yang diambil oleh user;
- `uploadedDocuments()` → dokumen yang diunggah user.

Fungsi akses penting:

- `isInOrganizationScope($villageId)`;
- `canAccessChild(Child $child)`;
- `canAccessApplication(Application $application)`.

Akses didasarkan pada organisasi user, bukan hanya role semata.

### 4.3 Person

`Person` merepresentasikan orang yang terlibat dalam struktur keluarga atau wali.

Atribut:

- `name`;
- `gender` dengan nilai `male | female`;
- `occupation` nullable;
- `status` dengan nilai `alive | deceased | unknown`.

`Person` bukan pengguna aplikasi; ini adalah entitas data orang-orang yang terhubung ke child melalui `ChildRelationship`.

### 4.4 Household

`Household` memuat data rumah tangga induk dari child.

Atribut yang relevan:

- `kk_number`.

Hubungan:

- `children()` → `Child` yang tinggal dalam rumah tangga tersebut.

### 4.5 Address

`Address` menyimpan lokasi geografis dan konteks tinggal anak.

Atribut:

- `village_id`;
- `rt`;
- `rw`;
- `address_detail` nullable;
- `living_with` dengan nilai:
  - `parents`
  - `alone`
  - `relative`
  - `other`

Hubungan:

- `village()` → `Village`;
- `children()` → `Child` yang memiliki alamat ini.

### 4.6 Child

`Child` adalah entitas inti yang menampung data anak yang diproses dalam sistem.

Atribut utama:

- `household_id`;
- `address_id`;
- `name`;
- `nik` (unik dalam schema);
- `birth_place`;
- `birth_date`;
- `gender`.

Hubungan utama:

- `household()`
- `address()`
- `relationships()` -> `ChildRelationship`
- `applications()` -> `Application`

Aturan akses data:

- `scopeForUser($user)` membatasi child sesuai organisasi user (`village`, `district`, atau `regency`).
- `super_admin` atau user tanpa `organization_unit_id` memiliki akses tanpa pembatas wilayah.

### 4.7 ChildRelationship

`ChildRelationship` menghubungkan anak dengan orang tua/wali.

Atribut:

- `child_id`;
- `person_id`;
- `relationship_type` dengan nilai:
  - `father`
  - `mother`
  - `guardian`
- `is_primary` boolean.

Relasi ini mendefinisikan status keluarga yang relevan untuk data bantuan dan hasil pengelompokan status yatim/duka.

### 4.8 Application

`Application` adalah entitas utama proses bantuan.

Atribut utama:

- `child_id`;
- `period_year` integer;
- `status` enum `ApplicationStatus`;
- `created_by`;
- `submitted_at` nullable;
- `validated_at` nullable;
- `verified_at` nullable;
- timestamps dan soft delete.

Kebijakan integritas:

- unik kombinasi `child_id + period_year`;
- satu child hanya dapat memiliki satu aplikasi per tahun program;
- `status` bertindak sebagai state aplikasi terkini;
- `workflow_history` mencatat setiap transisi.

### 4.9 WorkflowHistory

`WorkflowHistory` berfungsi sebagai audit trail untuk perubahan status.

Atribut:

- `application_id`;
- `from_status` nullable;
- `to_status`;
- `action`;
- `note` nullable;
- `acted_by`;
- `acted_at`.

Ini adalah sumber paling jelas untuk menelusuri keputusan review, revisi, validasi, verifikasi, dan penolakan.

### 4.10 Document & DocumentVersion

`Document` adalah berkas pendukung per aplikasi.

Atribut:

- `application_id`;
- `document_category_id`;
- `original_filename`;
- `storage_path`;
- `mime_type`;
- `file_size`;
- `checksum`;
- `uploaded_by`;
- `uploaded_at`.

`DocumentCategory` menentukan jenis dokumen yang diperbolehkan dan parameter validasinya:

- `code` unik;
- `name`;
- `is_required` boolean;
- `allowed_mime_types` array;
- `max_file_size_kb` integer.

`DocumentVersion` menyimpan rekam jejak versi file. `Document::currentVersion()` mengambil versi terbaru berdasarkan `version_number`.

### 4.11 BankAccount

`BankAccount` merepresentasikan rekening yang terikat ke aplikasi.

Atribut utama:

- `application_id`;
- `bank_name`;
- `account_number`;
- `account_holder_name` nullable;
- `holder_type` dengan nilai `child | father | mother | guardian | unknown`.

---

## 5. State Application dan Workflow

### 5.1 Status yang ada

Nilai status yang terdefinisi di `ApplicationStatus` adalah:

- `draft`
- `submitted`
- `revision_required`
- `validated`
- `verified`
- `rejected`

Status ini dipakai di `applications.status` dan juga di `workflow_histories.to_status` / `from_status`.

### 5.2 Arti status dalam domain

- `status` menjelaskan state aplikasi saat ini, yaitu status bisnis yang sedang dipenuhi oleh aplikasi tersebut pada titik waktu tertentu.
- `status` bukan sekadar label UI; status ini berfungsi sebagai gate otorisasi dan transisi workflow, seperti yang diatur di `ApplicationWorkflowService` dan `ApplicationPolicy`.
- `workflow_history` memberikan jejak setiap perubahan status, sehingga state aplikasi bisa ditelusuri secara audit.

### 5.3 Transisi yang diimplementasikan

Transisi yang diizinkan oleh `ApplicationWorkflowService`:

- `draft -> submitted`
- `submitted -> revision_required`
- `submitted -> validated`
- `validated -> verified`
- `submitted -> rejected`
- `validated -> rejected`
- `revision_required -> submitted`

Aturan penting:

- `revision_required` dipakai untuk koreksi data/dokumen, bukan penolakan terminal.
- `rejected` bersifat terminal.
- `request_revision` hanya bisa dilakukan dari `submitted`.
- `verify` hanya bisa dilakukan dari `validated`.
- `reject` memerlukan catatan (`note`) karena bersifat keputusan final yang harus terdokumentasi.
- `request_revision` juga memerlukan note.

### 5.4 Review Level dan current_review_level

`current_review_level` adalah konsep domain yang disepakati dalam PRD untuk menjelaskan tujuan review pada level tertentu. Dalam kata lain, `current_review_level` menunjukkan level proses yang sedang berlaku: apakah aplikasi sedang dalam review awal, validasi tingkat tengah, atau verifikasi akhir.

Dalam implementasi saat ini, field tersebut belum ada sebagai kolom database. Yang ada adalah:

- `status` aplikasi (`submitted`, `validated`, `verified`, dll.);
- role/permission user (`admin_kelurahan`, `admin_kecamatan`, `admin_kabupaten`);
- policy yang membatasi tindakan berdasarkan state aplikasi.

Karena itu, `current_review_level` lebih tepat dipahami sebagai konsep bisnis/operasional daripada kolom yang terpersistensi. Dalam implementasi yang ada, pembeda “level review” lebih banyak direpresentasikan melalui peran dan policy, bukan oleh atribut database yang terpisah.

---

## 6. Aturan Bisnis Domain yang Sudah Terepresentasi

### 6.1 Satu child, satu aplikasi per tahun

Aturan inti yang sudah tertanam dalam schema:

- `applications` memiliki unique index pada `child_id` dan `period_year`;
- `period_year` adalah tahun program atau tahun anggaran yang dituju.

Artinya domain mengharuskan:

- satu anak tidak dapat memiliki lebih dari satu aplikasi pada tahun yang sama;
- duplikasi aplikasi untuk periode yang sama merupakan pelanggaran integritas data.

### 6.2 Review lintas level harus tetap terjaga

PRD menetapkan bahwa revisi dari level yang lebih tinggi tetap harus kembali ke level sebelumnya, agar tidak bypass mekanisme review. Ini dicerminkan secara konseptual dalam workflow dan kebijakan review:

- review dilakukan pada `submitted`;
- validasi di level menengah dilakukan terhadap aplikasi yang sama;
- verifikasi final di Kabupaten/Kesra dilakukan terhadap aplikasi yang sudah `validated`;
- revisi harus kembali ke status `revision_required` dan ditangani ulang dengan proses submit berikutnya.

### 6.3 Penolakan adalah terminal

Domain membedakan dengan jelas antara:

- koreksi data/dokumen (`revision_required`);
- keputusan final tidak layak (`rejected`).

Revisi tidak sama dengan penolakan. Penolakan tidak dibolehkan untuk situasi koreksi biasa.

### 6.4 Audit trail wajib ada

Setiap perubahan status dicatat di `workflow_histories` dengan:

- `from_status`;
- `to_status`;
- `action`;
- `note`;
- `acted_by`;
- `acted_at`.

Ini merupakan bukti bahwa domain mengharuskan traceability dan akuntabilitas atas keputusan.

### 6.5 Dokumen dan versi dokumen harus dapat ditelusuri

Dokumen aplikasi disimpan per aplikasi dan dapat memiliki banyak versi melalui `DocumentVersion`. Domain memerlukan:

- file upload per dokumen tertentu;
- identifikasi kategori dokumen (`DocumentCategory`);
- kontrol file size dan mime type;
- rekam jejak versi file untuk audit dan perbaikan.

### 6.6 Status yatim didasarkan pada status ayah/ibu

Domain `PersonStatus` mendefinisikan status orang tua atau wali sebagai:

- `alive`
- `deceased`
- `unknown`

Aturan bisnis yang umum dipakai di domain bantuan anak yatim adalah:

- ayah meninggal dan ibu masih hidup = `yatim`;
- ayah hidup dan ibu meninggal = `yatim` atau `yatim piatu` tergantung definisi operasional; dalam konteks domain umum yang paling umum dipahami, status tersebut masih termasuk kategori yatim bila salah satu orang tua sudah meninggal dan yang lain masih hidup;
- bila kedua orang tua meninggal, maka istilah yang lebih tepat adalah `yatim piatu`.

Catatan penting: dalam schema saat ini, status ini bukan field terpisah di `Child` atau `Application`. Ini adalah aturan derivasi bisnis yang dibangun dari status orang tua/wali di `Person`, bukan data yang disimpan sebagai kolom status yatim yang terpisah.

---

## 7. Otorisasi dan Scope Organisasi

### 7.1 Role yang ada

Seeder mendefinisikan role berikut:

- `super_admin`
- `admin_kabupaten`
- `admin_kecamatan`
- `admin_kelurahan`

### 7.2 Hak akses per role

Berdasarkan `RoleAndPermissionSeeder`:

- `super_admin` fokus pada manajemen sistem dan audit umum, tidak diberi hak `verify applications` secara eksplisit untuk memisahkan peran administratif dengan otoritas bisnis.
- `admin_kabupaten` memiliki izin untuk `verify applications` dan `reject applications`.
- `admin_kecamatan` memiliki izin untuk `review applications`, `validate applications`, dan `request revision`.
- `admin_kelurahan` memiliki izin untuk mengelola child, aplikasi, dan dokumen di wilayahnya.

### 7.3 Scope wilayah

Akses child dan application dibatasi berdasarkan lokasi wilayah user:

- `village` scope -> hanya data di kelurahan yang sama;
- `district` scope -> semua data di kecamatan yang sama;
- `regency` scope -> semua data di kabupaten yang sama;
- `super_admin` -> akses tidak dibatasi oleh wilayah.

Fungsi kunci yang mewujudkan aturan ini:

- `User::isInOrganizationScope()`;
- `User::canAccessChild()`;
- `User::canAccessApplication()`;
- `Child::scopeForUser()`;
- `Application::scopeForUser()`.

### 7.4 Policy workflow

`ApplicationPolicy` membatasi keputusan berdasarkan status aplikasi dan permission user:

- `create` -> hanya jika user punya permission dan child berada dalam scope akses;
- `update` -> hanya saat status `draft` atau `revision_required`;
- `submit` -> hanya status `draft` atau `revision_required`;
- `review` -> hanya status `submitted`;
- `validate` -> hanya status `submitted`;
- `requestRevision` -> hanya status `submitted`;
- `verify` -> hanya status `validated`;
- `reject` -> hanya status `submitted` atau `validated`.

Ini menunjukkan bahwa aturan status dan otorisasi tidak dipisahkan; keduanya bersatu dalam satu model domain.

---

## 8. Model Data Khusus

### 8.1 Relasi keluarga

`ChildRelationship` adalah mekanisme utama untuk menghubungkan child ke orang tua/wali.

Domain membutuhkan setidaknya:

- child dapat memiliki beberapa relationship, tetapi biasanya satu hubungan utama yang mewakili orang tua/wali utama;
- hubungan ini dipakai untuk keperluan data penerima bantuan, validasi keluarga, serta klasifikasi asumsi keluarga.

### 8.2 Model alamat dan rumah tangga

`Address` dan `Household` memberi konteks sosial-ekonomi dan wilayah administrasi. Domain di sini tidak hanya soal lokasi, tetapi juga tentang kondisi tinggal anak, seperti:

- tinggal bersama orang tua;
- tinggal sendiri;
- tinggal bersama kerabat;
- jenis kondisi tinggal lainnya.

### 8.3 Model dokumen

Dokumen adalah objek dokumentasi pendukung per proses bantuan. Domain adalah tentang integritas bukti, bukan sekadar file. Oleh karena itu, model menangkap:

- file asli;
- category dokumen;
- MIME type dan ukuran file;
- checksum;
- siapa yang upload;
- kapan upload;
- versi dokumen.

---

## 9. Batasan Implementasi Saat Ini

Ada beberapa area yang sangat relevan secara domain, namun belum sepenuhnya terstruktur sebagai field model yang eksplisit:

1. `current_review_level` belum ada sebagai kolom database; ini masih konsep domain, bukan field persisted.
2. Status yatim / yatim piatu belum menjadi field terpisah di `Child` atau `Application`; ini merupakan derived business rule dari `Person.status` orang tua/wali.
3. Kategori dokumen spesifik per program atau per jenis bantuan belum dibakukan dalam schema; kategori masih bersifat master generic dan dapat dikonfigurasi secara administratif.
4. Rincian laporan KPI per role belum otomatis diimplementasikan dalam schema; lebih banyak bersifat kebutuhan operasional daripada model data.
5. Jadwal program bantuan, pengajuan dibuka/ditutup, dan konfigurasi periode program belum ada sebagai entitas domain yang terpisah dalam model yang terlihat saat ini.

---

## 10. Daftar Business Rule yang Masih TBD / Butuh Konfirmasi

Daftar berikut adalah area yang tetap harus dikonfirmasi oleh stakeholder atau product owner sebelum dianggap final sebagai requirement yang dipaksakan:

- Apakah `current_review_level` harus benar-benar dibuat menjadi field yang tersimpan di database, atau cukup dipahami sebagai konsep workflow yang dikelola di UI/API?
- Apakah definisi status yatim / yatim piatu harus dibuat eksplisit di model data, atau tetap dihitung dari `Person.status` orang tua/wali?
- Apakah untuk setiap program bantuan perlu ada master dokumen yang berbeda-beda per jenis program, atau cukup satu kategori dokumen umum yang dapat dikonfigurasi?
- Apakah kebutuhan pengajuan bantuan dibatasi hanya pada satu tahun program (`period_year`) atau ada skema multi-tahun yang lebih kompleks di masa depan?
- Apakah revisi dari Kabupaten setelah Kelurahan harus selalu kembali ke Kecamatan, atau ada pengecualian domain tertentu yang perlu diatur aturan khusus?
- Apakah laporan per wilayah dan per role perlu memiliki struktur data yang lebih formal (misalnya table/report metadata) di versi mendatang?
- Apakah jadwal pengajuan, periode bantuan, atau window approval perlu dijadikan model domain tersendiri?
- Apakah ada kebutuhan untuk menambahkan field status lain pada `Child` untuk klasifikasi bantuan sosial, seperti `is_orphan`, `is_vulnerable`, atau `case_type`?

---

## 11. Kesimpulan

Domain inti sistem ini adalah pengelolaan data anak yang terintegrasi dengan wilayah administrasi, workflow aplikasi bantuan, dan audit keputusan. Implementasi saat ini sudah cukup matang untuk mendukung:

- data child/family/location;
- satu aplikasi per child per tahun;
- transisi status aplikasi yang jelas;
- review dan verification berdasarkan role dan wilayah;
- dokumentasi perubahan status secara audit;
- batasan akses berbasis organisasi dan permission.

Namun, beberapa bagian domain masih perlu pemastian tambahan agar tidak disalahartikan sebagai requirement yang benar-benar terimplementasi. Yang paling penting adalah membedakan antara:

- `status` aplikasi sebagai state actual processing;
- `current_review_level` sebagai konsep review/decision intent yang belum ada sebagai field database eksplisit;
- status yatim sebagai aturan derived dari orang tua/wali, bukan atribut yang tersimpan secara terpisah.

Dengan pengertian itu, domain model ini tetap konsisten dengan implementasi Laravel yang ada dan dengan PRD final yang menjadi landasan keputusan produk.
