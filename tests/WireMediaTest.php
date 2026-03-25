<?php

use Developerawam\WireMedia\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

it('renders the file upload component', function () {
    Livewire::test(FileUpload::class)
        ->assertStatus(200)
        ->assertSee('Drag & drop');
});

it('accepts a single file upload', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    Livewire::test(FileUpload::class, ['maxSizeMb' => 2])
        ->set('files', $file)
        ->assertSet('errors', [])
        ->assertCount('uploadedFiles', 1);
});

it('rejects files that exceed the max size', function () {
    // Create a fake file larger than 1 MB limit
    $file = UploadedFile::fake()->create('bigfile.pdf', 2048); // 2 MB

    Livewire::test(FileUpload::class, ['maxSizeMb' => 1])
        ->set('files', $file)
        ->assertCount('uploadedFiles', 0);
});

it('rejects files with disallowed extensions', function () {
    $file = UploadedFile::fake()->create('virus.exe', 10);

    Livewire::test(FileUpload::class, [
        'acceptedExtensions' => ['jpg', 'png', 'pdf'],
    ])
        ->set('files', $file)
        ->assertCount('uploadedFiles', 0);
});

it('accepts multiple files', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.png'),
    ];

    Livewire::test(FileUpload::class, ['multiple' => true])
        ->set('files', $files)
        ->assertCount('uploadedFiles', 2);
});

it('enforces the max files limit', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
        UploadedFile::fake()->image('c.jpg'),
    ];

    Livewire::test(FileUpload::class, ['multiple' => true, 'maxFiles' => 2])
        ->set('files', $files)
        ->assertCount('uploadedFiles', 0); // blocked entirely
});

it('can remove an uploaded file', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $component = Livewire::test(FileUpload::class)
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1);

    $component->call('removeFile', 0)
        ->assertCount('uploadedFiles', 0);
});

it('can clear all uploaded files and deletes them from storage', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ];

    $component = Livewire::test(FileUpload::class, ['multiple' => true])
        ->set('files', $files)
        ->assertCount('uploadedFiles', 2);

    $paths = collect($component->get('uploadedFiles'))->pluck('path')->all();

    $component->call('clearAll')
        ->assertCount('uploadedFiles', 0);

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('supports wildcard MIME types', function () {
    $file = UploadedFile::fake()->image('photo.webp');

    Livewire::test(FileUpload::class, ['acceptedMimeTypes' => ['image/*']])
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1)
        ->assertSet('errors', []);
});

// ── Edit mode: existing files ─────────────────────────────────────────────

it('loads existing files from an array of path strings', function () {
    // Put a fake file on disk so the path resolves
    Storage::disk('public')->put('uploads/existing.jpg', 'fake-image-content');

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class, [
        'existingFiles' => ['uploads/existing.jpg'],
    ])
        ->assertCount('uploadedFiles', 1)
        ->tap(function ($component) {
            $file = $component->get('uploadedFiles')[0];
            expect($file['is_existing'])->toBeTrue();
            expect($file['path'])->toBe('uploads/existing.jpg');
        });
});

it('loads existing files from an array of metadata arrays', function () {
    Storage::disk('public')->put('uploads/doc.pdf', 'fake-pdf');

    $existing = [[
        'original_name' => 'doc.pdf',
        'path'          => 'uploads/doc.pdf',
        'disk'          => 'public',
        'url'           => '/storage/uploads/doc.pdf',
        'size'          => 1024,
        'size_human'    => '1 KB',
        'mime'          => 'application/pdf',
        'extension'     => 'pdf',
        'is_image'      => false,
        'is_document'   => true,
        'uploaded_at'   => now()->toIso8601String(),
    ]];

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class, [
        'existingFiles' => $existing,
    ])
        ->assertCount('uploadedFiles', 1)
        ->tap(function ($component) {
            $file = $component->get('uploadedFiles')[0];
            expect($file['is_existing'])->toBeTrue();
            expect($file['is_pdf'])->toBeTrue();
        });
});

it('opens and closes the preview panel', function () {
    $file = UploadedFile::fake()->image('preview.jpg');

    $component = Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1);

    $component->call('openPreview', 0)
        ->assertSet('previewIndex', 0);

    $component->call('closePreview')
        ->assertSet('previewIndex', null);
});

it('clears previewIndex when the previewed file is removed', function () {
    $file = UploadedFile::fake()->image('remove-me.jpg');

    $component = Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->call('openPreview', 0)
        ->assertSet('previewIndex', 0);

    $component->call('removeFile', 0)
        ->assertSet('previewIndex', null);
});

it('adjusts previewIndex after removing an earlier file', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
        UploadedFile::fake()->image('c.jpg'),
    ];

    $component = Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class, ['multiple' => true])
        ->set('files', $files)
        ->call('openPreview', 2)   // previewing "c.jpg" at index 2
        ->assertSet('previewIndex', 2);

    $component->call('removeFile', 0)  // remove "a.jpg"
        ->assertSet('previewIndex', 1); // index shifts down by 1
});

// ── Image Editor ───────────────────────────────────────────────────────────

it('can open the image editor for an image file', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1)
        ->call('openEditor', 0)
        ->assertSet('editorOpen', true)
        ->assertSet('editorIndex', 0);
});

it('cannot open the editor for a non-image file', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->call('openEditor', 0)
        ->assertSet('editorOpen', false);
});

it('closes the editor', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->call('openEditor', 0)
        ->assertSet('editorOpen', true)
        ->call('closeEditor')
        ->assertSet('editorOpen', false)
        ->assertSet('editorIndex', null);
});

it('saves a processed image to server in replace mode', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    // Generate a tiny valid 1x1 PNG data URL
    $pngBytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    );
    $dataUrl = 'data:image/png;base64,' . base64_encode($pngBytes);

    $component = Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1);

    $oldPath = $component->get('uploadedFiles')[0]['path'];

    $component->call('saveProcessedImage', 0, $dataUrl, 'replace')
        ->assertCount('uploadedFiles', 1)
        ->assertSet('editorOpen', false);

    // Old file should be gone, new file should be different
    Storage::disk('public')->assertMissing($oldPath);
    $newPath = $component->get('uploadedFiles')[0]['path'];
    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

it('saves a processed image to server in add mode', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $pngBytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    );
    $dataUrl = 'data:image/png;base64,' . base64_encode($pngBytes);

    Livewire::test(\Developerawam\WireMedia\Components\FileUpload::class)
        ->set('files', $file)
        ->assertCount('uploadedFiles', 1)
        ->call('saveProcessedImage', 0, $dataUrl, 'add')
        ->assertCount('uploadedFiles', 2);
});
