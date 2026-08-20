<?php

namespace Developerawam\WireMedia\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandleImageProcessing
{
    /**
     * Receive a base64-encoded image from the editor (crop/transform result),
     * store it to disk, and replace the file at $index in $uploadedFiles.
     *
     * Called via wire:click from the editor "Save to Server" button.
     *
     * @param  int  $index  Index in $this->uploadedFiles to replace
     * @param  string  $dataUrl  Image data URL (data:image/png;base64,...)
     * @param  string  $mode  'replace' | 'add'
     */
    public function saveProcessedImage(int $index, string $dataUrl, string $mode = 'replace'): void
    {
        if (! preg_match('#^data:image/(jpeg|png|webp|gif);base64,#', $dataUrl)) {
            $this->errors[] = trans('wire-media::messages.invalid_image_data');

            return;
        }

        try {
            $stored = $this->storeDataUrl($dataUrl, $index);

            if (! $stored) {
                $this->errors[] = trans('wire-media::messages.save_processed_failed');

                return;
            }

            if ($mode === 'replace' && isset($this->uploadedFiles[$index])) {
                // Delete old file (+ thumbnail) from storage
                $old = $this->uploadedFiles[$index];
                $disk = $old['disk'] ?? $this->disk;

                $this->deleteStoredFile($old, $disk);

                $this->uploadedFiles[$index] = $stored;
            } else {
                // 'add' mode — append as new file
                $this->uploadedFiles[] = $stored;
            }

            $this->editorOpen = false;
            $this->editorIndex = null;

            $this->dispatch('image-processed', file: $stored, mode: $mode);

        } catch (\Throwable $e) {
            report($e);
            $this->errors[] = trans('wire-media::messages.save_processed_error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Decode a data URL, validate it against the component configuration,
     * and store the raw image bytes to the configured disk.
     *
     * @throws \InvalidArgumentException When the data URL is malformed
     * @throws \RuntimeException When validation fails (size / MIME)
     */
    protected function storeDataUrl(string $dataUrl, int $originalIndex): ?array
    {
        if (! preg_match('#^data:(image/(jpeg|png|webp|gif));base64,(.*)$#s', $dataUrl, $m)) {
            throw new \InvalidArgumentException(trans('wire-media::messages.invalid_editor_format'));
        }

        $mime = $m[1];
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        $bytes = base64_decode($m[3], true);

        if ($bytes === false) {
            throw new \InvalidArgumentException(trans('wire-media::messages.invalid_editor_data'));
        }

        $maxBytes = (int) ($this->maxSizeMb * 1024 * 1024);

        if (strlen($bytes) > $maxBytes) {
            throw new \RuntimeException(trans('wire-media::messages.editor_max_size', [
                'max' => $this->maxSizeMb,
                'actual' => round(strlen($bytes) / 1024 / 1024, 2),
            ]));
        }

        if (! empty($this->acceptedMimeTypes) && ! $this->mimeIsAllowed($mime, $this->acceptedMimeTypes)) {
            throw new \RuntimeException(trans('wire-media::messages.editor_mime_not_allowed', ['mime' => $mime]));
        }

        // Library mode: optimise the edited image too (resize, convert, thumbnail).
        $optimized = null;

        if ($this->imageLibraryAvailable()) {
            $processed = $this->processImageBytes($bytes, $mime);

            if ($processed) {
                $bytes = $processed['bytes'];
                $mime = $processed['mime'];
                $extension = $this->extensionForMime($mime);
                $optimized = $processed;
            }
        }

        $filename = Str::uuid().'-edited.'.$extension;
        $path = rtrim($this->directory, '/').'/'.$filename;

        Storage::disk($this->disk)->put($path, $bytes);

        $thumbnailPath = null;
        $thumbnailUrl = null;

        if (($optimized['thumbnail_bytes'] ?? null) !== null) {
            $thumbnailPath = rtrim($this->directory, '/').'/'.Str::uuid().'-edited-thumb.webp';
            Storage::disk($this->disk)->put($thumbnailPath, $optimized['thumbnail_bytes']);
            $thumbnailUrl = Storage::disk($this->disk)->url($thumbnailPath);
        }

        // Derive original name
        $original = $this->uploadedFiles[$originalIndex]['original_name'] ?? 'edited-image';
        $origBase = pathinfo($original, PATHINFO_FILENAME);

        return [
            'original_name' => $origBase.'-edited.'.$extension,
            'path' => $path,
            'disk' => $this->disk,
            'url' => Storage::disk($this->disk)->url($path),
            'size' => strlen($bytes),
            'size_human' => $this->formatBytes(strlen($bytes)),
            'mime' => $mime,
            'extension' => $extension,
            'is_image' => true,
            'is_pdf' => false,
            'is_document' => false,
            'is_existing' => false,
            'thumbnail' => $thumbnailPath,
            'thumbnail_url' => $thumbnailUrl,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }
}
