<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_required', 'allowed_mime_types', 'max_file_size_kb'])]
class DocumentCategory extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allowed_mime_types' => 'array',
            'max_file_size_kb' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
