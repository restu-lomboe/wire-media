---
name: wire-media
description: "Use for any task involving developerawam/wire-media — Livewire file upload, image editor, preview, and media management. Trigger when user mentions wire-media, file upload, image compress, crop, convert, preview-only, or media handling with Livewire. Covers: <livewire:wire-media> props (multiple, accept, max-size, disk, directory, previewOnly, autoCompress, uiAutoCompress, useImageLibrary, existingFiles), storage/public/external/placeholder handling, image library (resize/convert/thumbnail), editor (zoom/rotate/flip/crop/compress/library), browser events (file-uploaded/removed/cleared/image-processed), and darkMode."
license: MIT
metadata:
  author: developerawam
---

# WireMedia Development

Premium Livewire media upload & editor for Laravel 12/13. Zero external JS, Alpine 3, Intervention Image optional.

## Installation & Config

```bash
composer require developerawam/wire-media
composer require intervention/image # optional for library/compress
```

Publish (optional):
```bash
php artisan vendor:publish --tag=wire-media-config
php artisan vendor:publish --tag=wire-media-views
php artisan vendor:publish --tag=wire-media-lang
```

Config `config/wire-media.php` defaults (prop overrides config):
- `disk` (`public`, env `WIRE_MEDIA_DISK`), `directory` (`uploads`), `max_size_mb` (2), `max_files` (0), `accepted_mime_types`/`accepted_extensions` ([] = all), `show_preview`/`show_list` (true), `dark_mode` (false), `preview_only` (false), `use_image_library` (false), `auto_compress` (false), `ui_auto_compress` (false), `image_library.driver` (`gd`/`imagick`), `max_width` 1920, `max_height` 1080, `quality` 85, `format` `webp`, `thumbnail` 400x400 q80

Increase Livewire payload for large canvas `dataUrl` (3-4 MB):
```php
// config/livewire.php
'payload' => ['max_size' => 5 * 1024 * 1024],
```

## Props (All)

| Prop | Type | Default | Notes |
|------|------|---------|-------|
| `multiple` | bool | false | |
| `accepted-extensions` | array | [] | `['jpg','png','pdf']` |
| `accepted-mime-types` | array | [] | `['image/*']` wildcard |
| `accept-attribute` | string | '' | raw `accept` |
| `max-size-mb` | float | 2.0 | |
| `max-files` | int | 0 | 0=unlimited |
| `disk` | string | 'public' | |
| `directory` | string | 'uploads' | |
| `show-preview`/`show-list` | bool | true | |
| `label`/`help-text` | string | '' | |
| `existing-files` | mixed | null | paths, metadata, or Model + `model-attribute` |
| `model-attribute` | string | null | |
| `dark-mode` | bool | false | force dark |
| `use-image-library` | bool | false | needs `intervention/image` |
| `auto-compress` | bool | false | upload lossless same mime q92, library `resize+webp` when both true |
| `ui-auto-compress` | bool | false | editor Compress slider 0-100 |
| `preview-only` | bool | false | hide dropzone/edit/delete, only preview |

Gating: `no flags`→ori, `autoCompress`→upload compressed, `uiAutoCompress`→edit only, both→upload+UI. Library `useImageLibrary` resize only when `autoCompress=true`.

## Basic Usage

```blade
<livewire:wire-media />
<livewire:wire-media :multiple="true" :accepted-mime-types="['image/*']" :max-size-mb="10" :max-files="10" disk="s3" directory="products" />
<livewire:wire-media :existing-files="['uploads/photo.jpg']" :multiple="true" />
<livewire:wire-media :existing-files="$user" model-attribute="avatar" />
<livewire:wire-media :preview-only="true" :existing-files="['upload/photo.jpg', 'https://cdn.example.com/img.jpg']" />
```

Preview-only supports: Storage `uploads/...`, public `upload/...` any folder via `public_path()` → `asset()`, external `https://...`/`//`/`s3`, missing → `data:image/svg+xml` placeholder “Image not found”.

## Editor (Fullscreen Split Stage)

Toolbar: Zoom (−/label/+/reset) | Rotate ±90 | Flip H/V | Crop (drag → Apply) | Compress (if `uiAutoCompress`, slider + Apply/Cancel, PNG→JPEG at <100%) | Library (if `useImageLibrary`: grayscale/blur/sharpen slider + Convert WebP/JPG/PNG) | Reset | Cancel

Stage: `canvas left flex` + `info right 320px` (stack on <768px), hint overlay bottom inside `canvas-wrap`, loading `Converting…` overlay.

Info live: `Name/Type (mime)/Size (human + bytes ↓/↑)/Disk/Path` + badge `Cropped/Compressed/Converted/Edited — Save to apply` (pulse on Save). `hasChanges` = `src!==originalSrc || mime!==fileInfo.mime || pendingTransforms`.

Save bar: `Replace/Add` radio, `Download`, `Save to server` (pulse when edited), `Cancel`. `libraryTransform`/`compress Apply` use server `libraryTransformDataUrl` if payload <900KB else client `canvas.toDataURL`.

## Viewer (LightboxManager)

Fullscreen split: preview left (image `<img>` + spinner, PDF `<iframe>`, doc badge) + details right (sticky head). Controls: prev/next (←→, swipe), strip, zoom (buttons/wheel/+/−/0), download, edit (hidden if `previewOnly` or not image), Esc.

## Browser Events (Global)

| Event | Payload | Dispatch |
|-------|---------|----------|
| `file-uploaded` | `{file}` | `updatedFiles` |
| `file-removed` | `{file}` | `removeFile` + `replace` old |
| `files-cleared` | — | `clearAll` |
| `image-processed` | `{file, mode}` | `saveProcessedImage` (`replace`/`add`) |

Listen:
```php
use Livewire\Attributes\On;
#[On('file-uploaded')] public function onFileUploaded(array $file): void { $this->remember($file['path']); }
#[On('file-removed')] public function onFileRemoved(array $file): void { $this->forget($file['path']); }
#[On('files-cleared')] public function onFilesCleared(): void { $this->galleryPaths=[]; }
#[On('image-processed')] public function onImageProcessed(array $file, string $mode): void { $this->remember($file['path']); } // handle both add/replace
```
JS: `window.addEventListener('image-processed', e=>console.log(e.detail.file.url, e.detail.mode))`

`saveProcessedImage` dispatches `file-removed` for old on `replace` + `image-processed` for new; parent should `remember` on both modes.

## File Metadata

```php
['original_name'=>'photo.jpg','path'=>'uploads/uuid.webp','disk'=>'public','url'=>'/storage/...','size'=>204800,'size_human'=>'200 KB','mime'=>'image/webp','extension'=>'webp','is_image'=>true,'is_pdf'=>false,'is_document'=>false,'is_existing'=>false,'is_external'=>false,'is_missing'=>false,'thumbnail'=>'...-thumb.webp','thumbnail_url'=>'...','uploaded_at'=>'...']
```

## Best Practices

- Always pass `:preview-only` for read-only galleries; ensure `existing-files` paths exist or external URL reachable else placeholder shows.
- For large photos, set `livewire.payload.max_size` 5MB and `max-size-mb` accordingly.
- Check `class_exists('Intervention\Image\ImageManager')` before `useImageLibrary/autoCompress`.
- Use `#[On]` with correct signature (`file-uploaded` 1 param, `image-processed` 2 params).
- Store `remembered` paths in parent component for persistence (`dd` in example is debug — replace with model sync).
