<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Village;

/**
 * Menghitung metrik kinerja administrasi pengajuan per kelurahan/desa
 * dari workflow_histories. Dihitung di PHP (bukan satu SQL besar) agar
 * tetap jalan di SQLite/MySQL/PostgreSQL tanpa fungsi window.
 *
 * Hanya metrik yang bisa dipertanggungjawabkan ke tingkat kelurahan/desa:
 * lama proses validasi/verifikasi sengaja TIDAK dihitung di sini karena itu
 * kinerja kecamatan/kabupaten, bukan kelurahan/desa.
 */
class WorkflowMetricsService
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * @return array{rows: list<array<string, mixed>>, villages_total: int}
     */
    public function villageMetrics(?int $year = null): array
    {
        $villages = Village::query()->with('district:id,name')->get(['id', 'name', 'district_id'])->keyBy('id');

        /** @var array<string, array<string, mixed>> $acc */
        $acc = [];

        Application::query()
            ->select(['applications.id', 'applications.created_at', 'addresses.village_id'])
            ->join('children', 'children.id', '=', 'applications.child_id')
            ->join('addresses', 'addresses.id', '=', 'children.address_id')
            ->whereNull('children.deleted_at')
            ->when($year, fn ($q) => $q->where('applications.period_year', $year))
            ->with(['workflowHistories' => fn ($q) => $q->orderBy('acted_at')->orderBy('id')])
            ->chunkById(500, function ($applications) use (&$acc) {
                foreach ($applications as $application) {
                    $metrics = $this->applicationMetrics($application);
                    if ($metrics === null) {
                        continue; // masih draft, belum pernah diajukan
                    }
                    $v = $application->village_id;
                    $acc[$v] ??= ['n' => 0, 'returns' => 0, 'rejected' => 0, 'resubmit' => [], 'first' => []];
                    $acc[$v]['n']++;
                    $acc[$v]['returns'] += $metrics['returns'];
                    $acc[$v]['rejected'] += $metrics['rejected'] ? 1 : 0;
                    array_push($acc[$v]['resubmit'], ...$metrics['resubmit_days']);
                    $acc[$v]['first'][] = $metrics['first_submit_days'];
                }
            }, 'applications.id', 'id');

        $rows = [];
        foreach ($acc as $villageId => $a) {
            $village = $villages->get($villageId);
            if ($village === null) {
                continue;
            }
            $rows[] = [
                'village_id' => $villageId,
                'village_name' => $village->name,
                'district_name' => $village->district?->name ?? '-',
                'applications_count' => $a['n'],
                'avg_returns' => round($a['returns'] / $a['n'], 4),
                'reject_rate' => round($a['rejected'] / $a['n'], 4),
                'avg_resubmit_days' => $a['resubmit'] === [] ? null : round(array_sum($a['resubmit']) / count($a['resubmit']), 3),
                'avg_first_submit_days' => round(array_sum($a['first']) / count($a['first']), 3),
            ];
        }

        return ['rows' => $rows, 'villages_total' => $villages->count()];
    }

    /**
     * @return array{returns: int, rejected: bool, resubmit_days: list<float>, first_submit_days: float}|null
     */
    public function applicationMetrics(Application $application): ?array
    {
        $events = $application->workflowHistories;
        $firstSubmit = $events->firstWhere('action', 'submit');
        if ($firstSubmit === null) {
            return null;
        }

        $returns = 0;
        $rejected = false;
        $resubmit = [];
        $pendingRevisionAt = null;

        foreach ($events as $event) {
            if ($event->action === 'request_revision') {
                $returns++;
                $pendingRevisionAt = $event->acted_at;
            } elseif ($event->action === 'submit' && $pendingRevisionAt !== null) {
                $resubmit[] = $this->days($pendingRevisionAt->getTimestamp(), $event->acted_at->getTimestamp());
                $pendingRevisionAt = null;
            } elseif ($event->action === 'reject') {
                $rejected = true;
            }
        }

        return [
            'returns' => $returns,
            'rejected' => $rejected,
            'resubmit_days' => $resubmit,
            'first_submit_days' => $this->days($application->created_at->getTimestamp(), $firstSubmit->acted_at->getTimestamp()),
        ];
    }

    private function days(int $from, int $to): float
    {
        return max(0, $to - $from) / self::SECONDS_PER_DAY;
    }
}
