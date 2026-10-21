<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cluster_run_id', 'village_id', 'village_name', 'district_name', 'is_clustered',
    'cluster_index', 'cluster_label', 'applications_count', 'avg_returns', 'reject_rate',
    'avg_resubmit_days', 'avg_first_submit_days',
])]
class ClusterResult extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_clustered' => 'boolean',
            'cluster_index' => 'integer',
            'avg_returns' => 'float',
            'reject_rate' => 'float',
            'avg_resubmit_days' => 'float',
            'avg_first_submit_days' => 'float',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ClusterRun::class, 'cluster_run_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** Warna Filament berdasarkan posisi cluster (0 = paling lancar). */
    public static function badgeColor(?int $index, int $k): string
    {
        if ($index === null) {
            return 'gray';
        }
        $ratio = $k > 1 ? $index / ($k - 1) : 0;

        return $ratio <= 0.34 ? 'success' : ($ratio < 0.67 ? 'warning' : 'danger');
    }

    /** Warna grafik (hijau -> merah) berdasarkan posisi cluster. */
    public static function chartColor(int $index, int $k): string
    {
        $ratio = $k > 1 ? $index / ($k - 1) : 0;

        return sprintf('hsl(%d, 65%%, 50%%)', (int) round(140 - 140 * $ratio));
    }
}
