<?php

namespace Developerawam\WireMedia;

use Developerawam\WireMedia\Components\FileUpload;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class WireMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wire-media.php',
            'wire-media'
        );
    }

    public function boot(): void
    {
        Livewire::component('wire-media', FileUpload::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wire-media');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'wire-media');

        $useImageLibrary = (bool) config('wire-media.use_image_library')
            && class_exists('Intervention\Image\ImageManager');

        AboutCommand::add('WireMedia', fn () => [
            'Vendor' => 'developerawam',
            'Features' => 'Upload · Crop · Zoom · Rotate · Flip · Lightbox · Dark Mode'.($useImageLibrary ? ' · Image Library' : ''),
        ]);

        $this->publishes([
            __DIR__.'/../config/wire-media.php' => config_path('wire-media.php'),
        ], 'wire-media-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/wire-media'),
        ], 'wire-media-views');

        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/wire-media/js'),
        ], 'wire-media-assets');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/wire-media'),
        ], 'wire-media-lang');
    }
}
