# Changelog

## [Unreleased]

### Fixed

- Lightbox zoom (buttons, keyboard, ctrl+wheel) now actually transforms the image — the
  viewer `<img>` was missing its `#da-lb-img` id that the zoom logic selects.
- `use_image_library` now defaults to `false` — library mode is opt-in and does **not**
  activate just because `intervention/image` is installed.

### Added

- **Library-mode editor transforms**: when image-library mode is enabled, the image editor
  toolbar shows server-side Intervention transforms — grayscale, blur, sharpen, and one-click
  convert to WebP / JPG / PNG (`libraryTransformDataUrl` on `HandleImageLibrary`).
- **Live filter preview sliders**: grayscale, blur, and sharpen now open a 0–100% range slider
  with Apply / Cancel buttons. The canvas previews the effect live as the slider moves
  (`ctx.filter` for grayscale/blur, a 3×3 convolution for sharpen); **Apply** bakes the result
  into the edited image, **Cancel** reverts to the pre-preview state. Convert remains a
  one-click server-side transform.

## [2.1.0] — 2026-08-13

### Added

- **Image Library mode** (optional): automatic image processing powered by Intervention Image
  - `use_image_library` config flag (`WIRE_MEDIA_USE_IMAGE_LIBRARY=true`)
  - Auto resize (`scaleDown`), format convert, quality compression
  - WebP thumbnail variant generated for the grid (`thumbnail` / `thumbnail_url` keys)
  - New `image_library` config block: driver, max dimensions, quality, format, thumbnail
  - Automatic fallback to default mode when `intervention/image` is not installed
  - `HandleImageLibrary` concern (`src/Concerns/HandleImageLibrary.php`)
- Optimised-badge UI on grid cards for library-processed images
- Thumbnails are deleted alongside their original file (upload, edit, remove, clear)

## [2.0.0] — 2026-08-10

### Added

- **Fullscreen Lightbox** with carousel (prev/next), thumbnail strip, keyboard & swipe navigation
- **Image Editor** — canvas-based (zero external deps):
  - Zoom in/out/reset (scroll wheel, buttons)
  - Rotate CW/CCW 90°
  - Flip horizontal/vertical
  - Free-draw crop with rule-of-thirds grid and corner handles
  - Pan mode (drag when not in crop mode)
- **Crop result modes**: replace original on server, keep both, or download locally
- **Dark mode** — auto OS detection + forced via `:dark-mode` prop
- `HandleImageProcessing` concern for server-side base64 → PNG storage
- `openEditor()` / `closeEditor()` Livewire methods
- `saveProcessedImage(index, dataUrl, mode)` Livewire method
- Browser event `image-processed`
- `ImageProcessor` class (`resources/js/image-processor.js`)
- `LightboxManager` class (`resources/js/lightbox-manager.js`)
- New publishable tag `wire-media-assets`
- New prop `dark-mode`
- Full EN/ID translations for all new UI strings
- 10 new Pest tests covering editor open/close and server save modes

## [1.0.0] — 2025-03-01

### Added

- Initial release: upload, multiple, extension/MIME validation, max size, drag & drop
- Edit mode: load existing files from path array, metadata array, or Eloquent model
- Detail panel: image preview, PDF embed, info table, download
- `removeFile()` / `clearAll()` with storage deletion
- EN/ID translations
