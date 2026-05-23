<?php

namespace EvoDevOps\Base;

use EvoDevOps\Base\Auth\DefaultUserResolver;
use EvoDevOps\Base\Auth\SpatieAdminGate;
use EvoDevOps\Base\Console\Commands\Ai\AiStreamSmokeTest;
use EvoDevOps\Base\Console\Commands\AiProbeCommand;
use EvoDevOps\Base\Console\Commands\AiSmokeTest;
use EvoDevOps\Base\Console\Commands\DoctorCommand;
use EvoDevOps\Base\Console\Commands\FontsSelfHost;
use EvoDevOps\Base\Console\Commands\OntologyCompileCommand;
use EvoDevOps\Base\Console\Commands\PromoteUserCommand;
use EvoDevOps\Base\Contracts\AdminGate;
use EvoDevOps\Base\Contracts\UserResolver;
use EvoDevOps\Base\Http\Middleware\EnsureExampleEnabled;
use EvoDevOps\Base\Http\Middleware\RequireAdmin;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evodevops.php', 'evo');
        $this->mergeConfigFrom(__DIR__.'/../config/evodevops-ai.php', 'ai');

        $this->app->singleton(AdminGate::class, SpatieAdminGate::class);
        $this->app->singleton(UserResolver::class, DefaultUserResolver::class);
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('example', EnsureExampleEnabled::class);
        $router->aliasMiddleware('evo.admin', RequireAdmin::class);

        $router->middlewareGroup('evo', (array) config('evo.route.middleware', ['web']));
        Route::middleware('evo')->group(__DIR__.'/../routes/web.php');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
                AiProbeCommand::class,
                AiSmokeTest::class,
                AiStreamSmokeTest::class,
                FontsSelfHost::class,
                OntologyCompileCommand::class,
                PromoteUserCommand::class,
            ]);

            $this->registerPublishables();
        }
    }

    private function registerPublishables(): void
    {
        $this->publishes([
            __DIR__.'/../config/evodevops.php' => config_path('evodevops.php'),
            __DIR__.'/../config/evodevops-ai.php' => config_path('evodevops-ai.php'),
        ], 'evodevops-base-config');

        $this->publishes([
            __DIR__.'/../resources/js/blocks' => resource_path('js/blocks'),
            __DIR__.'/../resources/js/pages/evodevops' => resource_path('js/pages/evodevops'),
            __DIR__.'/../resources/js/hooks' => resource_path('js/hooks'),
            __DIR__.'/../resources/js/components' => resource_path('js/components'),
            __DIR__.'/../resources/js/providers' => resource_path('js/providers'),
            __DIR__.'/../resources/js/layouts' => resource_path('js/layouts'),
            __DIR__.'/../resources/js/config' => resource_path('js/config'),
            __DIR__.'/../resources/js/types/layout.ts' => resource_path('js/types/layout.ts'),
            __DIR__.'/../resources/js/lib/appearance.ts' => resource_path('js/lib/appearance.ts'),
            __DIR__.'/../resources/js/lib/platform.ts' => resource_path('js/lib/platform.ts'),
        ], 'evodevops-base-frontend');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'evodevops-base-migrations');

        $this->publishes([
            __DIR__.'/../patches' => base_path('patches'),
        ], 'evodevops-base-patches');
    }
}
