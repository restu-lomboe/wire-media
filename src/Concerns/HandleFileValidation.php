<?php

namespace Developerawam\WireMedia\Concerns;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HandleFileValidation
{
    /**
     * Validate a single file against the component's configuration.
     *
     * @return array Array of error messages (empty = valid)
     */
    protected function validateFile(TemporaryUploadedFile $file): array
    {
        $errors = [];

        // ── Size validation ─────────────────────────────────────────────────
        $maxBytes = (int) ($this->maxSizeMb * 1024 * 1024);

        if ($file->getSize() > $maxBytes) {
            $errors[] = trans('wire-media::messages.max_size', [
                'file' => $file->getClientOriginalName(),
                'max' => $this->maxSizeMb,
                'actual' => round($file->getSize() / 1024 / 1024, 2),
            ]);
        }

        // ── Extension validation ────────────────────────────────────────────
        if (! empty($this->acceptedExtensions)) {
            $extension = strtolower($file->getClientOriginalExtension());
            $allowed = array_map('strtolower', $this->acceptedExtensions);

            if (! in_array($extension, $allowed, true)) {
                $errors[] = trans('wire-media::messages.invalid_extension', [
                    'file' => $file->getClientOriginalName(),
                    'extension' => $extension,
                    'allowed' => implode(', ', $allowed),
                ]);
            }
        }

        // ── MIME type validation ────────────────────────────────────────────
        if (! empty($this->acceptedMimeTypes)) {
            $mime = $file->getMimeType();
            $allowed = $this->acceptedMimeTypes;

            if (! $this->mimeIsAllowed($mime, $allowed)) {
                $errors[] = trans('wire-media::messages.invalid_mime', [
                    'file' => $file->getClientOriginalName(),
                    'mime' => $mime,
                    'allowed' => implode(', ', $allowed),
                ]);
            }
        }

        return $errors;
    }

    /**
     * Determine whether a MIME type matches a list of allowed types.
     * Supports wildcard MIME types like "image/*".
     */
    protected function mimeIsAllowed(string $mime, array $allowed): bool
    {
        foreach ($allowed as $allowedMime) {
            if (str_ends_with($allowedMime, '/*')) {
                $prefix = rtrim($allowedMime, '/*');
                if (str_starts_with($mime, $prefix.'/')) {
                    return true;
                }
            } elseif ($allowedMime === $mime) {
                return true;
            }
        }

        return false;
    }
}
