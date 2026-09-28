<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PersonStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'gender', 'occupation', 'status'])]
class Person extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'persons';

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => PersonStatus::class,
        ];
    }

    public function childRelationships(): HasMany
    {
        return $this->hasMany(ChildRelationship::class);
    }
}
