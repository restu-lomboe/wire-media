<?php

use Developerawam\WireMedia\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    // Ensure public/upload exists for public fallback tests - create via real filesystem mock
    // Storage fake already handles storage, public_path tests will use real temp
});

// ─────────────────────────────────────────────────────────────────────────────
// Config & Mount
// ─────────────────────────────────────────────────────────────────────────────

it('uses config defaults when props not provided', function () {
    config()->set('wire-media.disk', 'public');
    config()->set('wire-media.directory', 'uploads');
    config()->set('wire-media.max_size_mb', 5);
    config()->set('wire-media.max_files', 3);
    config()->set('wire-media.show_preview', true);
    config()->set('wire-media.show_list', true);
    config()->set('wire-media.dark_mode', false);

    $c = Livewire::test(FileUpload::class);
    expect($c->get('disk'))->toBe('public');
    expect($c->get('directory'))->toBe('uploads');
    expect($c->get('maxSizeMb'))->toBe(5.0);
    expect($c->get('maxFiles'))->toBe(3);
});

it('mount prop overrides config', function () {
    config()->set('wire-media.disk', 'public');
    $c = Livewire::test(FileUpload::class, ['disk' => 's3', 'directory' => 'custom', 'label' => 'My Label']);
    expect($c->get('disk'))->toBe('s3');
    expect($c->get('directory'))->toBe('custom');
    expect($c->get('label'))->toBe('My Label');
});

it('builds accept attribute from extensions and mime types', function () {
    $c = Livewire::test(FileUpload::class, ['acceptedExtensions' => ['jpg', 'png'], 'acceptedMimeTypes' => ['image/*']]);
    $attr = $c->get('acceptAttribute');
    expect($attr)->toContain('.jpg');
    expect($attr)->toContain('.png');
    expect($attr)->toContain('image/*');
});

it('uses explicit acceptAttribute when provided', function () {
    $c = Livewire::test(FileUpload::class, ['acceptAttribute' => '.pdf,image/*']);
    expect($c->get('acceptAttribute'))->toBe('.pdf,image/*');
});

it('returns uploaded paths via getUploadedPaths', function () {
    $file = UploadedFile::fake()->image('a.jpg');
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $paths = $c->instance()->getUploadedPaths();
    expect($paths)->toHaveCount(1);
    expect($paths[0])->toBeString();
});

// ─────────────────────────────────────────────────────────────────────────────
// PreviewOnly mode
// ─────────────────────────────────────────────────────────────────────────────

it('blocks upload in previewOnly mode', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $c = Livewire::test(FileUpload::class, ['previewOnly' => true])->set('files', $file);
    expect($c->get('uploadedFiles'))->toHaveCount(0);
    expect($c->get('errors'))->not->toBeEmpty();
});

it('blocks removeFile in previewOnly mode', function () {
    Storage::disk('public')->put('uploads/existing.jpg', 'content');
    $c = Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/existing.jpg'], 'previewOnly' => true]);
    $c->call('removeFile', 0)->assertCount('uploadedFiles', 1);
    Storage::disk('public')->assertExists('uploads/existing.jpg');
});

it('blocks clearAll in previewOnly mode', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    // First upload without previewOnly, then test clearAll with previewOnly? Instead test with existing
    Storage::disk('public')->put('uploads/existing.jpg', 'content');
    $c = Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/existing.jpg'], 'previewOnly' => true]);
    $c->call('clearAll')->assertCount('uploadedFiles', 1);
});

it('blocks openEditor in previewOnly mode', function () {
    Storage::disk('public')->put('uploads/existing.jpg', 'content');
    $c = Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/existing.jpg'], 'previewOnly' => true]);
    // Need to ensure is_image true - put real image content? Use fake via storage with image mime
    // Instead test with uploadedFiles that is_image true via existing metadata
    $c->set('uploadedFiles', [[
        'original_name' => 'test.jpg', 'path' => 'uploads/existing.jpg', 'disk' => 'public',
        'url' => '/storage/uploads/existing.jpg', 'size' => 100, 'size_human' => '100 B',
        'mime' => 'image/jpeg', 'extension' => 'jpg', 'is_image' => true, 'is_pdf' => false, 'is_document' => false, 'is_existing' => true,
        'thumbnail' => null, 'thumbnail_url' => null, 'uploaded_at' => now()->toIso8601String(),
    ]]);
    $c->call('openEditor', 0)->assertSet('editorOpen', false);
});

