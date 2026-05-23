<?php

namespace EvoDevOps\Base\Tests;

use EvoDevOps\Base\BaseServiceProvider;
use EvoDevOps\Base\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Inertia\ServiceProvider::class,
            \Laravel\Ai\AiServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Spatie\Tags\TagsServiceProvider::class,
            BaseServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('view.paths', array_merge(
            [__DIR__.'/Fixtures/views'],
            $app['config']->get('view.paths', [])
        ));
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
