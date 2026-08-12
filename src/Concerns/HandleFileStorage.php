<?php

namespace Developerawam\WireMedia\Concerns;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HandleFileStorage
{
    /**
     * Store an uploaded file and return its metadata array.
     */
    protected function storeFile(TemporaryUploadedFile $file): ?array
    {
        try {
            $path = $file->store($this->directory, $this->disk);

            if (! $path) {
                return null;
            }

            $mime = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());

            return [
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => $this->disk,
                'url' => Storage::disk($this->disk)->url($path),
                'size' => $file->getSize(),
                'size_human' => $this->formatBytes($file->getSize()),
                'mime' => $mime,
                'extension' => $extension,
                'is_image' => str_starts_with($mime, 'image/'),
                'is_pdf' => ($mime === 'application/pdf' || $extension === 'pdf'),
                'is_document' => $this->isDocument($mime, $extension),
                'is_existing' => false,
                'uploaded_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Format bytes into a human-readable string.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min(floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision).' '.$units[$pow];
    }

    /**
     * Determine whether a file is a document (non-image file).
     */
    protected function isDocument(string $mime, string $extension): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        $documentExtensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        ];

        return in_array($mime, $documentMimes, true)
            || in_array($extension, $documentExtensions, true);
    }
}
