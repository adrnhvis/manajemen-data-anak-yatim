<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentService
{
    public function upload(User $user, Application $application, DocumentCategory $category, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($user, $application, $category, $file) {
            $application = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            $category = DocumentCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('upload', $application);
            $this->validateFile($category, $file);

            return $this->storeVersion($user, $application, $category, $file);
        });
    }

    public function replace(User $user, Document $document, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($user, $document, $file) {
            Application::query()->whereKey($document->application_id)->lockForUpdate()->firstOrFail();
            $document = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $document->loadMissing('category', 'application');

            Gate::forUser($user)->authorize('replace', $document);
            $this->validateFile($document->category, $file);

            $number = ((int) $document->versions()->max('version_number')) + 1;
            return $this->writeVersion($user, $document, $file, $number);
        });
    }

    public function delete(User $user, Document $document): void
    {
        DB::transaction(function () use ($user, $document) {
            Application::query()->whereKey($document->application_id)->lockForUpdate()->firstOrFail();
            $document = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $document->loadMissing('application');

            Gate::forUser($user)->authorize('delete', $document);
            $document->delete();
        });
    }

    public function download(User $user, DocumentVersion $version): string
    {
        $version->loadMissing('document.application');
        Gate::forUser($user)->authorize('view', $version->document);
        if (!Storage::disk('local')->exists($version->storage_path)) {
            throw new RuntimeException('Document file is unavailable.');
        }
        return Storage::disk('local')->path($version->storage_path);
    }

    private function storeVersion(User $user, Application $application, DocumentCategory $category, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($user, $application, $category, $file) {
            $document = new Document;
            $document->application_id = $application->getKey();
            $document->document_category_id = $category->getKey();
            $document->original_filename = $file->getClientOriginalName();
            $document->uploaded_by = $user->getKey();
            $document->uploaded_at = \Illuminate\Support\Carbon::parse(date('Y-m-d H:i:s'));
            $document->storage_path = '';
            $document->mime_type = $file->getMimeType();
            $document->file_size = $file->getSize();
            $document->checksum = hash_file('sha256', $file->getRealPath());
            $document->save();

            return $this->writeVersion($user, $document, $file, 1);
        });
    }

    private function writeVersion(User $user, Document $document, UploadedFile $file, int $number): Document
    {
        $path = 'applications/'.$document->application_id.'/documents/'.$document->getKey().'/'.$number.'-'.bin2hex(random_bytes(12));
        $disk = Storage::disk('local');
        if (!$disk->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('Document storage failed.');
        }

        try {
            $version = DocumentVersion::create([
                'document_id' => $document->getKey(),
                'version_number' => $number,
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $user->getKey(),
                'uploaded_at' => now(),
            ]);
            $document->update([
                'original_filename' => $version->original_filename,
                'storage_path' => $version->storage_path,
                'mime_type' => $version->mime_type,
                'file_size' => $version->file_size,
                'checksum' => $version->checksum,
                'uploaded_by' => $user->getKey(),
                'uploaded_at' => $version->uploaded_at,
            ]);
            return $document->refresh();
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }

    private function validateFile(DocumentCategory $category, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $allowed = $category->allowed_mime_types ?: ['application/pdf', 'image/jpeg'];
        $extensions = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        if (!isset($extensions[$extension]) || $extensions[$extension] !== $mime || !in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'The document type is not allowed.']);
        }

        $signature = file_get_contents($file->getRealPath(), false, null, 0, 12);
        $validSignature = match ($mime) {
            'application/pdf' => str_starts_with($signature, '%PDF-'),
            'image/jpeg' => substr($signature, 0, 2) === "\xFF\xD8",
            'image/png' => str_starts_with($signature, "\x89PNG\r\n\x1a\n"),
            default => false,
        };

        if (!$validSignature) {
            throw ValidationException::withMessages(['file' => 'The file content does not match its declared type.']);
        }

        if ($file->getSize() > ((int) $category->max_file_size_kb * 1024)) {
            throw ValidationException::withMessages(['file' => 'The document exceeds the category size limit.']);
        }
    }
}
