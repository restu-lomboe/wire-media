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
     * Build a metadata array from a bare path.
     * Supports:
     *  - Storage disk (storage/app/public) e.g. uploads/gallery/xxx.jpg
     *  - Direct public/ folder with dynamic name e.g. upload/image.png, custom_folder/photo.jpg, assets/img.jpg
     *  - External URL e.g. https://cdn.example.com/img.jpg or S3 https://bucket.s3.amazonaws.com/...
     *  - Missing file → placeholder with alt "image not found"
     */
    protected function hydrateFromPath(string $path): ?array
    {
        $disk = $this->disk;

        // 0) External URL (http/https, //, S3, CDN)
        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, '//')) {
            $parsedPath = parse_url($path, PHP_URL_PATH) ?: $path;
            $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
            $basename = pathinfo($parsedPath, PATHINFO_BASENAME) ?: 'external-image';
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                default => str_starts_with($extension, 'svg') ? 'image/svg+xml' : 'image/jpeg',
            };

            return $this->hydrateMetadata([
                'original_name' => $basename,
                'path' => $path,
                'disk' => 'external',
                'url' => $path,
                'size' => 0,
                'mime' => $mime,
                'extension' => $extension ?: 'jpg',
                'is_external' => true,
                'uploaded_at' => now()->toIso8601String(),
            ]);
        }

        // 1) Try Storage disk first (e.g. uploads/gallery/xxx.jpg, also works for S3 disk if configured)
        try {
            if (Storage::disk($disk)->exists($path)) {
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
        } catch (\Throwable $e) {
            // disk may not exist, continue to public fallback
        }

        // 2) Fallback to public/ folder with dynamic name (e.g. upload/image.png, custom_folder/photo.jpg, any folder under public/)
        $publicFullPath = public_path($path);

        if (file_exists($publicFullPath) && is_file($publicFullPath)) {
            $url = asset($path);
            $size = filesize($publicFullPath) ?: 0;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $basename = pathinfo($path, PATHINFO_BASENAME);
            $mime = @mime_content_type($publicFullPath) ?: match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };

            return $this->hydrateMetadata([
                'original_name' => $basename,
                'path' => $path,
                'disk' => 'public',
                'url' => $url,
                'size' => $size,
                'mime' => $mime,
                'extension' => $extension,
                'uploaded_at' => now()->toIso8601String(),
            ]);
        }

        // 3) Not found anywhere → placeholder with alt "image not found"
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
        $basename = pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_BASENAME) ?: $path;
        $placeholderUrl = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><rect width="400" height="400" fill="#e2e8f0"/><text x="200" y="200" text-anchor="middle" dy=".35em" fill="#64748b" font-family="sans-serif" font-size="14">Image not found</text><text x="200" y="220" text-anchor="middle" fill="#94a3b8" font-family="sans-serif" font-size="10">'.$basename.'</text></svg>');

        return $this->hydrateMetadata([
            'original_name' => $basename.' (not found)',
            'path' => $path,
            'disk' => $disk,
            'url' => $placeholderUrl,
            'size' => 0,
            'mime' => 'image/svg+xml',
            'extension' => $extension ?: 'png',
            'is_missing' => true,
            'placeholder' => true,
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

        // Resolve URL: prefer Storage, fallback to public/ asset
        $url = $meta['url'] ?? null;

        if (! $url) {
            try {
                if ($path && Storage::disk($disk)->exists($path)) {
                    $url = Storage::disk($disk)->url($path);
                } elseif ($path && file_exists(public_path($path))) {
                    $url = asset($path);
                } else {
                    $url = $path ? Storage::disk($disk)->url($path) : '';
                }
            } catch (\Throwable $e) {
                $url = $path ? asset($path) : '';
            }
        }

        // Resolve size if missing and file exists in public/
        if (! $size && $path && file_exists(public_path($path))) {
            $size = filesize(public_path($path)) ?: 0;
        }

        return array_merge($meta, [
            'disk' => $disk,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
            'size_human' => $meta['size_human'] ?? $this->formatBytes((int) $size),
            'url' => $url,
            'thumbnail' => $meta['thumbnail'] ?? null,
            'thumbnail_url' => $meta['thumbnail_url'] ?? null,
            'is_image' => $meta['is_image'] ?? str_starts_with($mime, 'image/'),
            'is_document' => $meta['is_document'] ?? $this->isDocument($mime, $extension),
            'is_pdf' => ($mime === 'application/pdf' || $extension === 'pdf'),
            'uploaded_at' => $meta['uploaded_at'] ?? now()->toIso8601String(),
        ]);
    }
}
