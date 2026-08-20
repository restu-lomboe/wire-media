<?php

namespace Developerawam\WireMedia\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

trait HandleExistingFiles
{
    /**
     * Load existing files either from a plain array of metadata/paths
     * or from an Eloquent model attribute.
     *
     * Supported $existingFiles formats:
     *   1. Array of full metadata arrays (previously returned by storeFile)
     *   2. Array of path strings:  ['uploads/foo.jpg', 'uploads/bar.pdf']
     *   3. Eloquent Model + attribute name containing path(s)
     *
     * @param  array|Model|null  $existingFiles
     * @param  string|null  $modelAttribute  Column name on the model
     */
    protected function loadExistingFiles(
        mixed $existingFiles,
        ?string $modelAttribute = null,
    ): void {
        if (! $existingFiles) {
            return;
        }

        // ── Eloquent model binding ──────────────────────────────────────────
        if ($existingFiles instanceof Model) {
            if (! $modelAttribute) {
                return;
            }

            $value = $existingFiles->getAttribute($modelAttribute);

            // Column may be a single path string, a JSON-encoded array, or already an array
            if (is_string($value)) {
                // Try JSON first (e.g. stored as JSON column)
                $decoded = json_decode($value, true);
                $paths = is_array($decoded) ? $decoded : [$value];
            } elseif (is_array($value)) {
                $paths = $value;
            } else {
                return;
            }

            foreach ($paths as $item) {
                $this->resolveAndAppend($item);
            }

            return;
        }

        // ── Plain array ─────────────────────────────────────────────────────
        if (is_array($existingFiles)) {
            foreach ($existingFiles as $item) {
                $this->resolveAndAppend($item);
            }
        }
    }

    /**
     * Resolve a single item (path string or metadata array) into a
     * normalised metadata array and append it to $this->uploadedFiles.
     */
    protected function resolveAndAppend(mixed $item): void
    {
        if (is_string($item)) {
            // Plain path string — hydrate metadata from storage
            $meta = $this->hydrateFromPath($item);
        } elseif (is_array($item) && isset($item['path'])) {
            // Already a full metadata array — hydrate any missing keys
            $meta = $this->hydrateMetadata($item);
        } else {
            return;
        }

        if ($meta) {
            $meta['is_existing'] = true;   // flag so the view can distinguish
            $this->uploadedFiles[] = $meta;
        }
    }

    /**
     * Build a metadata array from a bare storage path.
     */
    protected function hydrateFromPath(string $path): ?array
    {
        $disk = $this->disk;

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $url = Storage::disk($disk)->url($path);
        $size = Storage::disk($disk)->size($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $basename = pathinfo($path, PATHINFO_BASENAME);

        return $this->hydrateMetadata([
            'original_name' => $basename,
            'path' => $path,
            'disk' => $disk,
            'url' => $url,
            'size' => $size,
            'mime' => $mime,
            'extension' => $extension,
            'uploaded_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Fill any missing derived keys (size_human, is_image, is_document, url)
     * on an existing metadata array.
     */
    protected function hydrateMetadata(array $meta): array
    {
        $disk = $meta['disk'] ?? $this->disk;
        $path = $meta['path'] ?? '';
        $mime = $meta['mime'] ?? 'application/octet-stream';
        $extension = $meta['extension'] ?? strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $size = $meta['size'] ?? 0;

        return array_merge($meta, [
            'disk' => $disk,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
            'size_human' => $meta['size_human'] ?? $this->formatBytes((int) $size),
            'url' => $meta['url'] ?? Storage::disk($disk)->url($path),
            'thumbnail' => $meta['thumbnail'] ?? null,
            'thumbnail_url' => $meta['thumbnail_url'] ?? null,
            'is_image' => $meta['is_image'] ?? str_starts_with($mime, 'image/'),
            'is_document' => $meta['is_document'] ?? $this->isDocument($mime, $extension),
            'is_pdf' => ($mime === 'application/pdf' || $extension === 'pdf'),
            'uploaded_at' => $meta['uploaded_at'] ?? now()->toIso8601String(),
        ]);
    }
}
