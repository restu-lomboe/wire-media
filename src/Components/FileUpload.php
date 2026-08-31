<?php

namespace Developerawam\WireMedia\Components;

use Developerawam\WireMedia\Concerns\HandleExistingFiles;
use Developerawam\WireMedia\Concerns\HandleFileStorage;
use Developerawam\WireMedia\Concerns\HandleFileValidation;
use Developerawam\WireMedia\Concerns\HandleImageLibrary;
use Developerawam\WireMedia\Concerns\HandleImageProcessing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileUpload extends Component
{
    use HandleExistingFiles;
    use HandleFileStorage;
    use HandleFileValidation;
    use HandleImageLibrary;
    use HandleImageProcessing;
    use WithFileUploads;

    // ─── Wire model ──────────────────────────────────────────────────────────
    public mixed $files = null;

    /** @var array All file metadata (new + existing) */
    public array $uploadedFiles = [];

    // ─── Edit / existing ─────────────────────────────────────────────────────
    public mixed $existingFiles = null;

    public ?string $modelAttribute = null;

    // ─── Config ──────────────────────────────────────────────────────────────
    public bool $multiple = false;

    public array $acceptedMimeTypes = [];

    public array $acceptedExtensions = [];

    public string $acceptAttribute = '';

    public float $maxSizeMb = 2.0;

    public int $maxFiles = 0;

    public string $disk = 'public';

    public string $directory = 'uploads';

    public bool $showPreview = true;

    public bool $showList = true;

    public string $label = '';

    public string $helpText = '';

    public bool $darkMode = false;

    public ?bool $useImageLibrary = false;

    public ?bool $autoCompress = false;

    public ?bool $uiAutoCompress = false;

    public bool $previewOnly = false;

    // ─── UI state ────────────────────────────────────────────────────────────
    public array $errors = [];

    /** Detail panel */
    public ?int $previewIndex = null;

    /** Image editor panel */
    public bool $editorOpen = false;

    public ?int $editorIndex = null;

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(
        bool $multiple = false,
        array $acceptedMimeTypes = [],
        array $acceptedExtensions = [],
        string $acceptAttribute = '',
        ?float $maxSizeMb = null,
        ?int $maxFiles = null,
        ?string $disk = null,
        ?string $directory = null,
        ?bool $showPreview = null,
        ?bool $showList = null,
        string $label = '',
        string $helpText = '',
        mixed $existingFiles = null,
        ?string $modelAttribute = null,
        ?bool $darkMode = null,
        ?bool $useImageLibrary = null,
        ?bool $autoCompress = null,
        ?bool $uiAutoCompress = null,
        bool $previewOnly = false,
    ): void {
        $this->multiple = $multiple;
        $this->acceptedMimeTypes = $acceptedMimeTypes ?: (array) config('wire-media.accepted_mime_types', []);
        $this->acceptedExtensions = $acceptedExtensions ?: (array) config('wire-media.accepted_extensions', []);
        $this->maxSizeMb = (float) ($maxSizeMb ?? config('wire-media.max_size_mb', 2.0));
        $this->maxFiles = (int) ($maxFiles ?? config('wire-media.max_files', 0));
        $this->disk = $disk ?? (string) config('wire-media.disk', 'public');
        $this->directory = $directory ?? (string) config('wire-media.directory', 'uploads');
        $this->showPreview = $showPreview ?? (bool) config('wire-media.show_preview', true);
        $this->showList = $showList ?? (bool) config('wire-media.show_list', true);
        $this->label = $label ?: trans('wire-media::messages.default_label');
        $this->helpText = $helpText;
        $this->modelAttribute = $modelAttribute;
        $this->acceptAttribute = $acceptAttribute ?: $this->buildAcceptAttribute();
        $this->darkMode = $darkMode ?? (bool) config('wire-media.dark_mode', false);
        $this->useImageLibrary = $useImageLibrary ?? (bool) config('wire-media.use_image_library', false);
        $this->autoCompress = $autoCompress ?? (bool) config('wire-media.auto_compress', false);
        $this->uiAutoCompress = $uiAutoCompress ?? (bool) config('wire-media.ui_auto_compress', false);
        $this->previewOnly = $previewOnly || (bool) config('wire-media.preview_only', false);

        if ($existingFiles) {
            $this->loadExistingFiles($existingFiles, $modelAttribute);
        }
    }

    // ─── Upload lifecycle ────────────────────────────────────────────────────

    public function updatedFiles(): void
    {
        if ($this->previewOnly) {
            $this->errors[] = trans('wire-media::messages.preview_only_no_upload');

            return;
        }

        $this->errors = [];

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
            if (! $file) {
                continue;
            }

            $errs = $this->validateFile($file);
            if (! empty($errs)) {
                $this->errors = array_merge($this->errors, $errs);

                continue;
            }

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
        if ($this->previewOnly) {
            return;
        }

        if (! isset($this->uploadedFiles[$index])) {
            return;
        }

        $removed = $this->uploadedFiles[$index];
        $disk = $removed['disk'] ?? $this->disk;

        // Never delete pre-existing files from storage — the app owns those.
        if (! ($removed['is_existing'] ?? false)) {
            $this->deleteStoredFile($removed, $disk);
        }

        if ($this->previewIndex === $index) {
            $this->previewIndex = null;
        }
        if ($this->editorIndex === $index) {
            $this->editorOpen = false;
            $this->editorIndex = null;
        }

        array_splice($this->uploadedFiles, $index, 1);

        if ($this->previewIndex !== null && $this->previewIndex > $index) {
            $this->previewIndex--;
        }
        if ($this->editorIndex !== null && $this->editorIndex > $index) {
            $this->editorIndex--;
        }

        $this->dispatch('file-removed', file: $removed);
    }

    public function clearAll(): void
    {
        if ($this->previewOnly) {
            return;
        }

        foreach ($this->uploadedFiles as $file) {
            // Pre-existing files are owned by the app — leave them on disk.
            if ($file['is_existing'] ?? false) {
                continue;
            }

            $disk = $file['disk'] ?? $this->disk;
            $this->deleteStoredFile($file, $disk);
        }

        $this->uploadedFiles = [];
        $this->errors = [];
        $this->previewIndex = null;
        $this->editorOpen = false;
        $this->editorIndex = null;
        $this->dispatch('files-cleared');
    }

    // ─── Detail panel ────────────────────────────────────────────────────────

    public function openPreview(int $index): void
    {
        $this->previewIndex = isset($this->uploadedFiles[$index]) ? $index : null;
        $this->editorOpen = false;
    }

    public function closePreview(): void
    {
        $this->previewIndex = null;
    }

    // ─── Image editor ────────────────────────────────────────────────────────

    public function openEditor(int $index): void
    {
        if ($this->previewOnly) {
            return;
        }

        if (! isset($this->uploadedFiles[$index])) {
            return;
        }
        if (! $this->uploadedFiles[$index]['is_image']) {
            return;
        }

        $this->editorIndex = $index;
        $this->editorOpen = true;
        $this->previewIndex = null;
        $this->errors = [];
    }

    public function closeEditor(): void
    {
        $this->editorOpen = false;
        $this->editorIndex = null;
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
        foreach ($this->acceptedExtensions as $ext) {
            $parts[] = '.'.ltrim($ext, '.');
        }
        foreach ($this->acceptedMimeTypes as $mime) {
            $parts[] = $mime;
        }

        return implode(',', $parts);
    }

    public function render(): View
    {
        return view('wire-media::components.file-upload', [
            'wireMediaJsPath' => dirname(__DIR__, 2).'/resources/js',
        ]);
    }
}
