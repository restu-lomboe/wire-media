<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    */
    'disk' => env('WIRE_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Default Storage Directory
    |--------------------------------------------------------------------------
    */
    'directory' => env('WIRE_MEDIA_DIR', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Default Max File Size (in MB)
    |--------------------------------------------------------------------------
    */
    'max_size_mb' => env('WIRE_MEDIA_MAX_SIZE_MB', 2),

    /*
    |--------------------------------------------------------------------------
    | Default Max Number of Files (0 = unlimited)
    |--------------------------------------------------------------------------
    */
    'max_files' => 0,

    /*
    |--------------------------------------------------------------------------
    | Accepted MIME Types (empty = all)
    |--------------------------------------------------------------------------
    | Example: ['image/jpeg', 'image/png', 'application/pdf']
    */
    'accepted_mime_types' => [],

    /*
    |--------------------------------------------------------------------------
    | Accepted Extensions (empty = all)
    |--------------------------------------------------------------------------
    | Example: ['jpg', 'png', 'pdf', 'docx']
    */
    'accepted_extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | UI Options
    |--------------------------------------------------------------------------
    */
    'show_preview' => true,
    'show_list' => true,
    'dark_mode' => false,
    'preview_only' => env('WIRE_MEDIA_PREVIEW_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Image Library Mode (Intervention Image)
    |--------------------------------------------------------------------------
    | Set ke `true` (atau `WIRE_MEDIA_USE_IMAGE_LIBRARY=true`) untuk mengaktifkan
    | pemrosesan gambar otomatis: resize, convert, optimize, thumbnail.
    | Membutuhkan `intervention/image:^4.0`. Default `false` — upload tersimpan
    | apa adanya, dan library mode hanya aktif bila diaktifkan di sini atau
    | lewat prop `:use-image-library="true"` pada komponen.
    */
    'use_image_library' => env('WIRE_MEDIA_USE_IMAGE_LIBRARY', false),

    /*
    |--------------------------------------------------------------------------
    | Auto Compress (lossless)
    |--------------------------------------------------------------------------
    | Jika `true`, upload gambar otomatis di-compress (re-encode) tanpa
    | mengurangi kualitas & tanpa resize. Tetap format asli, kualitas tinggi.
    | Membutuhkan `intervention/image`. Library mode punya prioritas jika aktif.
    */
    'auto_compress' => env('WIRE_MEDIA_AUTO_COMPRESS', false),

    /*
    |--------------------------------------------------------------------------
    | UI Auto Compress (editor slider)
    |--------------------------------------------------------------------------
    | Jika `true`, editor menampilkan tombol Compress dengan slider 0-100%
    | (metode sama seperti library). Bisa dipakai tanpa library mode
    | (fallback client-side via canvas quality).
    */
    'ui_auto_compress' => env('WIRE_MEDIA_UI_AUTO_COMPRESS', false),

    /*
    |--------------------------------------------------------------------------
    | Image Library Options (dipakai saat use_image_library = true)
    |--------------------------------------------------------------------------
    */
    'image_library' => [
        // Driver: 'gd' (default) atau 'imagick'
        'driver' => env('WIRE_MEDIA_IMAGE_DRIVER', 'gd'),

        // Otomatis turunkan resolusi gambar melebihi batas maksimal
        'auto_resize' => true,
        'max_width' => env('WIRE_MEDIA_IMAGE_MAX_WIDTH', 1920),
        'max_height' => env('WIRE_MEDIA_IMAGE_MAX_HEIGHT', 1080),

        // Kualitas output (1-100, hanya JPEG/WebP)
        'quality' => env('WIRE_MEDIA_IMAGE_QUALITY', 85),

        // Format output: 'webp' | 'jpg' | 'png' | null (biarkan sesuai aslinya)
        'format' => env('WIRE_MEDIA_IMAGE_FORMAT', 'webp'),

        // Thumbnail varian untuk tampilan grid
        'thumbnail' => [
            'enabled' => true,
            'width' => 400,
            'height' => 400,
            'quality' => 80,
        ],
    ],

];
