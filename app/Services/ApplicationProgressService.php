<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Child;

/**
 * Menyusun tampilan progres (stepper) dan riwayat (timeline) satu permohonan
 * dari status dan workflow_histories. Tidak ada aturan bisnis baru di sini:
 * hanya membaca apa yang sudah dicatat ApplicationWorkflowService.
 */
class ApplicationProgressService
{
    private const ACTION_LABELS = [
        'submit' => 'Diajukan ke kecamatan',
        'request_revision' => 'Dikembalikan untuk revisi',
        'validate' => 'Lolos pengecekan kecamatan',
        'verify' => 'Diverifikasi final kabupaten',
        'reject' => 'Ditolak',
    ];

    /** Permohonan dengan periode terbaru milik anak, atau null jika belum ada. */
    public function forChild(Child $child): ?array
    {
        $application = $child->applications()->orderByDesc('period_year')->orderByDesc('id')->first();

        return $application ? $this->build($application) : null;
    }

    /**
     * @return array{
     *   period_year: int, status: ApplicationStatus,
     *   steps: list<array{key: string, label: string, state: string, at: ?\Illuminate\Support\Carbon}>,
     *   banner: ?array{tone: string, title: string, note: ?string, by: ?string, at: ?\Illuminate\Support\Carbon},
     *   history: list<array{label: string, by: ?string, note: ?string, at: ?\Illuminate\Support\Carbon}>
     * }
     */
    public function build(Application $application): array
    {
        $histories = $application->workflowHistories()
            ->with('actor:id,name')
            ->orderBy('acted_at')
            ->orderBy('id')
            ->get();

        $lastOf = fn (string $action) => $histories->where('action', $action)->last();

        $states = $this->stepStates($application->status, $lastOf('reject')?->from_status);
        $steps = [
            ['key' => 'village', 'label' => 'Disusun Kelurahan/Desa', 'at' => $lastOf('submit')?->acted_at],
            ['key' => 'district', 'label' => 'Dicek Kecamatan', 'at' => $lastOf('validate')?->acted_at],
            ['key' => 'regency', 'label' => 'Diverifikasi Kabupaten', 'at' => $lastOf('verify')?->acted_at],
        ];
        foreach ($steps as $i => &$step) {
            $step['state'] = $states[$i];
            // Waktu hanya bermakna untuk tahap yang benar-benar sudah dilewati.
            $step['at'] = $step['state'] === 'done' ? $step['at'] : null;
        }
        unset($step);

        return [
            'period_year' => $application->period_year,
            'status' => $application->status,
            'steps' => $steps,
            'banner' => $this->banner($application->status, $lastOf('request_revision'), $lastOf('reject')),
            'history' => $histories->reverse()->map(fn ($h) => [
                'label' => self::ACTION_LABELS[$h->action] ?? $h->action,
                'by' => $h->actor?->name,
                'note' => $h->note,
                'at' => $h->acted_at,
            ])->values()->all(),
        ];
    }

    /**
     * Status tiap tahap: done | current | pending | revision | rejected.
     *
     * @return list<string>
     */
    private function stepStates(ApplicationStatus $status, ?ApplicationStatus $rejectedFrom): array
    {
        return match ($status) {
            ApplicationStatus::DRAFT => ['current', 'pending', 'pending'],
            ApplicationStatus::REVISION_REQUIRED => ['revision', 'pending', 'pending'],
            ApplicationStatus::SUBMITTED => ['done', 'current', 'pending'],
            ApplicationStatus::VALIDATED => ['done', 'done', 'current'],
            ApplicationStatus::VERIFIED => ['done', 'done', 'done'],
            ApplicationStatus::REJECTED => $rejectedFrom === ApplicationStatus::VALIDATED
                ? ['done', 'done', 'rejected']
                : ['done', 'rejected', 'pending'],
        };
    }

    private function banner(ApplicationStatus $status, $revision, $reject): ?array
    {
        [$event, $tone, $verb] = match ($status) {
            ApplicationStatus::REVISION_REQUIRED => [$revision, 'warning', 'Dikembalikan untuk revisi'],
            ApplicationStatus::REJECTED => [$reject, 'danger', 'Ditolak'],
            default => [null, null, null],
        };

        if ($event === null) {
            return null;
        }

        $level = match ($event->from_status) {
            ApplicationStatus::SUBMITTED => 'Kecamatan',
            ApplicationStatus::VALIDATED => 'Kabupaten',
            default => null,
        };

        return [
            'tone' => $tone,
            'title' => $level ? "{$verb} oleh {$level}" : $verb,
            'note' => $event->note,
            'by' => $event->actor?->name,
            'at' => $event->acted_at,
        ];
    }
}
