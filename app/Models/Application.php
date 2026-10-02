<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['child_id', 'period_year', 'status', 'current_review_level', 'created_by', 'submitted_at', 'validated_at', 'verified_at'])]
class Application extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'current_review_level' => ReviewLevel::class,
            'period_year' => 'integer',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function workflowHistories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('child', function ($q) use ($user) {
            $q->forUser($user);
        });
    }
}