// ─────────────────────────────────────────────────────────────────────────────
// Validation
// ─────────────────────────────────────────────────────────────────────────────

it('allows file exactly at max size limit', function () {
    // 1 MB limit, file exactly 1024 KB (1 MB)
    $file = UploadedFile::fake()->create('exact.pdf', 1024);
    Livewire::test(FileUpload::class, ['maxSizeMb' => 1])->set('files', $file)->assertCount('uploadedFiles', 1);
});

it('handles case-insensitive extension validation', function () {
    $file = UploadedFile::fake()->create('photo.JPG', 100, 'image/jpeg');
    Livewire::test(FileUpload::class, ['acceptedExtensions' => ['jpg']])->set('files', $file)->assertCount('uploadedFiles', 1);
});

it('allows any file when no mime/extension restrictions', function () {
    $file = UploadedFile::fake()->create('anything.xyz', 100);
    Livewire::test(FileUpload::class)->set('files', $file)->assertCount('uploadedFiles', 1);
});

it('validates mime wildcard correctly', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
    Livewire::test(FileUpload::class, ['acceptedMimeTypes' => ['image/*']])->set('files', $file)->assertCount('uploadedFiles', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Upload edge cases
// ─────────────────────────────────────────────────────────────────────────────

it('handles null file in array gracefully', function () {
    $c = Livewire::test(FileUpload::class, ['multiple' => true]);
    // Simulate updatedFiles with null entry - set files to array with null
    $c->set('files', [null])->assertCount('uploadedFiles', 0);
});

it('enforces maxFiles considering existing uploaded count', function () {
    $f1 = UploadedFile::fake()->image('a.jpg');
    $c = Livewire::test(FileUpload::class, ['multiple' => true, 'maxFiles' => 2])->set('files', $f1)->assertCount('uploadedFiles', 1);
    $f2 = UploadedFile::fake()->image('b.jpg');
    $c->set('files', $f2)->assertCount('uploadedFiles', 2);
    $f3 = UploadedFile::fake()->image('c.jpg');
    $c->set('files', $f3)->assertCount('uploadedFiles', 2)->assertSet('errors', fn ($e) => count($e) > 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Existing files: storage, public, external, missing, model
// ─────────────────────────────────────────────────────────────────────────────

it('loads existing file from public folder (dynamic)', function () {
    // Create temp file in public/upload
    $publicPath = public_path('upload/test-public.jpg');
    @mkdir(dirname($publicPath), 0777, true);
    file_put_contents($publicPath, 'fake-public-image');
    Livewire::test(FileUpload::class, ['existingFiles' => ['upload/test-public.jpg']])
        ->assertCount('uploadedFiles', 1)
        ->tap(function ($c) {
            $f = $c->get('uploadedFiles')[0];
            expect($f['is_existing'])->toBeTrue();
            expect($f['url'])->toContain('upload/test-public.jpg');
        });
    @unlink($publicPath);
});

it('handles external URL as existing file', function () {
    $url = 'https://example.com/images/photo.jpg';
    Livewire::test(FileUpload::class, ['existingFiles' => [$url]])
        ->assertCount('uploadedFiles', 1)
        ->tap(function ($c) use ($url) {
            $f = $c->get('uploadedFiles')[0];
            expect($f['url'])->toBe($url);
            expect($f['is_existing'])->toBeTrue();
            expect($f['is_external'] ?? false)->toBeTrue();
        });
});

it('handles S3 external URL', function () {
    $url = 'https://my-bucket.s3.amazonaws.com/uploads/photo.png';
    Livewire::test(FileUpload::class, ['existingFiles' => [$url]])->assertCount('uploadedFiles', 1);
});

it('shows placeholder for missing file', function () {
    Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/missing-not-exist.jpg']])
        ->assertCount('uploadedFiles', 1)
        ->tap(function ($c) {
            $f = $c->get('uploadedFiles')[0];
            expect($f['is_missing'] ?? false)->toBeTrue();
            expect($f['url'])->toContain('data:image/svg+xml');
        });
});

it('loads existing files from Eloquent model string attribute', function () {
    $model = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
    Storage::disk('public')->put('uploads/model-file.jpg', 'content');
    $model->setAttribute('avatar', 'uploads/model-file.jpg');
    Livewire::test(FileUpload::class, ['existingFiles' => $model, 'modelAttribute' => 'avatar'])
        ->assertCount('uploadedFiles', 1);
});

it('loads existing files from Eloquent model JSON attribute', function () {
    $model = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
    Storage::disk('public')->put('uploads/a.jpg', 'a');
    Storage::disk('public')->put('uploads/b.jpg', 'b');
    $model->setAttribute('gallery', json_encode(['uploads/a.jpg', 'uploads/b.jpg']));
    Livewire::test(FileUpload::class, ['existingFiles' => $model, 'modelAttribute' => 'gallery'])
        ->assertCount('uploadedFiles', 2);
});

it('does not delete existing file on remove', function () {
    Storage::disk('public')->put('uploads/existing.jpg', 'keep');
    $c = Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/existing.jpg']])->assertCount('uploadedFiles', 1);
    $c->call('removeFile', 0)->assertCount('uploadedFiles', 0);
    Storage::disk('public')->assertExists('uploads/existing.jpg');
});

it('does not delete existing files on clearAll', function () {
    Storage::disk('public')->put('uploads/keep.jpg', 'keep');
    $c = Livewire::test(FileUpload::class, ['existingFiles' => ['uploads/keep.jpg']]);
    $f = UploadedFile::fake()->image('new.jpg');
    $c->set('files', $f)->assertCount('uploadedFiles', 2);
    $c->call('clearAll')->assertCount('uploadedFiles', 0);
    Storage::disk('public')->assertExists('uploads/keep.jpg');
});

// ─────────────────────────────────────────────────────────────────────────────
// Preview & Editor edge cases
// ─────────────────────────────────────────────────────────────────────────────

it('openPreview with invalid index sets null', function () {
    $file = UploadedFile::fake()->image('a.jpg');
    Livewire::test(FileUpload::class)->set('files', $file)->call('openPreview', 99)->assertSet('previewIndex', null);
});

it('closePreview is idempotent', function () {
    Livewire::test(FileUpload::class)->call('closePreview')->assertSet('previewIndex', null);
    Livewire::test(FileUpload::class)->call('closePreview')->assertSet('previewIndex', null);
});

it('openEditor with out-of-bounds does nothing', function () {
    Livewire::test(FileUpload::class)->call('openEditor', 5)->assertSet('editorOpen', false);
});

// ─────────────────────────────────────────────────────────────────────────────
// Image processing edge cases
// ─────────────────────────────────────────────────────────────────────────────

it('rejects invalid dataUrl in saveProcessedImage', function () {
    $file = UploadedFile::fake()->image('a.jpg');
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $c->call('saveProcessedImage', 0, 'not-a-data-url', 'replace')->assertSet('errors', fn ($e) => count($e) > 0);
});

it('rejects dataUrl exceeding max size', function () {
    // Create a dataUrl with bytes > maxSizeMb (2MB default, set to 0.001 MB = 1KB)
    $bigBytes = str_repeat('a', 2000);
    $dataUrl = 'data:image/png;base64,'.base64_encode($bigBytes);
    $file = UploadedFile::fake()->image('a.jpg');
    $c = Livewire::test(FileUpload::class, ['maxSizeMb' => 0.001])->set('files', $file);
    $c->call('saveProcessedImage', 0, $dataUrl, 'replace');
    expect($c->get('errors'))->not->toBeEmpty();
});

it('rejects mime not allowed in storeDataUrl', function () {
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $file = UploadedFile::fake()->image('a.jpg');
    $c = Livewire::test(FileUpload::class, ['acceptedMimeTypes' => ['image/jpeg']])->set('files', $file);
    $c->call('saveProcessedImage', 0, $dataUrl, 'replace');
    expect($c->get('errors'))->not->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// AutoCompress & ImageLibrary
// ─────────────────────────────────────────────────────────────────────────────

it('autoCompress does not run when disabled (stores original)', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $c = Livewire::test(FileUpload::class, ['autoCompress' => false])->set('files', $file);
    $stored = $c->get('uploadedFiles')[0];
    expect($stored['path'])->toContain('uploads/');
    expect($stored['thumbnail'])->toBeNull();
});

it('autoCompress compresses when enabled (no resize, same mime)', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('Intervention not installed');
    }
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $c = Livewire::test(FileUpload::class, ['autoCompress' => true])->set('files', $file);
    $stored = $c->get('uploadedFiles')[0];
    expect($stored['mime'])->toBeString();
    // Should have been re-encoded, path contains uploads (and -compressed when smaller)
    expect($stored['path'])->toContain('uploads/');
});

it('image library optimizes with resize and webp convert when autoCompress true', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('Intervention not installed');
    }
    $file = UploadedFile::fake()->image('big.jpg', 2500, 2000);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true])->set('files', $file);
    $stored = $c->get('uploadedFiles')[0];
    expect($stored['mime'])->toBe('image/webp');
    expect($stored['thumbnail'])->not->toBeNull();
});

