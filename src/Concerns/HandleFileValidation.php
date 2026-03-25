<?php

namespace Developerawam\WireMedia\Concerns;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HandleFileValidation
{
    /**
     * Validate a single file against the component's configuration.
     *
     * @param  TemporaryUploadedFile  $file
     * @return array  Array of error messages (empty = valid)
     */
    protected function validateFile(TemporaryUploadedFile $file): array
    {
        $errors = [];

        // ── Size validation ─────────────────────────────────────────────────
        $maxBytes = (int) ($this->maxSizeMb * 1024 * 1024);

        if ($file->getSize() > $maxBytes) {
            $errors[] = trans('wire-media::messages.max_size', [
                'file'    => $file->getClientOriginalName(),
                'max'     => $this->maxSizeMb,
                'actual'  => round($file->getSize() / 1024 / 1024, 2),
            ]);
        }

        // ── Extension validation ────────────────────────────────────────────
        if (! empty($this->acceptedExtensions)) {
            $extension = strtolower($file->getClientOriginalExtension());
            $allowed   = array_map('strtolower', $this->acceptedExtensions);

            if (! in_array($extension, $allowed, true)) {
                $errors[] = trans('wire-media::messages.invalid_extension', [
                    'file'      => $file->getClientOriginalName(),
                    'extension' => $extension,
                    'allowed'   => implode(', ', $allowed),
                ]);
            }
        }

        // ── MIME type validation ────────────────────────────────────────────
        if (! empty($this->acceptedMimeTypes)) {
            $mime    = $file->getMimeType();
            $allowed = $this->acceptedMimeTypes;

            $matched = false;
            foreach ($allowed as $allowedMime) {
                // Support wildcard MIME types like "image/*"
                if (str_ends_with($allowedMime, '/*')) {
                    $prefix = rtrim($allowedMime, '/*');
                    if (str_starts_with($mime, $prefix . '/')) {
                        $matched = true;
                        break;
                    }
                } elseif ($allowedMime === $mime) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $errors[] = trans('wire-media::messages.invalid_mime', [
                    'file'    => $file->getClientOriginalName(),
                    'mime'    => $mime,
                    'allowed' => implode(', ', $allowed),
                ]);
            }
        }

        return $errors;
    }
}
