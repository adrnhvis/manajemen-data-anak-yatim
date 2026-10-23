<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationUnitType;
use App\Enums\ReviewLevel;
use App\Models\Application;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data dashboard per role. Semua query lewat scope forUser(), jadi cakupan wilayah
 * (kelurahan / kecamatan / kabupaten) selalu mengikuti aturan yang sama dengan Data Anak.
 */
class DashboardService
{
    public const LEVEL_VILLAGE = 'village';

    public const LEVEL_DISTRICT = 'district';

    public const LEVEL_REGENCY = 'regency';

    public const LEVEL_ADMIN = 'admin';

    public const LEVEL_NONE = 'none';

    /** Kelompok status untuk grafik per wilayah: label => warna. */
    public const BUCKETS = [
        'draft' => ['Draft', '#9ca3af'],
        'process' => ['Sedang diproses', '#3b82f6'],
        'revision' => ['Perlu revisi', '#f59e0b'],
        'verified' => ['Terverifikasi', '#10b981'],
        'rejected' => ['Ditolak', '#f43f5e'],
    ];

    public function level(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return self::LEVEL_ADMIN;
        }

        return match ($user->organizationUnit?->type) {
            OrganizationUnitType::VILLAGE => self::LEVEL_VILLAGE,
            OrganizationUnitType::DISTRICT => self::LEVEL_DISTRICT,
            OrganizationUnitType::REGENCY => self::LEVEL_REGENCY,
            default => self::LEVEL_NONE,
        };
    }

    public function scopeDescription(User $user): string
    {
        $unit = $user->organizationUnit;

        return match ($this->level($user)) {
            self::LEVEL_VILLAGE => 'Kelurahan/Desa: '.$unit->name,
            self::LEVEL_DISTRICT => 'Kecamatan: '.$unit->name.' (seluruh kelurahan/desa di kecamatan ini)',
            self::LEVEL_REGENCY => 'Kabupaten: '.$unit->name.' (seluruh kelurahan/desa di kabupaten ini)',
            self::LEVEL_ADMIN => 'Seluruh wilayah',
            default => 'Wilayah belum ditetapkan',
        };
    }

    /** Pengajuan yang menunggu tindakan user ini. */
    public function taskQuery(User $user): Builder
    {
        return $this->applyTaskConstraints(Application::query()->forUser($user), $user);
    }

    /** Terapkan aturan "antrian saya" ke query permohonan yang sudah dibatasi wilayah. */
    public function applyTaskConstraints(Builder $query, User $user): Builder
    {
        return match ($this->level($user)) {
            self::LEVEL_VILLAGE => $query->whereIn('status', [
                ApplicationStatus::DRAFT->value,
                ApplicationStatus::REVISION_REQUIRED->value,
            ]),
            self::LEVEL_DISTRICT => $query
                ->where('status', ApplicationStatus::SUBMITTED->value)
                ->where('current_review_level', ReviewLevel::DISTRICT->value),
            self::LEVEL_REGENCY => $query
                ->where('status', ApplicationStatus::VALIDATED->value)
                ->where('current_review_level', ReviewLevel::REGENCY->value),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Permohonan yang pernah diproses unit user ini (lulus, dikembalikan, diverifikasi, atau ditolak).
     * Dihitung per unit organisasi, bukan per orang, supaya rekan sekantor melihat riwayat yang sama.
     */
    public function applyHandledConstraints(Builder $query, User $user): Builder
    {
        if (! $user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('workflowHistories', fn ($history) => $history
            ->whereIn('action', ['validate', 'verify', 'request_revision', 'reject'])
            ->whereHas('actor', fn ($actor) => $actor->where('organization_unit_id', $user->organization_unit_id)));
    }

    public function taskHeading(User $user): string
    {
        return match ($this->level($user)) {
            self::LEVEL_VILLAGE => 'Perlu ditindaklanjuti (draft & perlu revisi)',
            self::LEVEL_DISTRICT => 'Menunggu pengecekan Anda',
            self::LEVEL_REGENCY => 'Menunggu verifikasi final Anda',
            default => 'Tugas',
        };
    }

    /**
     * Kartu ringkasan, berbeda per role.
     *
     * @return list<array{label: string, value: int, description: string, color: string, url?: string}>
     */
    public function statCards(User $user): array
    {
        $counts = $this->statusCounts($user);
        $count = fn (array $statuses): int => $counts
            ->only(array_map(fn (ApplicationStatus $s) => $s->value, $statuses))->sum();

        $revision = $count([ApplicationStatus::REVISION_REQUIRED]);
        $verified = $count([ApplicationStatus::VERIFIED]);

        return match ($this->level($user)) {
            self::LEVEL_VILLAGE => [
                ['label' => 'Perlu direvisi', 'value' => $revision, 'description' => 'Dikembalikan, menunggu perbaikan Anda', 'color' => $revision > 0 ? 'warning' : 'gray'],
                ['label' => 'Draft belum diajukan', 'value' => $count([ApplicationStatus::DRAFT]), 'description' => 'Belum dikirim ke kecamatan', 'color' => 'gray'],
                ['label' => 'Sedang diproses', 'value' => $count([ApplicationStatus::SUBMITTED, ApplicationStatus::VALIDATED]), 'description' => 'Di kecamatan atau kabupaten', 'color' => 'info'],
                ['label' => 'Terverifikasi final', 'value' => $verified, 'description' => 'Selesai', 'color' => 'success'],
            ],
            self::LEVEL_DISTRICT => [
                ['label' => 'Menunggu pengecekan Anda', 'value' => $this->taskQuery($user)->count(), 'description' => 'Perlu dicek sebelum ke kabupaten', 'color' => 'primary'],
                ['label' => 'Sedang direvisi', 'value' => $revision, 'description' => 'Dikembalikan ke kelurahan/desa', 'color' => 'warning'],
                ['label' => 'Menunggu kabupaten', 'value' => $count([ApplicationStatus::VALIDATED]), 'description' => 'Sudah lulus pengecekan kecamatan', 'color' => 'info'],
                ['label' => 'Terverifikasi final', 'value' => $verified, 'description' => 'Selesai', 'color' => 'success'],
            ],
            self::LEVEL_REGENCY => [
                ['label' => 'Menunggu verifikasi Anda', 'value' => $this->taskQuery($user)->count(), 'description' => 'Verifikasi final kabupaten', 'color' => 'primary'],
                ['label' => 'Menunggu kecamatan', 'value' => $count([ApplicationStatus::SUBMITTED]), 'description' => 'Belum dicek kecamatan', 'color' => 'info'],
                ['label' => 'Sedang direvisi', 'value' => $revision, 'description' => 'Dikembalikan ke kelurahan/desa', 'color' => 'warning'],
                ['label' => 'Terverifikasi final', 'value' => $verified, 'description' => 'Selesai', 'color' => 'success'],
            ],
            self::LEVEL_ADMIN => [
                ['label' => 'Total anak terdata', 'value' => Child::query()->forUser($user)->count(), 'description' => 'Seluruh wilayah', 'color' => 'primary'],
                ['label' => 'Total permohonan', 'value' => $counts->sum(), 'description' => 'Semua status', 'color' => 'gray'],
                ['label' => 'Perlu revisi', 'value' => $revision, 'description' => 'Menunggu kelurahan/desa', 'color' => 'warning'],
                ['label' => 'Terverifikasi final', 'value' => $verified, 'description' => 'Selesai', 'color' => 'success'],
            ],
            default => [],
        };
    }

    /**
     * Jumlah pengajuan per status dalam cakupan user.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function statusCounts(User $user)
    {
        return Application::query()->forUser($user)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Pengajuan per wilayah bawahan: per kelurahan/desa (untuk kecamatan)
     * atau per kecamatan (untuk kabupaten dan super admin).
     *
     * @return array<string, array<string, int>> nama wilayah => [bucket => jumlah]
     */
    public function regionBreakdown(User $user): array
    {
        $byVillage = $this->level($user) === self::LEVEL_DISTRICT;
        $regionColumn = $byVillage ? 'v.name' : 'd.name';

        $rows = Application::query()->forUser($user)
            ->join('children as c', 'c.id', '=', 'applications.child_id')
            ->join('addresses as a', 'a.id', '=', 'c.address_id')
            ->join('villages as v', 'v.id', '=', 'a.village_id')
            ->join('districts as d', 'd.id', '=', 'v.district_id')
            ->whereNull('c.deleted_at')
            ->toBase()
            ->selectRaw("{$regionColumn} as region, applications.status as status, count(*) as total")
            ->groupByRaw("{$regionColumn}, applications.status")
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $bucket = ApplicationStatus::from($row->status)->bucket();
            $result[$row->region][$bucket] = ($result[$row->region][$bucket] ?? 0) + (int) $row->total;
        }
        ksort($result);

        return $result;
    }
}