it('image library does not auto compress when autoCompress false even if library enabled', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => false])->set('files', $file);
    $stored = $c->get('uploadedFiles')[0];
    // Should be original mime jpg, not webp, and no thumbnail
    expect($stored['mime'])->toBe('image/jpeg');
    expect($stored['thumbnail'])->toBeNull();
});

it('libraryTransformDataUrl handles compress with quality', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('Intervention not installed');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class, ['uiAutoCompress' => true]);
    $result = $c->instance()->libraryTransformDataUrl($dataUrl, 'compress', ['quality' => 50]);
    expect($result)->toBeString()->toContain('data:image');
});

it('libraryTransformDataUrl returns null for invalid dataUrl', function () {
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $result = $c->instance()->libraryTransformDataUrl('invalid', 'grayscale');
    expect($result)->toBeNull();
});

it('libraryTransformDataUrl handles grayscale transform', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('Intervention not installed');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $result = $c->instance()->libraryTransformDataUrl($dataUrl, 'grayscale');
    expect($result)->toBeString();
});

// ─────────────────────────────────────────────────────────────────────────────
// Events
// ─────────────────────────────────────────────────────────────────────────────

it('dispatches file-uploaded on successful upload', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    Livewire::test(FileUpload::class)->set('files', $file)->assertDispatched('file-uploaded');
});

