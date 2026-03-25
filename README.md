# WireMedia

[![Latest Version](https://img.shields.io/packagist/v/developerawam/wire-media.svg?style=flat-square)](https://packagist.org/packages/developerawam/wire-media)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-v3%20%7C%20v4-FB70A9?style=flat-square)](https://livewire.laravel.com)
[![License](https://img.shields.io/packagist/l/developerawam/wire-media.svg?style=flat-square)](LICENSE.md)

**Premium Livewire media upload & image editor for Laravel 12.**  
Upload, crop, zoom, rotate, flip, fullscreen lightbox — zero external JS dependencies.

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

---

## 🌟 Features

### Upload
- Drag & drop or click to browse
- Single or multiple files
- Extension, MIME type & file size validation
- Max file count enforcement

### Image Editor (canvas-based, zero deps)
Click ✏️ on any image to open the editor.

| Action       | How                                   |
|--------------|---------------------------------------|
| Zoom in/out  | Scroll wheel or toolbar buttons       |
| Pan          | Drag when not in crop mode            |
| Rotate       | CW/CCW 90° buttons                    |
| Flip         | Horizontal or vertical buttons        |
| Crop         | Enable → drag to select → Apply ✓    |
| Reset        | Reset all transforms                  |

Save options: **Replace original**, **Keep both**, or **Download locally**.

### Fullscreen Lightbox
Click ↔ on any image card or the preview panel.

- Prev / Next (buttons, `←→` keyboard, swipe on mobile)
- Scrollable thumbnail strip
- Zoom (buttons, scroll wheel, `+` / `-` / `0`)
- Download & Edit buttons in header
- `Esc` to close

### Detail Panel
Click any card thumbnail.
- Image preview (click → lightbox)
- PDF `<iframe>` embed
- Info table: name, type, size, disk, path, date, status
- Download button

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

```js
window.addEventListener('image-processed', (e) => {
    console.log('Saved:', e.detail.file.url);
    console.log('Mode:', e.detail.mode); // 'replace' | 'add'
});
```

---

## 📐 File Metadata Object

```php
[
    'original_name' => 'photo.jpg',
    'path'          => 'uploads/uuid.jpg',
    'disk'          => 'public',
    'url'           => 'https://app.test/storage/uploads/uuid.jpg',
    'size'          => 204800,
    'size_human'    => '200 KB',
    'mime'          => 'image/jpeg',
    'extension'     => 'jpg',
    'is_image'      => true,
    'is_pdf'        => false,
    'is_document'   => false,
    'is_existing'   => false,
    'uploaded_at'   => '2025-01-01T00:00:00+00:00',
]
```

---

## 🌏 Localisation

Ships with **English** (`en`) and **Indonesian** (`id`).

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
