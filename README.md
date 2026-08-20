# WireMedia

[![Latest Version](https://img.shields.io/packagist/v/developerawam/wire-media.svg?style=flat-square)](https://packagist.org/packages/developerawam/wire-media)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-v3%20%7C%20v4-FB70A9?style=flat-square)](https://livewire.laravel.com)
[![License](https://img.shields.io/packagist/l/developerawam/wire-media.svg?style=flat-square)](LICENSE.md)

**Premium Livewire media upload & image editor for Laravel 12.**  
Upload, crop, zoom, rotate, flip, **fullscreen split file viewer** (preview + details side-by-side) and **fullscreen image editor** — zero external JS dependencies.

> **Works out of the box with Livewire v3 AND v4.** Component assets (styles + scripts) are injected via Livewire's `@assets` mechanism, so they are loaded once per page, deduplicated, and **never broken by Livewire's DOM morphing after an upload**.

---

## ✅ Requirements

| Dependency | Version       |
|------------|---------------|
| PHP        | ^8.2          |
| Laravel    | ^12.0         |
| Livewire   | ^3.0 or ^4.0  |
| Alpine.js  | ^3.x          |

---

## 📥 Installation

```bash
composer require developerawam/wire-media
```

Ensure your layout includes Alpine.js and Livewire scripts:

```html
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@livewireStyles
@livewireScripts
```

### Publish (optional)

```bash
# Config
php artisan vendor:publish --tag=wire-media-config

# Views (to customise)
php artisan vendor:publish --tag=wire-media-views

# Language files
php artisan vendor:publish --tag=wire-media-lang

# JS assets to public/
php artisan vendor:publish --tag=wire-media-assets
```

> **Note:** The published config (`config/wire-media.php`) sets the **defaults** for `disk`, `directory`, `max-size-mb`, `max-files`, accepted types and UI options. Any prop passed to `<livewire:wire-media>` overrides its config default; props left unset fall back to the config value.

### Optional: Image Library Mode

By default WireMedia stores images **as uploaded**. You can optionally enable automatic image processing (resize, format conversion, optimisation & thumbnail generation) powered by [Intervention Image](https://intervention.io):

```bash
composer require intervention/image
```

Then set the `use_image_library` config to `true`:

```env
WIRE_MEDIA_USE_IMAGE_LIBRARY=true
```

| Config key                                     | Default      | Description                                     |
|------------------------------------------------|--------------|-------------------------------------------------|
| `WIRE_MEDIA_IMAGE_DRIVER`                      | `gd`         | `gd` or `imagick`                               |
| `WIRE_MEDIA_IMAGE_MAX_WIDTH` / `MAX_HEIGHT`    | `1920/1080`  | Scale down larger images to this bound          |
| `WIRE_MEDIA_IMAGE_QUALITY`                     | `85`         | Output quality (JPEG/WebP, 1–100)               |
| `WIRE_MEDIA_IMAGE_FORMAT`                      | `webp`       | Output format: `webp`, `jpg`, `png`, `gif`      |

The processor automatically:
- **Resizes** oversized images down (`scaleDown`, never upscales)
- **Converts** to the target format with quality compression
- **Generates a WebP thumbnail variant** (`-thumb`, 400×400, used in the grid)
- Applies to **uploads and editor saves**; thumbnails are removed together with their original file

When image-library mode is on, the **image editor toolbar** additionally exposes server-side
Intervention transforms: **grayscale**, **blur**, **sharpen**, and one-click **convert**
(WebP / JPG / PNG). Edits are re-encoded server-side so they stay optimised.

> **Fallback:** when the flag is off, or `intervention/image` is not installed,
> WireMedia silently behaves like the default mode — nothing breaks.

---

## 🚀 Basic Usage

```blade
<livewire:wire-media />
```

---

## 🎛️ All Props

| Prop                  | Type      | Default     | Description                                               |
|-----------------------|-----------|-------------|-----------------------------------------------------------|
| `multiple`            | `bool`    | `false`     | Allow multiple file selection                             |
| `accepted-extensions` | `array`   | `[]`        | Allowed extensions e.g. `['jpg','png','pdf']`             |
| `accepted-mime-types` | `array`   | `[]`        | Allowed MIME types e.g. `['image/*','application/pdf']`   |
| `accept-attribute`    | `string`  | `''`        | Raw HTML `accept` attribute string                        |
| `max-size-mb`         | `float`   | `2.0`       | Max file size in **megabytes**                            |
| `max-files`           | `int`     | `0`         | Max files when multiple (0 = unlimited)                   |
| `disk`                | `string`  | `'public'`  | Laravel filesystem disk                                   |
| `directory`           | `string`  | `'uploads'` | Sub-directory on disk                                     |
| `show-preview`        | `bool`    | `true`      | Show image thumbnails in grid                             |
| `show-list`           | `bool`    | `true`      | Show uploaded file grid                                   |
| `label`               | `string`  | `''`        | Label above dropzone                                      |
| `help-text`           | `string`  | `''`        | Help text below component                                 |
| `existing-files`      | `mixed`   | `null`      | Pre-load files (array of paths, metadata, or Model)       |
| `model-attribute`     | `string`  | `null`      | Model column when `existing-files` is an Eloquent Model   |
| `dark-mode`           | `bool`    | `false`     | Force dark mode (also auto-detects OS preference)         |
| `use-image-library`   | `bool`    | `false`     | Enable image processing for this instance (requires Intervention Image) |

---

## 🌟 Features

### Upload
- Drag & drop or click to browse
- Single or multiple files
- Extension, MIME type & file size validation
- Max file count enforcement

### Image Viewer — Fullscreen Split View
Click **any card thumbnail** (image, PDF or document) or the **↔ fullscreen button** (images).

The viewer opens as a **fullscreen modal split into two panes**:

```
┌──────────────────────────────────────────┬──────────────────┐
│                                          │  File name  [BADGE]│
│              FILE PREVIEW                │──────────────────│
│   (left pane, flexible width)            │  Type             │
│                                          │  Size             │
│                                          │  Disk             │
│                                          │  Path             │
│                                          │  Uploaded         │
│                                          │  Status           │
└──────────────────────────────────────────┴──────────────────┘
                [ thumbnail strip ]
```

**Left pane — preview** (adapts to file type):
- **Image** → `<img>` with loading spinner
- **PDF** → inline `<iframe>` embed (`#toolbar=0&navpanes=0`)
- **Other documents** → extension badge + "no preview" placeholder

**Right pane — details** (auto-fills on every slide):
- File name + **status badge** (`New` / `Existing`)
- **Type** (MIME + extension)
- **Size** (human readable + raw bytes)
- **Disk**
- **Path** (monospace)
- **Uploaded** date (localised)
- **Status**

**Navigation & controls:**
- Prev / Next (`←` `→` keyboard, buttons, swipe on mobile)
- Scrollable thumbnail strip (images show thumbnails; documents show extension badges)
- Zoom (buttons, scroll wheel, `+` / `-` / `0` — images only)
- **Download** in header
- **Edit** button in header (images only → opens the fullscreen editor)
- `Esc` to close

> The details panel is a dedicated component with **sticky header**, scrollable list, and a responsive layout: on narrow screens (<768px) the panes **stack vertically** (preview on top, details below).

### Image Editor — Fullscreen
Click ✏️ (on an image card, in the viewer, or in the detail panel) to open the editor.

The editor opens as a **fullscreen overlay** (`position: fixed`, covers the viewport):

```
┌──────────────────────────────────────────────────────────────────┐
│  Zoom ▾ ⬚ ▴  Rotate ⟳ ⟲  Flip ⇋ ⇵  Crop ▧ ✓   ...     Reset  ✕  │  ← toolbar
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│                         CANVAS  (flexible, fills the space)      │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│ ◉ Replace original  ◉ Keep both     ↓ Download   💾 Save   Cancel │  ← save bar
└──────────────────────────────────────────────────────────────────┘
```

| Action       | How                                   |
|--------------|---------------------------------------|
| Zoom in/out  | Scroll wheel or toolbar buttons       |
| Pan          | Drag when not in crop mode            |
| Rotate       | CW/CCW 90° buttons                    |
| Flip         | Horizontal or vertical buttons        |
| Crop         | Enable → drag to select → Apply ✓    |
| Reset        | Reset all transforms (undoes crops)   |

- **Close ✕** button pinned to the top-right of the toolbar
- **Cancel** also available in the save bar
- Save options: **Replace original**, **Keep both**, or **Download locally**

### Detail Panel (fallback, non-image)
For non-image files in the standard view, opening the card previously used an inline detail panel. With the fullscreen split viewer this is no longer needed — every file type now opens the unified fullscreen viewer instead.

### Edit Mode
Pre-load existing files:

```blade
{{-- From path array --}}
<livewire:wire-media
    :existing-files="['uploads/photo.jpg', 'uploads/doc.pdf']"
    :multiple="true"
/>

{{-- From Eloquent model --}}
<livewire:wire-media
    :existing-files="$user"
    model-attribute="avatar"
/>
```

### Dark Mode
Auto-detects OS preference. Force with `:dark-mode="true"`.

---

## 📸 Usage Examples

```blade
{{-- Image gallery with editor --}}
<livewire:wire-media
    :multiple="true"
    :accepted-mime-types="['image/*']"
    :max-size-mb="10"
    :max-files="20"
    label="Photo Gallery"
    :dark-mode="true"
/>

{{-- Document attachments --}}
<livewire:wire-media
    :multiple="true"
    :accepted-extensions="['pdf','docx','xlsx']"
    :max-size-mb="25"
    label="Attachments"
/>

{{-- S3 upload --}}
<livewire:wire-media
    disk="s3"
    directory="products/images"
    :accepted-mime-types="['image/*']"
    :max-size-mb="20"
/>
```

---

## 🎉 Browser Events

| Event             | Payload          | Description                     |
|-------------------|------------------|---------------------------------|
| `file-uploaded`   | `{ file }`       | New file stored                 |
| `file-removed`    | `{ file }`       | File deleted from storage       |
| `files-cleared`   | —                | All files deleted               |
| `image-processed` | `{ file, mode }` | Edited image saved to server    |

> The events are dispatched **globally** through the browser (not component-scoped), so they can be listened to from **any Livewire component**, plain JavaScript, or Alpine.

### 🙉 Listening from a Livewire component

Use the `#[On]` (or `#[On('...')]`) attribute to react to any of the events from another component. In Livewire v4 single-file components (SFC) this is done directly in the class:

```php
<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public array $galleryPaths = [];

    #[On('file-uploaded')]
    public function onFileUploaded(array $file): void
    {
        // $file is the full metadata array (see "File Metadata Object" below)
        $this->remember($file['path']);
    }

    #[On('file-removed')]
    public function onFileRemoved(array $file): void
    {
        $this->forget($file['path']);
    }

    #[On('files-cleared')]
    public function onFilesCleared(): void
    {
        $this->galleryPaths = [];
    }

    #[On('image-processed')]
    public function onImageProcessed(array $file, string $mode): void
    {
        $this->remember($file['path']);
        \Log::info('Edited image saved', ['path' => $file['path'], 'mode' => $mode]);
    }

    /** Add a path to the list (avoids duplicates). */
    protected function remember(string $path): void
    {
        if (! in_array($path, $this->galleryPaths, true)) {
            $this->galleryPaths[] = $path;
        }
    }

    /** Remove a path from the list. */
    protected function forget(string $path): void
    {
        $this->galleryPaths = array_values(array_diff($this->galleryPaths, [$path]));
    }
};
```

For class-based components (Livewire v3 / v4), the same works on a regular class:

```php
<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class PostForm extends Component
{
    public array $attachments = [];

    #[On('file-uploaded')]
    public function onFileUploaded(array $file): void
    {
        $this->attachments[] = $file['path'];
    }

    #[On('file-removed')]
    public function onFileRemoved(array $file): void
    {
        $this->attachments = array_diff($this->attachments, [$file['path']]);
    }

    public function render()
    {
        return view('livewire.post-form');
    }
}
```

> In Livewire v3 the attribute is `\Livewire\Attributes\On` as well; `#[On]` without arguments (parameter-less listeners) requires Livewire v3.5+ or v4+. Check the parameters each listener receives: `file-uploaded`/`file-removed` pass one `array`, `image-processed` passes two (`array $file`, `string $mode`).

### 🙉 Listening from JavaScript / Alpine

Plain JS:

```js
window.addEventListener('image-processed', (e) => {
    console.log('Saved:', e.detail.file.url);
    console.log('Mode:', e.detail.mode); // 'replace' | 'add'
});

window.addEventListener('file-uploaded', (e) => {
    console.log('New file:', e.detail.file.original_name);
});
```

Alpine (`$dispatch`):

```html
<div x-data @file-uploaded.window="console.log($event.detail.file.path)">
    ...
</div>

<script>
    document.addEventListener('file-uploaded', (e) => {
        // e.detail.file → metadata array
    });
</script>
```

### 📤 Dispatching the events yourself

You can also fire the same events from anywhere to reuse your listener logic (e.g. after restoring files on page load or simulating an upload):

```js
// From JS — payload must match the expected shape
window.dispatchEvent(new CustomEvent('file-uploaded', {
    detail: { file: { path: 'uploads/example.jpg', url: '/storage/uploads/example.jpg', /* … */ } },
}));
```

```php
// From another Livewire component
Livewire::dispatch('file-uploaded', [
    'file' => [
        'path' => 'uploads/example.jpg',
        'url' => '/storage/uploads/example.jpg',
        // …
    ],
]);
```

---

## 📐 File Metadata Object

```php
[
    'original_name' => 'photo.jpg',
    'path'          => 'uploads/uuid.webp',
    'disk'          => 'public',
    'url'           => 'https://app.test/storage/uploads/uuid.webp',
    'size'          => 204800,
    'size_human'    => '200 KB',
    'mime'          => 'image/webp',
    'extension'     => 'webp',
    'is_image'      => true,
    'is_pdf'        => false,
    'is_document'   => false,
    'is_existing'   => false,
    'thumbnail'     => 'uploads/uuid-thumb.webp', // only in image-library mode
    'thumbnail_url' => 'https://app.test/storage/uploads/uuid-thumb.webp',
    'uploaded_at'   => '2025-01-01T00:00:00+00:00',
]
```

> In image-library mode, the original image is replaced by the optimised variant and
> `path`/`url`/`mime`/`extension` reflect the **processed** file, with a `thumbnail`
> variant generated for the grid.

---

## 🌏 Localisation

Ships with **English** (`en`) and **Indonesian** (`id`).

All UI labels — including the fullscreen viewer's details panel — are translatable and are passed to the JavaScript layer (`LightboxManager`) via a `labels` object, so the panel renders fully localised strings.

---

## 🛠️ Technical Improvements (changelog highlights)

### Component assets never break after uploads (`@assets`)

Styles and scripts are rendered through Livewire's **`@assets`** directive (instead of a plain `@once` block). This is the officially recommended Livewire mechanism for component assets:

- Assets are **injected once into the page `<head>`** on the initial render.
- Each asset gets a **deterministic compile-time key**, deduplicating across multiple component instances and repeated requests.
- Livewire's **DOM morphing** during uploads/re-renders no longer removes or corrupts the `<style>`/`<script>` blocks — the CSS (and the Alpine components `daFileUpload` / `daImageEditor`) survives every state update.

### Fullscreen split file viewer (`LightboxManager`)

Renamed conceptually from a "lightbox" to a **file viewer**:

- Handles **all file types** — images (`<img>`), PDFs (`<iframe>`), and other documents (extension badge + placeholder).
- **Split layout**: responsive preview pane (left) + details pane (right, sticky header, scrollable).
- Edit button and zoom bar are **conditionally hidden** for non-image files.
- Thumbnail strip renders extension badges for documents.

### Fullscreen image editor

The image editor is now a **fullscreen overlay** (`position: fixed; z-index; flex column`) instead of an inline card:

- Toolbar (top) → canvas (flexible middle) → save bar (bottom).
- Canvas uses `flex: 1` and `max-height: 100%` so it fills the available viewport space.
- Added a dedicated **Close ✕** button in the toolbar (previously only "Cancel" in the save bar).

### Unified interaction model

Every card thumbnail — image **or** document — now opens the same fullscreen viewer, giving a consistent UX across galleries and attachment lists.

---

## 🧪 Testing

```bash
composer test
```

---

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## 🔒 License

MIT — see [LICENSE.md](LICENSE.md).

---

**developerawam** · info@developerawam.com
