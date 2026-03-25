<?php

namespace Developerawam\WireMedia\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandleImageProcessing
{
    /**
     * Receive a base64-encoded PNG from the editor (crop/transform result),
     * store it to disk, and replace the file at $index in $uploadedFiles.
     *
     * Called via wire:click from the editor "Save to Server" button.
     *
     * @param  int     $index    Index in $this->uploadedFiles to replace
     * @param  string  $dataUrl  PNG data URL (data:image/png;base64,...)
     * @param  string  $mode     'replace' | 'add'
     */
    public function saveProcessedImage(int $index, string $dataUrl, string $mode = 'replace'): void
    {
        if (! str_starts_with($dataUrl, 'data:image/')) {
            $this->errors[] = 'Invalid image data.';
            return;
        }

        try {
            $stored = $this->storeDataUrl($dataUrl, $index);

            if (! $stored) {
                $this->errors[] = 'Failed to save processed image.';
                return;
            }

            if ($mode === 'replace' && isset($this->uploadedFiles[$index])) {
                // Delete old file from storage
                $old  = $this->uploadedFiles[$index];
                $disk = $old['disk'] ?? $this->disk;
                $path = $old['path'] ?? null;

                if ($path && \Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
                }

                $this->uploadedFiles[$index] = $stored;
            } else {
                // 'add' mode — append as new file
                $this->uploadedFiles[] = $stored;
            }

            $this->editorOpen     = false;
            $this->editorIndex    = null;
            $this->editorDataUrl  = null;

            $this->dispatch('image-processed', file: $stored, mode: $mode);

        } catch (\Throwable $e) {
            report($e);
            $this->errors[] = 'Error saving image: ' . $e->getMessage();
        }
    }

    /**
     * Decode a data URL and store the raw PNG bytes to the configured disk.
     */
    protected function storeDataUrl(string $dataUrl, int $originalIndex): ?array
    {
        // Strip header: "data:image/png;base64,"
        [$header, $base64] = explode(',', $dataUrl, 2);
        preg_match('/data:(image\/\w+);base64/', $header, $m);
        $mime      = $m[1] ?? 'image/png';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'png',
        };

        $bytes    = base64_decode($base64);
        $filename = Str::uuid() . '-edited.' . $extension;
        $path     = rtrim($this->directory, '/') . '/' . $filename;

        Storage::disk($this->disk)->put($path, $bytes);

        // Derive original name
        $original = $this->uploadedFiles[$originalIndex]['original_name'] ?? 'edited-image';
        $origBase = pathinfo($original, PATHINFO_FILENAME);

        return [
            'original_name' => $origBase . '-edited.' . $extension,
            'path'          => $path,
            'disk'          => $this->disk,
            'url'           => Storage::disk($this->disk)->url($path),
            'size'          => strlen($bytes),
            'size_human'    => $this->formatBytes(strlen($bytes)),
            'mime'          => $mime,
            'extension'     => $extension,
            'is_image'      => true,
            'is_pdf'        => false,
            'is_document'   => false,
            'is_existing'   => false,
            'uploaded_at'   => now()->toIso8601String(),
        ];
    }
}
