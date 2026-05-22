<?php

namespace EvoDevOps\Base\Tests;

use EvoDevOps\Base\BaseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Inertia\ServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Spatie\Tags\TagsServiceProvider::class,
            BaseServiceProvider::class,
        ];
    }
}
