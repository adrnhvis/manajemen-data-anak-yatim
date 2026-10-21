<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'period_year', 'k', 'silhouette', 'quality', 'inertia', 'n_clustered', 'n_excluded',
    'n_without_applications', 'min_applications', 'feature_names', 'centroids', 'k_scores',
])]
class ClusterRun extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'silhouette' => 'float',
            'inertia' => 'float',
            'feature_names' => 'array',
            'centroids' => 'array',
            'k_scores' => 'array',
        ];
    }

    public static function latestRun(): ?self
    {
        return static::query()->latest('created_at')->latest('id')->first();
    }

    public function results(): HasMany
    {
        return $this->hasMany(ClusterResult::class);
    }

    public function qualityLabel(): string
    {
        return match ($this->quality) {
            'baik' => 'Struktur kelompok jelas',
            'lemah' => 'Struktur kelompok lemah, gunakan hati-hati',
            default => 'Tidak bermakna: kelompok tidak terpisah jelas',
        };
    }

    public function qualityColor(): string
    {
        return match ($this->quality) {
            'baik' => 'success',
            'lemah' => 'warning',
            default => 'danger',
        };
    }
}
