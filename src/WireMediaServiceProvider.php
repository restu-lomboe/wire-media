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
        // Tambahkan ini — share path JS ke semua view
        \Illuminate\Support\Facades\View::share(
            'wireMediaJsPath',
            __DIR__ . '/../resources/js'
        );

        Livewire::component('wire-media', FileUpload::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wire-media');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'wire-media');

        AboutCommand::add('WireMedia', fn () => [
            'Version'  => '2.0.0',
            'Vendor'   => 'developerawam',
            'Features' => 'Upload · Crop · Zoom · Rotate · Flip · Lightbox · Dark Mode',
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
