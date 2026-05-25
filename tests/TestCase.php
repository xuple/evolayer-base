<?php

namespace Xuple\EvoLayer\Base\Tests;

use Illuminate\Foundation\Application;
use Inertia\ServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Tags\TagsServiceProvider;
use Xuple\EvoLayer\Base\BaseServiceProvider;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            AiServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            MediaLibraryServiceProvider::class,
            TagsServiceProvider::class,
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

        // Enable every EvoLayer Base feature for the package's own tests.
        // The package's production default is `false` (opt-in per the "doesn't
        // come with features wired in" principle); tests need them all on to
        // exercise the full surface.
        $app['config']->set('evolayer.base.examples', [
            'thread_studio' => true,
            'prd_studio' => true,
            'admin_inbox' => true,
            'contact_ai' => true,
            'voice_input' => true,
            'ai_text_field' => true,
            'marketing_pages' => true,
        ]);
        $app['config']->set('evolayer.base.features', [
            'contact_attachments' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