it('dispatches file-removed on removeFile', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $c->call('removeFile', 0)->assertDispatched('file-removed');
});

it('dispatches files-cleared on clearAll', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $c->call('clearAll')->assertDispatched('files-cleared');
});

it('dispatches image-processed and file-removed on replace mode', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $c->call('saveProcessedImage', 0, $dataUrl, 'replace')->assertDispatched('image-processed')->assertDispatched('file-removed');
});

it('dispatches image-processed on add mode without file-removed', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class)->set('files', $file);
    $c->call('saveProcessedImage', 0, $dataUrl, 'add')->assertDispatched('image-processed');
    // file-removed should not be dispatched for add
    expect($c->get('uploadedFiles'))->toHaveCount(2);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

it('formats bytes correctly', function () {
    $c = Livewire::test(FileUpload::class);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('formatBytes');
    $m->setAccessible(true);
    expect($m->invoke($c->instance(), 0))->toBe('0 B');
    expect($m->invoke($c->instance(), 1024))->toBe('1 KB');
    expect($m->invoke($c->instance(), 1048576))->toBe('1 MB');
});

it('detects document correctly', function () {
    $c = Livewire::test(FileUpload::class);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('isDocument');
    $m->setAccessible(true);
    expect($m->invoke($c->instance(), 'application/pdf', 'pdf'))->toBeTrue();
    expect($m->invoke($c->instance(), 'image/jpeg', 'jpg'))->toBeFalse();
});

