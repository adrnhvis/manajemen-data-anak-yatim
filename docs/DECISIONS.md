# Decision Record
# Manajemen Data Anak Yatim - V1

| Informasi Dokumen | Detail |
|---|---|
| Versi Dokumen | 1.0 |
| Tanggal | 2026-10-02 |
| Status | Acuan keputusan V1 |
| Sumber | `docs/PRD.md`, `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, dan implementasi repository |

Dokumen ini mencatat keputusan yang penting untuk menjaga konsistensi domain dan arsitektur. `LOCKED` berarti tidak boleh diubah tanpa keputusan eksplisit dari pemilik produk/arsitektur. `PROPOSED` berarti arah yang disarankan tetapi belum dikonfirmasi. `TBD` berarti belum ada keputusan; jangan mengisi kekosongan dengan asumsi.

Keputusan yang berstatus `LOCKED` tetap dapat memiliki gap implementasi. Context dan Consequence menyebutkan gap tersebut; status tidak boleh dibaca sebagai klaim bahwa seluruh fitur sudah selesai dibuat.

---

## DEC-01: Laravel 13 sebagai Framework Aplikasi

**Status:** LOCKED

**Context:** Composer mensyaratkan `laravel/framework ^13.17`; aplikasi, konfigurasi, dan service memakai struktur Laravel.

**Decision:** Laravel 13 menjadi framework backend V1 dan runtime utama aplikasi.

**Consequence:** Perubahan major framework atau penggantian framework memerlukan persetujuan eksplisit dan evaluasi kompatibilitas package, migrasi, serta deployment.

---

## DEC-02: Satu Filament Panel untuk V1

**Status:** LOCKED

**Context:** `AdminPanelProvider` mendaftarkan satu panel dengan ID `admin`, path `/admin`, login, dan discovery resources/pages/widgets.

**Decision:** V1 memakai satu Filament panel sebagai panel administrasi internal untuk seluruh role yang berwenang; perbedaan akses ditentukan oleh policy, permission, dan scope organisasi, bukan panel terpisah per role.

**Consequence:** Panel tambahan tidak dibuat tanpa kebutuhan produk yang disetujui. Provider panel tersedia, tetapi resources/pages operasional belum ada; keputusan ini tidak menyatakan UI administrasi sudah lengkap.

---

## DEC-03: Filament 5, Livewire, dan Blade sebagai Presentation Stack

**Status:** LOCKED

**Context:** Composer memasang Filament `~5.0`; provider memakai Filament Panel dan middleware web. V1 menetapkan interaksi panel dengan Livewire dan rendering Blade.

**Decision:** Filament 5 menjadi presentation/admin layer; Livewire menangani interaksi panel dan Blade menjadi rendering view.

**Consequence:** Alur presentation harus menggunakan pola Filament/Livewire/Blade yang konsisten. Resource dan action tetap memanggil policy/service dan tidak menjadi tempat implementasi workflow domain.

---

## DEC-04: Tidak Ada Inertia, Vue, atau React pada V1

**Status:** LOCKED

**Context:** PRD menetapkan stack server-rendered berbasis panel administrasi; ketiganya bukan bagian dari V1.

**Decision:** V1 tidak menambahkan Inertia, Vue, atau React sebagai presentation stack.

**Consequence:** Jangan menambah dependency, scaffold, atau frontend kedua untuk memenuhi kebutuhan V1 tanpa keputusan eksplisit yang mengubah scope teknologi.

---

## DEC-05: Spatie Laravel Permission untuk Role dan Permission

**Status:** LOCKED

**Context:** `spatie/laravel-permission` menjadi dependency; `User` memakai `HasRoles`; seeder menetapkan role dan permission bernama.

**Decision:** Otorisasi berbasis role/permission menggunakan Spatie Laravel Permission dan Laravel Policy.

**Consequence:** Jangan mengganti sistem permission atau menambahkan pemeriksaan role ad hoc yang melewati policy tanpa alasan dan keputusan arsitektur yang disetujui.

---

## DEC-06: Role Tidak Sama dengan Organization Scope

**Status:** LOCKED

**Context:** Role menjawab aksi apa yang boleh dilakukan; `organization_unit_id` dan lokasi child menjawab data wilayah mana yang boleh diakses. Model `User` dan `Child` menerapkan pemeriksaan scope secara terpisah.

**Decision:** Role/permission dan organization scope adalah dua syarat otorisasi yang berbeda. Memiliki role tidak dengan sendirinya memberi akses ke seluruh wilayah.

**Consequence:** Query collection harus menggunakan scope wilayah dan akses resource harus memeriksa wilayah. Menyembunyikan data hanya melalui UI bukan kontrol akses yang memadai.

---

## DEC-07: Super Admin Bukan Otoritas Verifikasi Bisnis Otomatis

**Status:** LOCKED

**Context:** Seeder memberi `super_admin` permission administrasi sistem dan beberapa permission baca, tetapi tidak memberi `verify applications`. `admin_kabupaten` memiliki permission verifikasi dan penolakan.

**Decision:** Hak administrasi sistem Super Admin tidak otomatis mencakup kewenangan verifikasi bisnis aplikasi. Otoritas verifikasi mengikuti permission bisnis yang diberikan secara eksplisit.

**Consequence:** Jangan memberi kemampuan verifikasi hanya karena user adalah Super Admin. Perubahan kewenangan harus diputuskan dan tercermin pada permission serta policy.

---

## DEC-08: Autentikasi Email dan Password untuk V1

**Status:** LOCKED

**Context:** Model `User` menyimpan email dan password; email unik; Filament panel mengaktifkan login dan memakai autentikasi sesi web.

**Decision:** V1 menggunakan autentikasi internal dengan email dan password melalui login panel.

**Consequence:** Metode autentikasi lain tidak menjadi asumsi V1. Alur provisioning akun dan ada/tidaknya self-registration belum ditetapkan; route registrasi tidak ditemukan saat ini, sehingga jangan menganggap flow tersebut sudah tersedia atau dilarang sebagai keputusan produk. Metode autentikasi baru memerlukan keputusan eksplisit dan penilaian keamanan.

---

## DEC-09: Pengelolaan User oleh Super Admin

**Status:** LOCKED

**Context:** Permission `manage users` diberikan kepada `super_admin`; `UserPolicy` menggunakan permission tersebut untuk aksi manajemen user.

**Decision:** Pengelolaan akun internal dilakukan oleh Super Admin melalui hak `manage users`; user tidak memperoleh hak pengelolaan akun hanya karena berada pada unit organisasi tertentu.

**Consequence:** User management tetap dibatasi oleh `manage users`; cakupan provisioning/self-registration masih **TBD**. Filament resource untuk pengelolaan user belum tersedia dan tetap harus dibangun jika diperlukan.

---

## DEC-10: Master Administrative Data oleh Super Admin

**Status:** LOCKED

**Context:** Seeder memberi `manage master data` kepada `super_admin`, dan `MasterDataPolicy` mensyaratkan permission tersebut.

**Decision:** Perubahan master data administratif dilakukan oleh user dengan permission `manage master data`, yang pada role seed V1 dimiliki Super Admin.

**Consequence:** Jangan memberikan hak perubahan master data kepada seluruh admin wilayah tanpa keputusan eksplisit. UI pengelolaan master data belum dapat dianggap tersedia sebelum Filament resources dibuat.

---

## DEC-11: Child dan Application adalah Entitas Terpisah

**Status:** LOCKED

**Context:** `Child` menyimpan identitas anak yang relatif menetap; `Application` merujuk `child_id` dan menyimpan `period_year`, status, creator, serta timestamp proses.

**Decision:** Data identitas anak tidak digabungkan dengan aplikasi bantuan. Satu child dapat mempunyai aplikasi untuk periode yang berbeda, dengan batas satu aplikasi untuk periode yang sama.

**Consequence:** Perubahan identitas anak dan perubahan proses bantuan memiliki lifecycle terpisah. Dokumen, rekening, status, serta riwayat workflow tetap terkait ke application yang bersangkutan.

---

## DEC-12: Satu Application per Child per Period Year

**Status:** LOCKED

**Context:** Migrasi aplikasi menetapkan unique index `(child_id, period_year)`.

**Decision:** Kombinasi `child_id` dan `period_year` harus unik; child tidak boleh memiliki dua aplikasi pada tahun program yang sama.

**Consequence:** Constraint database adalah penjaga integritas utama dan harus dipertahankan pada perubahan schema. Alur koreksi tidak membuat application duplikat untuk kombinasi tersebut.

---

## DEC-13: Period Year adalah Tahun Anggaran/Program

**Status:** LOCKED

**Context:** PRD menetapkan `period_year` sebagai tahun anggaran atau tahun program bantuan; kolom aplikasi bertipe integer.

**Decision:** `period_year` mengidentifikasi periode tahun program bantuan yang dimohonkan, bukan tahun lahir anak atau sekadar timestamp pembuatan record.

**Consequence:** Pencarian, laporan, dan aturan duplikasi aplikasi menggunakan periode program ini. Jendela tanggal buka/tutup program belum mempunyai entitas atau konfigurasi terimplementasi dan tidak boleh diasumsikan sudah ditegakkan.

---

## DEC-14: NIK Child Harus Unik

**Status:** LOCKED

**Context:** Kolom `children.nik` memiliki unique constraint dan panjang 16 karakter.

**Decision:** Satu nilai NIK hanya boleh tercatat pada satu child.

**Consequence:** Validasi aplikasi boleh memberi pesan awal, tetapi constraint database harus tetap ada. Prosedur koreksi NIK duplikat atau salah input belum ditetapkan dan perlu mengikuti kebijakan data yang disetujui.

---

## DEC-15: Nomor KK Tidak Dipaksa Unik

**Status:** LOCKED

**Context:** `households.kk_number` berupa string, tetapi migrasi tidak memberi unique constraint.

**Decision:** V1 tidak mensyaratkan nomor KK unik pada tabel household.

**Consequence:** Jangan menambahkan unique constraint atau validasi yang menolak nomor KK berulang tanpa keputusan domain baru. Jika kebutuhan deduplikasi rumah tangga muncul, aturan penggabungan dan identitas household harus ditentukan terlebih dahulu.

---

## DEC-16: Bank Account Number Tidak Dipaksa Unik

**Status:** LOCKED

**Context:** Kolom `bank_accounts.account_number` tidak memiliki unique constraint.

**Decision:** Nomor rekening tidak dijadikan identifier unik global atau unik per application pada V1.

**Consequence:** Jangan menolak rekening yang sama hanya berdasarkan nomor rekening. Aturan duplikasi lintas aplikasi atau lintas penerima masih dapat diputuskan kemudian dengan konteks bisnis yang jelas.

---

## DEC-17: Bank Account Melekat pada Application

**Status:** LOCKED

**Context:** `BankAccount` memiliki foreign key `application_id`; model menyediakan relasi `Application::bankAccounts()`.

**Decision:** Data rekening disimpan sebagai bagian dari application tertentu, bukan sebagai rekening global yang dimiliki langsung oleh child.

**Consequence:** Rekening dapat dicatat sesuai konteks aplikasi/periode. Jangan memindahkan rekening menjadi atribut global child tanpa keputusan perubahan model domain.

---

## DEC-18: Pemilik Rekening Tidak Boleh Diasumsikan

**Status:** LOCKED

**Context:** `account_holder_name` nullable; `holder_type` nullable dan enum mengizinkan `child`, `father`, `mother`, `guardian`, atau `unknown`.

**Decision:** Sistem tidak menyimpulkan pemilik rekening dari hubungan keluarga atau identitas child. Pemilik hanya diketahui jika data aplikasi menyatakannya; nilai tidak diketahui tetap valid.

**Consequence:** Jangan otomatis mengisi tipe/nama pemilik rekening berdasarkan orang tua, wali, atau nama child. Aturan validasi ketika holder type kosong atau `unknown` tetap mengikuti kebutuhan program yang dikonfirmasi.

---

## DEC-19: Orang Tua/Wali Dimodelkan sebagai Person dan Child Relationship

**Status:** LOCKED

**Context:** `Person` menyimpan data orang dan status `alive`, `deceased`, atau `unknown`; `ChildRelationship` menghubungkan person ke child dengan tipe `father`, `mother`, atau `guardian`.

**Decision:** Identitas ayah, ibu, dan wali direpresentasikan sebagai `Person` yang terhubung melalui `ChildRelationship`, bukan sebagai kumpulan kolom nama/status orang tua yang diduplikasi di `Child`.

**Consequence:** Status dan relasi keluarga diperbarui pada entitas terkait. Constraint jumlah relasi ayah/ibu/wali dan aturan `is_primary` yang lebih rinci belum ditegakkan oleh schema dan tidak boleh diasumsikan.

---

## DEC-20: Status Yatim adalah Derived Domain Value

**Status:** LOCKED

**Context:** Tidak ada kolom status yatim pada `Child`; informasi terkait berasal dari `Person.status` dan `ChildRelationship.relationship_type`.

**Decision:** Status yatim diturunkan dari status orang tua yang terhubung, bukan disimpan sebagai nilai independen yang dapat berbeda dari data sumber. Aturan yang telah disepakati: ayah deceased dan ibu alive berarti yatim.

**Consequence:** Jangan menambahkan atau mengisi `is_orphan` sebagai sumber kebenaran terpisah tanpa keputusan baru. Perlakuan untuk ayah alive/ibu deceased, kedua orang tua deceased, status unknown, atau data orang tua tidak lengkap masih **TBD**; jangan menyimpulkan kategorinya sendiri.

---

## DEC-21: Hierarki Organisasi Regency, District, Village

**Status:** LOCKED

**Context:** `OrganizationUnitType` memiliki `regency`, `district`, dan `village`; referensi wilayah tersusun melalui Regency, District, dan Village; unit mempunyai parent-child.

**Decision:** Scope administrasi V1 mengikuti hierarki Kabupaten/Regency -> Kecamatan/District -> Kelurahan/Desa/Village.

**Consequence:** Akses dan laporan per wilayah harus konsisten terhadap hubungan administratif tersebut. Penambahan tingkat organisasi lain atau pemetaan wilayah lintas hirarki memerlukan keputusan domain.

---

## DEC-22: Authorization Memerlukan Permission, Scope, dan Workflow State

**Status:** LOCKED

**Context:** Policy memeriksa permission, akses wilayah melalui `User::canAccessChild()`/`canAccessApplication()`, dan status aplikasi yang mengizinkan aksi.

**Decision:** Untuk keputusan aksi atas resource, authorization invariant adalah `permission AND organization scope AND workflow state`. Ketiga syarat berlaku bersama; role saja tidak cukup.

**Consequence:** Perubahan policy/query harus mempertahankan ketiga kontrol. `viewAny` atau tombol yang tersembunyi tidak menggantikan scope query dan pemeriksaan resource server-side. User tanpa organization unit diperlakukan luas oleh beberapa scope saat ini; perilakunya perlu dikonfirmasi sebelum menjadi pola akses operasional.

---

## DEC-23: Application Workflow Hanya Melalui ApplicationWorkflowService

**Status:** LOCKED

**Context:** `ApplicationWorkflowService` mengunci application, memeriksa status dan Gate, memperbarui status/timestamp, serta menulis `WorkflowHistory` dalam satu transaksi.

**Decision:** Submit, request revision, validate, verify, dan reject harus dijalankan melalui `ApplicationWorkflowService`. Filament Action tidak boleh mengubah status atau menulis workflow history secara langsung.

**Consequence:** Setiap UI atau entry point baru memanggil service ini dan mempertahankan pemeriksaan server-side. Resource Filament operasional belum ada; aturan ini harus dipatuhi ketika resource dibangun.

---

## DEC-24: REJECTED dan VERIFIED adalah Status Terminal

**Status:** LOCKED

**Context:** Workflow service hanya mengizinkan `verified` dari `validated` dan tidak mendefinisikan transisi keluar dari `verified` atau `rejected`.

**Decision:** `REJECTED` adalah hasil penolakan final dan `VERIFIED` adalah hasil verifikasi final. Keduanya terminal; proses tidak melanjutkan application yang sama dari kedua status tersebut.

**Consequence:** Koreksi setelah status terminal tidak dilakukan dengan mengedit status atau membuka ulang application secara diam-diam. Mekanisme banding, pembukaan kembali, atau aplikasi pengganti belum diputuskan dan memerlukan keputusan eksplisit.

---

## DEC-25: Kesalahan Data/Dokumen Menggunakan REVISION_REQUIRED

**Status:** LOCKED

**Context:** Revisi dimaksudkan untuk perbaikan; rejection adalah keputusan final. Workflow service mengharuskan note untuk request revision dan rejection.

**Decision:** Kekurangan atau kesalahan data/dokumen yang masih dapat diperbaiki menggunakan `REVISION_REQUIRED`, bukan `REJECTED`.

**Consequence:** Catatan revisi harus menjelaskan perbaikan yang diminta. Rejection tidak boleh dipakai sebagai pengganti feedback koreksi.

---

## DEC-26: Revisi Kabupaten Kembali Melalui Kecamatan

**Status:** LOCKED

**Context:** PRD menetapkan bahwa setelah Kelurahan memperbaiki revisi dari Kabupaten, application harus melewati review Kecamatan lagi.

**Decision:** Revisi yang diminta Kabupaten tidak boleh membuat perbaikan Kelurahan melewati Kecamatan; setelah perbaikan, alur kembali ke review Kecamatan sebelum verifikasi Kabupaten.

**Consequence:** Ini adalah keputusan bisnis yang wajib ditegakkan pada workflow. **Gap implementasi:** `ApplicationWorkflowService::requestRevision()` dan policy saat ini hanya mengizinkan revisi dari `SUBMITTED`; tidak ada routing/persistensi yang membedakan revisi Kabupaten. Jangan menyatakan aturan ini sudah berjalan sebelum service dan data workflow mendukungnya.

---

## DEC-27: current_review_level Memisahkan Target Review dari State

**Status:** LOCKED

**Context:** `status` menjelaskan state lifecycle aplikasi; kebutuhan workflow juga harus membedakan level tujuan review: `SUBMITTED` menuju Kecamatan dan `VALIDATED` menuju Kabupaten/Kesra.

**Decision:** Konsep `current_review_level` dipertahankan untuk menyatakan level organisasi yang menjadi tujuan review berikutnya secara eksplisit. Ia tidak menggantikan `status`.

**Consequence:** Kebutuhan ini belum memiliki kolom atau enum di schema saat ini; status hanya memberi indikasi implisit untuk dua tahap tersebut. Persistensi dan aturan perubahan level, terutama saat revisi Kabupaten harus kembali ke Kecamatan, berstatus **TBD implementasi** dan tidak boleh direka saat membuat UI/query.

---

## DEC-28: Dokumen Disimpan di Private Storage

**Status:** LOCKED

**Context:** `DocumentService` menulis/mengambil file melalui disk `local`, yang dikonfigurasi ke `storage/app/private`, serta memeriksa policy saat akses.

**Decision:** Dokumen aplikasi adalah file sensitif dan disimpan pada private storage, diakses melalui operasi service yang terotorisasi; file tidak dipublikasikan melalui path publik.

**Consequence:** Jangan memakai disk public atau membuat tautan publik ke dokumen sensitif. Persistensi/shared storage, backup, enkripsi at-rest, dan retensi production masih **TBD**.

---

## DEC-29: Penggantian Dokumen Membuat Versi Baru

**Status:** LOCKED

**Context:** `DocumentService::replace()` menentukan nomor versi berikutnya; `DocumentVersion` menyimpan metadata file dan unique `(document_id, version_number)`.

**Decision:** Replace membuat versi baru dan memperbarui pointer/metadata versi terkini pada `Document`; versi lama tidak ditimpa oleh operasi replace normal.

**Consequence:** Riwayat versi dapat ditelusuri dan checksum/actor/waktu upload disimpan per versi. Retensi jangka panjang, penghapusan administratif, serta pembersihan file orphan perlu kebijakan terpisah; jangan menghapus versi lama sebagai efek samping replace.

---

## DEC-30: Dokumen Tidak Dapat Diubah pada State Aplikasi Terkunci

**Status:** LOCKED

**Context:** `DocumentPolicy` hanya mengizinkan upload, replace/update, dan delete ketika status application adalah `draft` atau `revision_required`.

**Decision:** Operasi perubahan dokumen dibatasi pada application berstatus `DRAFT` atau `REVISION_REQUIRED`; dokumen tidak diubah selama tahap review, validasi, verifikasi, atau setelah keputusan terminal.

**Consequence:** Perubahan setelah submit memerlukan transisi revisi yang sah terlebih dahulu. Semua pemeriksaan harus tetap berada di policy/service, bukan hanya disable control di panel.

---

## DEC-31: Workflow History Terpisah dari General Audit Log

**Status:** LOCKED

**Context:** `WorkflowHistory` menyimpan transisi status aplikasi secara eksplisit; `AuditLog` adalah record polymorphic untuk perubahan umum dengan old/new values dan metadata request.

**Decision:** Workflow history dan general audit log adalah dua jejak dengan tujuan berbeda. Transisi aplikasi dicatat di `WorkflowHistory`; perubahan umum yang diaudit menggunakan `AuditLog`.

**Consequence:** Jangan mengganti atau menduplikasi catatan transisi dengan general audit log. Tabel/model `AuditLog` tersedia, tetapi writer/observer otomatis belum teridentifikasi; cakupan event, retensi, dan mekanisme pengisian general audit berstatus **TBD**.

---

## DEC-32: SQLite untuk Development, MySQL untuk Production

**Status:** LOCKED

**Context:** Konfigurasi default database menggunakan SQLite; koneksi MySQL tersedia. Suite test memakai SQLite in-memory.

**Decision:** SQLite digunakan untuk development dan feedback test lokal; MySQL menjadi database production.

**Consequence:** Migrasi, constraint, locking, dan alur dokumen yang melibatkan transaksi harus diuji pada MySQL sebelum release. SQLite test tidak membuktikan kompatibilitas penuh dengan engine production.

---

## DEC-33: Konsistensi Primary Key User Masih Belum Diputuskan

**Status:** TBD

**Context:** Migrasi awal membuat `users.id` integer, sedangkan model `User` memakai `HasUlids` dan beberapa migration menyatakan foreign key user sebagai ULID.

**Decision:** Belum ada keputusan yang mengesahkan salah satu tipe ID sebagai sumber kebenaran. Jangan mengubah skema atau model secara sepihak; pemilik teknis harus menentukan strategi migrasi yang konsisten.

**Consequence:** Relasi user dan migration harus direkonsiliasi serta diuji pada SQLite dan MySQL sebelum production. Dokumentasi ini tidak menganggap kondisi saat ini sudah valid.

---

*Keputusan berstatus LOCKED hanya dapat diubah melalui keputusan eksplisit yang memperbarui record ini dan dokumen requirement terkait.*
