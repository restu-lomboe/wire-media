<?php

namespace Developerawam\WireMedia\Tests;

use Developerawam\WireMedia\WireMediaServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireMediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => '/storage',
        ]);

        $app['config']->set('livewire.temporary_file_upload.disk', 'public');
    }
}