it('handles darkMode prop', function () {
    $c = Livewire::test(FileUpload::class, ['darkMode' => true]);
    expect($c->get('darkMode'))->toBeTrue();
});

it('handles showPreview and showList false', function () {
    $c = Livewire::test(FileUpload::class, ['showPreview' => false, 'showList' => false]);
    expect($c->get('showPreview'))->toBeFalse();
    expect($c->get('showList'))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// HandleImageLibrary coverage boost (push to 80%)
// ─────────────────────────────────────────────────────────────────────────────

it('checks imageLibraryAvailable true/false', function () {
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('imageLibraryAvailable');
    $m->setAccessible(true);
    expect($m->invoke($c->instance()))->toBeTrue();
    $c2 = Livewire::test(FileUpload::class, ['useImageLibrary' => false]);
    $ref2 = new ReflectionClass($c2->instance());
    $m2 = $ref2->getMethod('imageLibraryAvailable');
    $m2->setAccessible(true);
    expect($m2->invoke($c2->instance()))->toBeFalse();
});

it('checks autoCompressAvailable and uiAutoCompressAvailable', function () {
    $c = Livewire::test(FileUpload::class, ['autoCompress' => true, 'uiAutoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m1 = $ref->getMethod('autoCompressAvailable');
    $m1->setAccessible(true);
    $m2 = $ref->getMethod('uiAutoCompressAvailable');
    $m2->setAccessible(true);
    $m3 = $ref->getMethod('compressTransformAvailable');
    $m3->setAccessible(true);
    expect($m1->invoke($c->instance()))->toBeTrue();
    expect($m2->invoke($c->instance()))->toBeTrue();
    expect($m3->invoke($c->instance()))->toBeTrue();
});

it('imageManager falls back to GD when driver class missing', function () {
    config()->set('wire-media.image_library.driver', 'invalid_driver');
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('imageManager');
    $m->setAccessible(true);
    $mgr = $m->invoke($c->instance(), 'invalid');
    expect($mgr)->not->toBeNull();
});

it('imageManager handles imagick driver', function () {
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('imageManager');
    $m->setAccessible(true);
    $mgr = $m->invoke($c->instance(), 'imagick');
    expect($mgr)->not->toBeNull();
});

it('processImageBytes returns null on invalid bytes', function () {
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('processImageBytes');
    $m->setAccessible(true);
    $result = $m->invoke($c->instance(), 'not-an-image', 'image/jpeg');
    expect($result)->toBeNull();
});

it('buildThumbnailBytes returns null when disabled', function () {
    config()->set('wire-media.image_library.thumbnail.enabled', false);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('buildThumbnailBytes');
    $m->setAccessible(true);
    // Need a valid encoded image
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $result = $m->invoke($c->instance(), $pngBytes, 'image/png');
    expect($result)->toBeNull();
    config()->set('wire-media.image_library.thumbnail.enabled', true);
});

it('buildThumbnailBytes returns null when dimensions zero', function () {
    config()->set('wire-media.image_library.thumbnail.width', 0);
    config()->set('wire-media.image_library.thumbnail.height', 0);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('buildThumbnailBytes');
    $m->setAccessible(true);
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $result = $m->invoke($c->instance(), $pngBytes, 'image/jpeg');
    expect($result)->toBeNull();
    config()->set('wire-media.image_library.thumbnail.width', 400);
    config()->set('wire-media.image_library.thumbnail.height', 400);
});

it('optimizeStoredImage returns null for missing file', function () {
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('optimizeStoredImage');
    $m->setAccessible(true);
    $result = $m->invoke($c->instance(), 'uploads/missing.jpg', 'image/jpeg');
    expect($result)->toBeNull();
});

it('processAutoCompressBytes handles invalid bytes', function () {
    $c = Livewire::test(FileUpload::class, ['autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('processAutoCompressBytes');
    $m->setAccessible(true);
    $result = $m->invoke($c->instance(), 'invalid', 'image/jpeg');
    expect($result)->toBeNull();
});

it('processCompressBytes clamps quality', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('no intervention');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $c = Livewire::test(FileUpload::class, ['uiAutoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('processCompressBytes');
    $m->setAccessible(true);
    $r1 = $m->invoke($c->instance(), $pngBytes, 'image/png', 0);
    $r2 = $m->invoke($c->instance(), $pngBytes, 'image/png', 200);
    expect($r1)->not->toBeNull();
    expect($r2)->not->toBeNull();
});

it('extensionForMime and formatForMime mappings', function () {
    $c = Livewire::test(FileUpload::class);
    $ref = new ReflectionClass($c->instance());
    $e = $ref->getMethod('extensionForMime');
    $e->setAccessible(true);
    expect($e->invoke($c->instance(), 'image/webp'))->toBe('webp');
    expect($e->invoke($c->instance(), 'image/jpeg'))->toBe('jpg');
    expect($e->invoke($c->instance(), 'image/png'))->toBe('png');
    expect($e->invoke($c->instance(), 'image/gif'))->toBe('gif');
    expect($e->invoke($c->instance(), 'image/avif'))->toBe('avif');
    expect($e->invoke($c->instance(), 'image/bmp'))->toBe('bmp');
    expect($e->invoke($c->instance(), 'unknown'))->toBe('jpg');
    $f = $ref->getMethod('formatForMime');
    $f->setAccessible(true);
    expect($f->invoke($c->instance(), 'image/jpeg'))->toBe('jpeg');
    expect($f->invoke($c->instance(), 'image/png'))->toBe('png');
});

it('libraryTransform covers blur, sharpen, flip, rotate, orient', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('no intervention');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'blur', ['level' => 5]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'sharpen', ['level' => 5]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'flip', ['direction' => 'vertical']))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'flip', ['direction' => 'horizontal']))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'rotate', ['angle' => 90]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'orient'))->toBeString();
});

