<?php

namespace App\Console\Commands;

use App\Models\ClusterResult;
use App\Models\ClusterRun;
use App\Services\WorkflowMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RunWorkflowClustering extends Command
{
    protected $signature = 'clustering:run
        {--year= : Tahun periode pengajuan (kosong = semua tahun)}
        {--k= : Paksa jumlah cluster (kosong = dipilih otomatis lewat silhouette)}
        {--min-apps=5 : Minimum pengajuan per wilayah agar ikut dikelompokkan}
        {--python=python3 : Perintah/path Python (Windows: python atau .venv\\Scripts\\python.exe)}';

    protected $description = 'Kelompokkan kelurahan/desa berdasarkan kinerja administrasi pengajuan (K-Means)';

    private const FEATURES = ['avg_returns', 'reject_rate', 'avg_resubmit_days', 'avg_first_submit_days'];

    public function handle(WorkflowMetricsService $metrics): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $minApps = max(1, (int) $this->option('min-apps'));

        $data = $metrics->villageMetrics($year);
        $rows = collect($data['rows']);
        $eligible = $rows->where('applications_count', '>=', $minApps)->values();
        $excluded = $rows->where('applications_count', '<', $minApps)->values();
        $withoutApps = $data['villages_total'] - $rows->count();

        $this->info(sprintf(
            'Wilayah: %d total, %d memenuhi syarat (>= %d pengajuan), %d data belum cukup, %d belum mengajukan.',
            $data['villages_total'], $eligible->count(), $minApps, $excluded->count(), $withoutApps
        ));

        $dir = storage_path('app/private/clustering');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('YmdHis');
        $input = "{$dir}/input-{$stamp}.json";
        $output = "{$dir}/output-{$stamp}.json";

        File::put($input, json_encode([
            'features' => self::FEATURES,
            'rows' => $eligible->map(fn ($r) => ['village_id' => $r['village_id']] + collect(self::FEATURES)->mapWithKeys(fn ($f) => [$f => $r[$f]])->all())->all(),
        ], JSON_THROW_ON_ERROR));

        $command = [$this->option('python'), resource_path('python/cluster_workflow.py'), '--input', $input, '--output', $output];
        if ($this->option('k') !== null) {
            array_push($command, '--k', (string) (int) $this->option('k'));
        }

        $process = Process::timeout(120)->run($command);

        try {
            if ($process->failed()) {
                $this->error('Skrip Python gagal: '.trim($process->errorOutput() ?: $process->output()));

                return self::FAILURE;
            }

            $result = json_decode(File::get($output), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            File::delete([$input, $output]);
        }

        $assignment = collect($result['assignments'])->keyBy('village_id');

        $run = DB::transaction(function () use ($result, $year, $minApps, $excluded, $withoutApps, $eligible, $assignment) {
            $run = ClusterRun::create([
                'period_year' => $year,
                'k' => $result['k'],
                'silhouette' => $result['silhouette'],
                'quality' => $result['quality'],
                'inertia' => $result['inertia'],
                'n_clustered' => $result['n_clustered'],
                'n_excluded' => $excluded->count(),
                'n_without_applications' => $withoutApps,
                'min_applications' => $minApps,
                'feature_names' => $result['feature_names'],
                'centroids' => $result['centroids'],
                'k_scores' => $result['k_scores'],
            ]);

            $now = now();
            $payload = $eligible->map(function ($r) use ($run, $assignment, $now) {
                $a = $assignment[$r['village_id']];

                return $this->resultRow($run->id, $r, true, $a['cluster_index'], $a['cluster_label'], $now);
            })->concat($excluded->map(
                fn ($r) => $this->resultRow($run->id, $r, false, null, 'Data belum cukup', $now)
            ))->all();

            foreach (array_chunk($payload, 200) as $chunk) {
                ClusterResult::query()->insert($chunk);
            }

            return $run;
        });

        $this->info(sprintf('Selesai. k=%d, silhouette=%.3f (%s). Run ID: %s', $run->k, $run->silhouette, $run->quality, $run->id));
        if ($run->quality === 'tidak_bermakna') {
            $this->warn('Silhouette < 0,25: kelompok tidak terpisah jelas. Jangan dijadikan dasar keputusan.');
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $r */
    private function resultRow(string $runId, array $r, bool $clustered, ?int $index, ?string $label, $now): array
    {
        return [
            'id' => (string) Str::ulid(),
            'cluster_run_id' => $runId,
            'village_id' => $r['village_id'],
            'village_name' => $r['village_name'],
            'district_name' => $r['district_name'],
            'is_clustered' => $clustered,
            'cluster_index' => $index,
            'cluster_label' => $label,
            'applications_count' => $r['applications_count'],
            'avg_returns' => $r['avg_returns'],
            'reject_rate' => $r['reject_rate'],
            'avg_resubmit_days' => $r['avg_resubmit_days'],
            'avg_first_submit_days' => $r['avg_first_submit_days'],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
