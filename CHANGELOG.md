# Changelog

## [2.0.0] — 2025-03-24

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
