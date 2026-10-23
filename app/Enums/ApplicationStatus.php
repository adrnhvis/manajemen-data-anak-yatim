<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVISION_REQUIRED = 'revision_required';
    case VALIDATED = 'validated';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Menunggu Pengecekan Kecamatan',
            self::REVISION_REQUIRED => 'Perlu Revisi',
            self::VALIDATED => 'Menunggu Verifikasi Kabupaten',
            self::VERIFIED => 'Terverifikasi Final',
            self::REJECTED => 'Ditolak',
        };
    }

    /** Warna badge Filament. */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'info',
            self::REVISION_REQUIRED => 'warning',
            self::VALIDATED => 'primary',
            self::VERIFIED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /** Warna grafik (hex). */
    public function chartColor(): string
    {
        return match ($this) {
            self::DRAFT => '#9ca3af',
            self::SUBMITTED => '#3b82f6',
            self::REVISION_REQUIRED => '#f59e0b',
            self::VALIDATED => '#6366f1',
            self::VERIFIED => '#10b981',
            self::REJECTED => '#f43f5e',
        };
    }

    /** Pengelompokan ringkas untuk grafik per wilayah. */
    public function bucket(): string
    {
        return match ($this) {
            self::DRAFT => 'draft',
            self::SUBMITTED, self::VALIDATED => 'process',
            self::REVISION_REQUIRED => 'revision',
            self::VERIFIED => 'verified',
            self::REJECTED => 'rejected',
        };
    }
}