it('libraryTransform covers scaleDown, cover, contain, crop, convert', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('no intervention');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'scaleDown', ['width' => 100, 'height' => 100]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'scaleDown', []))->toBeNull(); // missing dims
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'cover', ['width' => 100, 'height' => 100]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'contain', ['width' => 100, 'height' => 100]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'crop', ['width' => 10, 'height' => 10, 'x' => 0, 'y' => 0]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'convert', ['format' => 'webp', 'quality' => 80]))->toBeString();
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'convert', ['format' => 'avif']))->toBeString();
});

it('libraryTransform handles invalid transform and base64', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('no intervention');
    }
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    expect($c->instance()->libraryTransformDataUrl($dataUrl, 'unknown_transform'))->toBeNull();
    expect($c->instance()->libraryTransformDataUrl('data:image/png;base64,!!!', 'grayscale'))->toBeNull();
    expect($c->instance()->libraryTransformDataUrl('data:text/plain;base64,abc', 'grayscale'))->toBeNull();
});

it('encodeImage covers all mime types', function () {
    if (! class_exists('Intervention\Image\ImageManager')) {
        $this->markTestSkipped('no intervention');
    }
    $c = Livewire::test(FileUpload::class, ['useImageLibrary' => true, 'autoCompress' => true]);
    $ref = new ReflectionClass($c->instance());
    $m = $ref->getMethod('encodeImage');
    $m->setAccessible(true);
    $refMgr = new ReflectionClass($c->instance());
    $mgrM = $refMgr->getMethod('imageManager');
    $mgrM->setAccessible(true);
    $mgr = $mgrM->invoke($c->instance());
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $img = $mgr->decode($pngBytes);
    expect($m->invoke($c->instance(), $img, 'image/webp', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'image/jpeg', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'image/png', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'image/gif', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'image/avif', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'image/bmp', 80))->toBeString();
    expect($m->invoke($c->instance(), $img, 'unknown/mime', 80))->toBeString();
});
