<?php

namespace Developerawam\WireMedia\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\BmpEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;

trait HandleImageLibrary
{
    /**
     * Whether the Intervention Image library mode is enabled and usable.
     */
    protected function imageLibraryAvailable(): bool
    {
        $enabled = isset($this->useImageLibrary)
            ? (bool) $this->useImageLibrary
            : (bool) config('wire-media.use_image_library', false);

        return $enabled && class_exists('Intervention\Image\ImageManager');
    }

    /**
     * Build an Intervention Image manager for the configured driver.
     */
    protected function imageManager(string $driver = ''): mixed
    {
        $driver = $driver ?: (string) config('wire-media.image_library.driver', 'gd');

        $driverClass = $driver === 'imagick'
            ? 'Intervention\Image\Drivers\Imagick\Driver'
            : 'Intervention\Image\Drivers\Gd\Driver';

        if (! class_exists($driverClass)) {
            $driverClass = 'Intervention\Image\Drivers\Gd\Driver';
        }

        $imageManagerClass = 'Intervention\Image\ImageManager';

        return new $imageManagerClass(new $driverClass);
    }

    /**
     * Optimize raw image bytes with Intervention Image: scale down to the
     * configured maximum dimensions, convert to the configured output format,
     * apply quality, and build a WebP thumbnail variant.
     *
     * @param  string  $sourceMime  Original MIME type (used when no format is configured)
     * @return array{bytes: string, mime: string, thumbnail_bytes: ?string}|null
     */
    protected function processImageBytes(string $bytes, string $sourceMime): ?array
    {
        $options = (array) config('wire-media.image_library', []);

        try {
            $image = $this->imageManager()->decode($bytes);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $maxWidth = (int) ($options['max_width'] ?? 1920);
        $maxHeight = (int) ($options['max_height'] ?? 1080);

        if (($options['auto_resize'] ?? true) && $maxWidth > 0 && $maxHeight > 0) {
            $image->scaleDown($maxWidth, $maxHeight);
        }

        $format = ($options['format'] ?? null) ? strtolower((string) $options['format']) : null;
        $quality = (int) ($options['quality'] ?? 85);

        $targetMime = $format ? match ($format) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => $sourceMime,
        } : $sourceMime;

        try {
            $encoded = $this->encodeImage($image, $targetMime, $quality);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return [
            'bytes' => $encoded,
            'mime' => $targetMime,
            'thumbnail_bytes' => $this->buildThumbnailBytes($encoded, $targetMime),
        ];
    }

    /**
     * Encode an image to the given MIME type using Intervention Image encoders.
     */
    protected function encodeImage(mixed $image, string $mime, int $quality): string
    {
        return (string) $image->encode(match ($mime) {
            'image/webp' => new WebpEncoder($quality),
            'image/jpeg' => new JpegEncoder($quality),
            'image/png' => new PngEncoder,
            'image/gif' => new GifEncoder,
            'image/avif' => new AvifEncoder($quality),
            'image/bmp' => new BmpEncoder,
            default => new AutoEncoder,
        });
    }

    /**
     * Build a WebP/PNG thumbnail variant of the processed image bytes.
     */
    protected function buildThumbnailBytes(string $encoded, string $targetMime): ?string
    {
        $options = (array) config('wire-media.image_library.thumbnail', []);

        if (! ($options['enabled'] ?? true)) {
            return null;
        }

        $width = (int) ($options['width'] ?? 400);
        $height = (int) ($options['height'] ?? 400);
        $quality = (int) ($options['quality'] ?? 80);

        if ($width <= 0 && $height <= 0) {
            return null;
        }

        try {
            $thumb = $this->imageManager()->decode($encoded);

            if ($width > 0 && $height > 0) {
                $thumb->cover($width, $height);
            } elseif ($height > 0) {
                $thumb->scaleDown(height: $height);
            } else {
                $thumb->scaleDown($width);
            }

            return $targetMime === 'image/png' || $targetMime === 'image/gif'
                ? (string) $thumb->encode(new PngEncoder)
                : (string) $thumb->encode(new WebpEncoder($quality));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Optimize a stored raw image in place: write the processed image + thumbnail
     * variant and remove the original file, returning the new metadata keys.
     *
     * @return array{path: string, mime: string, extension: string, thumbnail: ?string, thumbnail_url: ?string}|null
     */
    protected function optimizeStoredImage(string $path, string $sourceMime): ?array
    {
        $bytes = Storage::disk($this->disk)->get($path);

        if ($bytes === null) {
            return null;
        }

        $processed = $this->processImageBytes($bytes, $sourceMime);

        if ($processed === null) {
            return null;
        }

        $base = Str::uuid()->toString().'-optimized';
        $extension = $this->extensionForMime($processed['mime']);
        $optimizedPath = rtrim($this->directory, '/').'/'.$base.'.'.$extension;

        Storage::disk($this->disk)->put($optimizedPath, $processed['bytes']);

        $thumbnailPath = null;
        $thumbnailUrl = null;

        if ($processed['thumbnail_bytes'] !== null) {
            $thumbnailPath = rtrim($this->directory, '/').'/'.$base.'-thumb.webp';
            Storage::disk($this->disk)->put($thumbnailPath, $processed['thumbnail_bytes']);
            $thumbnailUrl = Storage::disk($this->disk)->url($thumbnailPath);
        }

        if ($optimizedPath !== $path && Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }

        return [
            'path' => $optimizedPath,
            'mime' => $processed['mime'],
            'extension' => $extension,
            'thumbnail' => $thumbnailPath,
            'thumbnail_url' => $thumbnailUrl,
        ];
    }

    /**
     * Map an image MIME type back to its file extension.
     */
    protected function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
            default => 'jpg',
        };
    }

    /**
     * Apply an Intervention Image transform to an editor data URL and return the
     * transformed data URL. Used by the library-mode editor toolbar buttons.
     *
     * Supported $transform values (Laravel docs / Intervention v4):
     *  - grayscale
     *  - blur          params: level (default 5)
     *  - sharpen       params: level (default 10)
     *  - flip          params: direction (horizontal|vertical)
     *  - rotate        params: angle (default 90), background
     *  - orient
     *  - scaleDown     params: width, height (optional)
     *  - cover         params: width, height (default 800)
     *  - contain       params: width, height (default 800), background
     *  - crop          params: width, height, x, y
     *  - resize        params: width, height (optional, uses scaleDown)
     *  - convert       params: format (webp|jpeg|png|gif|avif|bmp), quality (default 85)
     *
     * @return string|null The new data URL, or null when unavailable/invalid.
     */
    public function libraryTransformDataUrl(string $dataUrl, string $transform, ?array $params = null): ?string
    {
        if (! $this->imageLibraryAvailable()) {
            return null;
        }

        if (! preg_match('#^data:(image/(jpeg|png|webp|gif));base64,(.*)$#s', $dataUrl, $m)) {
            return null;
        }

        $sourceMime = $m[1];

        $bytes = base64_decode($m[3], true);

        if ($bytes === false) {
            return null;
        }

        try {
            $image = $this->imageManager()->decode($bytes);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $params = array_merge([
            'format' => null,
            'quality' => 85,
            'level' => null,
            'direction' => 'horizontal',
            'angle' => 90,
            'width' => null,
            'height' => null,
            'background' => null,
            'x' => 0,
            'y' => 0,
        ], $params ?? []);

        try {
            switch ($transform) {
                case 'grayscale':
                    $image->grayscale();
                    break;

                case 'blur':
                    $image->blur((int) $params['level']);
                    break;

                case 'sharpen':
                    $image->sharpen((int) $params['level']);
                    break;

                case 'flip':
                    $image->flip(strtolower((string) $params['direction']) === 'vertical'
                        ? Direction::VERTICAL
                        : Direction::HORIZONTAL);
                    break;

                case 'rotate':
                    $image->rotate((float) $params['angle'], $params['background']);
                    break;

                case 'orient':
                    $image->orient();
                    break;

                case 'resize':
                case 'scaleDown':
                    if ($params['width'] === null && $params['height'] === null) {
                        return null;
                    }

                    $image->scaleDown(
                        $params['width'] !== null ? (int) $params['width'] : null,
                        $params['height'] !== null ? (int) $params['height'] : null,
                    );
                    break;

                case 'convert':
                    // Re-encode only — no geometric transform.
                    break;

                case 'cover':
                    $image->cover(
                        (int) ($params['width'] ?? 800),
                        (int) ($params['height'] ?? 800),
                    );
                    break;

                case 'contain':
                    $image->contain(
                        (int) ($params['width'] ?? 800),
                        (int) ($params['height'] ?? 800),
                        $params['background'],
                    );
                    break;

                case 'crop':
                    $image->crop(
                        (int) $params['width'],
                        (int) $params['height'],
                        (int) $params['x'],
                        (int) $params['y'],
                    );
                    break;

                default:
                    return null;
            }

            $format = $params['format'] !== null
                ? strtolower((string) $params['format'])
                : $this->formatForMime($sourceMime);

            $targetMime = match ($format) {
                'webp' => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'avif' => 'image/avif',
                'bmp' => 'image/bmp',
                default => $sourceMime,
            };

            $quality = (int) $params['quality'];
            $encoded = $this->encodeImage($image, $targetMime, $quality);

            return 'data:'.$targetMime.';base64,'.base64_encode($encoded);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Map a source MIME to the default output format slug used by transform().
     */
    protected function formatForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpeg',
        };
    }
}
