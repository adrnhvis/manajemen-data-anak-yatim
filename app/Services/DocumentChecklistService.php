<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DocumentCategory;

/**
 * Daftar periksa kelengkapan dokumen satu permohonan, untuk reviewer.
 * Tautan unduh memakai route documents.download yang sudah terotorisasi
 * lewat DocumentService::download() / DocumentPolicy.
 */
class DocumentChecklistService
{
    /**
     * @return array{
     *   items: list<array{category: string, required: bool, version_id: ?string, filename: ?string, size: ?string, uploaded_at: ?\Illuminate\Support\Carbon, version_number: ?int}>,
     *   missing_required: int
     * }
     */
    public function forApplication(Application $application): array
    {
        $documents = $application->documents()->with('currentVersion')->get()->keyBy('document_category_id');

        $items = DocumentCategory::query()->orderByDesc('is_required')->orderBy('name')->get()
            ->map(function (DocumentCategory $category) use ($documents) {
                $version = $documents->get($category->id)?->currentVersion;

                return [
                    'category' => $category->name,
                    'required' => (bool) $category->is_required,
                    'version_id' => $version?->id,
                    'filename' => $version?->original_filename,
                    'size' => $version ? $this->formatSize((int) $version->file_size) : null,
                    'uploaded_at' => $version?->uploaded_at,
                    'version_number' => $version?->version_number,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'missing_required' => count(array_filter($items, fn (array $i) => $i['required'] && $i['version_id'] === null)),
        ];
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }
}
