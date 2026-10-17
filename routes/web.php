<?php

use App\Models\DocumentVersion;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Route;

Route::get('/documents/download/{version}', function (DocumentVersion $version, DocumentService $documentService) {
    $filePath = $documentService->download(auth()->user(), $version);

    return response()->download($filePath, $version->original_filename, [
        'Content-Type' => $version->mime_type,
    ]);
})->middleware(['web', 'auth'])->name('documents.download');
