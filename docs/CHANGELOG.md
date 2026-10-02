# Changelog

Catatan ini mencatat perubahan dan baseline yang dapat diverifikasi dari dokumentasi serta repository. Tanggal menunjukkan tanggal snapshot dokumentasi bila tanggal implementasi historis tidak tersedia; jangan menafsirkan snapshot sebagai tanggal setiap fitur dibuat.

## 2026-10-02

### Added

- Baseline V1 dicatat sebagai Laravel 13, Filament 5, Livewire/Blade, Eloquent ORM, PHP Backed Enum, dan Spatie Laravel Permission. Composer mengunci Laravel `^13.17`, Filament `~5.0`, dan `spatie/laravel-permission ^8.3`; satu Filament panel provider mengaktifkan login di `/admin`.
- `ApplicationWorkflowService` menyediakan operasi submit, request revision, validate, verify, dan reject. Perubahan status dan `WorkflowHistory` ditulis dalam transaksi database.
- `DocumentService` menangani operasi upload, replace, delete, dan download. File menggunakan disk `local` yang diarahkan ke `storage/app/private`; `DocumentVersion` menyimpan versi file dan metadata.
- Model, migration, dan seeder mendukung hierarki wilayah/organisasi, data child/keluarga, application, dokumen, rekening, role, dan permission.

### Changed

- Baseline requirement V1 menetapkan application terpisah dari child dan menggunakan `period_year` sebagai tahun anggaran/program bantuan.
- Dokumentasi V1 membedakan `Application.status` sebagai lifecycle state dari `current_review_level` sebagai tujuan review, serta membedakan `WorkflowHistory` dari general `AuditLog`.
- Dokumentasi arsitektur menetapkan integrasi operasional panel melalui `Policy -> Service -> Model/Database`; workflow menggunakan `ApplicationWorkflowService` dan operasi dokumen menggunakan `DocumentService`.

### Fixed

- Tidak ada perbaikan source code yang dapat dipastikan dari snapshot ini. Gap implementasi yang tercatat di bagian Notes belum dianggap selesai.

### Decision

- Satu child hanya boleh memiliki satu application per `period_year`; migration menetapkan unique `(child_id, period_year)`. NIK child juga memiliki unique constraint.
- Koreksi data/dokumen menggunakan `REVISION_REQUIRED`, bukan `REJECTED`. `REJECTED` adalah status terminal; `VERIFIED` juga diperlakukan sebagai hasil terminal dalam workflow V1.
- Keputusan bisnis menetapkan bahwa revisi yang diminta Kabupaten harus kembali melewati Kecamatan setelah perbaikan Kelurahan.
- `current_review_level` dipertahankan sebagai konsep desain untuk membedakan `SUBMITTED` yang menunggu Kecamatan dan `VALIDATED` yang menunggu Kabupaten/Kesra.

### Security

- Otorisasi aksi resource mengikuti invariant `permission AND organization scope AND workflow state`; policy menggabungkan permission, pemeriksaan akses wilayah, dan status workflow sesuai aksi.
- `super_admin` tidak otomatis memiliki permission verifikasi bisnis; seeder memisahkan kewenangan administrasi sistem dari verifikasi aplikasi.
- Dokumen disimpan pada private local storage dan akses download melalui `DocumentService` dengan pemeriksaan Policy.

### Notes

- Keputusan revisi Kabupaten kembali melalui Kecamatan **belum ditegakkan sepenuhnya**: `ApplicationWorkflowService::requestRevision()` dan `ApplicationPolicy` saat ini hanya mengizinkan request revision dari `SUBMITTED`.
- `current_review_level` belum memiliki kolom atau enum pada schema/model. Persistensi dan routing eksplisit masih Planned/TBD.
- Provider Filament dan panel login tersedia, tetapi resource/page operasional belum ada; jangan mengartikan keberadaan panel sebagai UI CRUD/workflow yang selesai.
- Model dan tabel `AuditLog` tersedia, tetapi writer otomatis general audit belum terverifikasi. `WorkflowHistory` tetap mencatat transisi workflow.
- Mismatch tipe ID masih perlu diselesaikan sebelum production: migration awal menggunakan integer untuk `users.id`, sementara model `User` memakai `HasUlids` dan beberapa foreign key user dideklarasikan sebagai ULID.
- Tidak ada tanggal implementasi historis yang cukup untuk memberi tanggal berbeda bagi baseline code; tanggal di atas mengikuti tanggal PRD dan snapshot dokumentasi project.
