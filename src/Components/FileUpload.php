<?php

namespace Developerawam\WireMedia\Components;

use Developerawam\WireMedia\Concerns\HandleExistingFiles;
use Developerawam\WireMedia\Concerns\HandleFileStorage;
use Developerawam\WireMedia\Concerns\HandleFileValidation;
use Developerawam\WireMedia\Concerns\HandleImageProcessing;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileUpload extends Component
{
    use HandleExistingFiles;
    use HandleFileStorage;
    use HandleFileValidation;
    use HandleImageProcessing;
    use WithFileUploads;

    // ─── Wire model ──────────────────────────────────────────────────────────
    public mixed $files = null;

    /** @var array  All file metadata (new + existing) */
    public array $uploadedFiles = [];

    // ─── Edit / existing ─────────────────────────────────────────────────────
    public mixed   $existingFiles   = null;
    public ?string $modelAttribute  = null;

    // ─── Config ──────────────────────────────────────────────────────────────
    public bool   $multiple           = false;
    public array  $acceptedMimeTypes  = [];
    public array  $acceptedExtensions = [];
    public string $acceptAttribute    = '';
    public float  $maxSizeMb          = 2.0;
    public int    $maxFiles           = 0;
    public string $disk               = 'public';
    public string $directory          = 'uploads';
    public bool   $showPreview        = true;
    public bool   $showList           = true;
    public string $label              = '';
    public string $helpText           = '';
    public bool   $darkMode           = false;

    // ─── UI state ────────────────────────────────────────────────────────────
    public array   $errors        = [];
    public bool    $isUploading   = false;
    public string  $uploadProgress = '0';

    /** Detail panel */
    public ?int    $previewIndex = null;

    /** Image editor panel */
    public bool    $editorOpen    = false;
    public ?int    $editorIndex   = null;

    /**
     * Base64 data-URL sent from JS canvas → PHP for server-side storage.
     * Kept as a Livewire public property so it can be submitted via wire:click.
     * Note: For large images the data URL can be big; we accept this trade-off
     * to keep the package dependency-free.
     */
    public ?string $editorDataUrl = null;

    /** 'replace' | 'add' */
    public string  $editorSaveMode = 'replace';

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(
        bool    $multiple           = false,
        array   $acceptedMimeTypes  = [],
        array   $acceptedExtensions = [],
        string  $acceptAttribute    = '',
        float   $maxSizeMb          = 2.0,
        int     $maxFiles           = 0,
        string  $disk               = 'public',
        string  $directory          = 'uploads',
        bool    $showPreview        = true,
        bool    $showList           = true,
        string  $label              = '',
        string  $helpText           = '',
        mixed   $existingFiles      = null,
        ?string $modelAttribute     = null,
        bool    $darkMode           = false,
    ): void {
        $this->multiple           = $multiple;
        $this->acceptedMimeTypes  = $acceptedMimeTypes;
        $this->acceptedExtensions = $acceptedExtensions;
        $this->maxSizeMb          = $maxSizeMb;
        $this->maxFiles           = $maxFiles;
        $this->disk               = $disk;
        $this->directory          = $directory;
        $this->showPreview        = $showPreview;
        $this->showList           = $showList;
        $this->label              = $label ?: trans('wire-media::messages.default_label');
        $this->helpText           = $helpText;
        $this->modelAttribute     = $modelAttribute;
        $this->acceptAttribute    = $acceptAttribute ?: $this->buildAcceptAttribute();
        $this->darkMode           = $darkMode;

        if ($existingFiles) {
            $this->loadExistingFiles($existingFiles, $modelAttribute);
        }
    }

    // ─── Upload lifecycle ────────────────────────────────────────────────────

    public function updatedFiles(): void
    {
        $this->errors      = [];
        $this->isUploading = false;

        $filesToProcess = $this->multiple
            ? (is_array($this->files) ? $this->files : [$this->files])
            : [$this->files];

        if ($this->multiple && $this->maxFiles > 0) {
            if (count($filesToProcess) + count($this->uploadedFiles) > $this->maxFiles) {
                $this->errors[] = trans('wire-media::messages.max_files', ['max' => $this->maxFiles]);
                return;
            }
        }

        foreach ($filesToProcess as $file) {
            if (! $file) continue;

            $errs = $this->validateFile($file);
            if (! empty($errs)) { $this->errors = array_merge($this->errors, $errs); continue; }

            $stored = $this->storeFile($file);
            if ($stored) {
                $stored['is_existing'] = false;
                $this->uploadedFiles[] = $stored;
                $this->dispatch('file-uploaded', file: $stored);
            }
        }

        $this->files = null;
    }

    // ─── Remove / clear ──────────────────────────────────────────────────────

    public function removeFile(int $index): void
    {
        if (! isset($this->uploadedFiles[$index])) return;

        $removed = $this->uploadedFiles[$index];
        $disk    = $removed['disk'] ?? $this->disk;
        $path    = $removed['path'] ?? null;

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        if ($this->previewIndex === $index) $this->previewIndex = null;
        if ($this->editorIndex  === $index) { $this->editorOpen = false; $this->editorIndex = null; }

        array_splice($this->uploadedFiles, $index, 1);

        if ($this->previewIndex !== null && $this->previewIndex > $index) $this->previewIndex--;
        if ($this->editorIndex  !== null && $this->editorIndex  > $index) $this->editorIndex--;

        $this->dispatch('file-removed', file: $removed);
    }

    public function clearAll(): void
    {
        foreach ($this->uploadedFiles as $file) {
            $disk = $file['disk'] ?? $this->disk;
            $path = $file['path'] ?? null;
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        $this->uploadedFiles  = [];
        $this->errors         = [];
        $this->previewIndex   = null;
        $this->editorOpen     = false;
        $this->editorIndex    = null;
        $this->editorDataUrl  = null;
        $this->dispatch('files-cleared');
    }

    // ─── Detail panel ────────────────────────────────────────────────────────

    public function openPreview(int $index): void
    {
        $this->previewIndex = isset($this->uploadedFiles[$index]) ? $index : null;
        $this->editorOpen   = false;
    }

    public function closePreview(): void
    {
        $this->previewIndex = null;
    }

    // ─── Image editor ────────────────────────────────────────────────────────

    public function openEditor(int $index): void
    {
        if (! isset($this->uploadedFiles[$index])) return;
        if (! $this->uploadedFiles[$index]['is_image']) return;

        $this->editorIndex   = $index;
        $this->editorOpen    = true;
        $this->editorDataUrl = null;
        $this->previewIndex  = null;
        $this->errors        = [];
    }

    public function closeEditor(): void
    {
        $this->editorOpen    = false;
        $this->editorIndex   = null;
        $this->editorDataUrl = null;
    }

    /**
     * Called by JS via $wire.saveProcessedImage(index, dataUrl, mode).
     * Delegates to HandleImageProcessing trait.
     */
    // saveProcessedImage() is in HandleImageProcessing trait

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getUploadedPaths(): array
    {
        return collect($this->uploadedFiles)->pluck('path')->all();
    }

    protected function buildAcceptAttribute(): string
    {
        $parts = [];
        foreach ($this->acceptedExtensions as $ext) $parts[] = '.'.ltrim($ext, '.');
        foreach ($this->acceptedMimeTypes   as $mime) $parts[] = $mime;
        return implode(',', $parts);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('wire-media::components.file-upload');
    }
}
