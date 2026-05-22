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
        }
    }
}
