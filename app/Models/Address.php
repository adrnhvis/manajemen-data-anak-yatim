<?php

namespace App\Models;

use App\Enums\LivingWith;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['village_id', 'rt', 'rw', 'address_detail', 'living_with'])]
class Address extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'living_with' => LivingWith::class,
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }
}
