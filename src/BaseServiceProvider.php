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
use EvoDevOps\Base\Support\OntologyRegistry;
use EvoDevOps\Base\Http\Middleware\EnsureExampleEnabled;
use EvoDevOps\Base\Http\Middleware\RequireAdmin;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider
{
    /**
     * EVO_BASE_EXAMPLE_* flags that gate per-feature route files under routes/features/.
     * Order matters only for route:list display; each file is independent.
     */
    private const FEATURE_ROUTES = [
        'marketing_pages',
        'contact_ai',
        'admin_inbox',
        'prd_studio',
        'thread_studio',
        'voice_input',
        'ai_text_field',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evodevops.php', 'evo');
        $this->mergeConfigFrom(__DIR__.'/../config/evodevops-ai.php', 'ai');

        $this->app->singleton(AdminGate::class, SpatieAdminGate::class);
        $this->app->singleton(UserResolver::class, DefaultUserResolver::class);
        $this->app->singleton(OntologyRegistry::class);
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('example', EnsureExampleEnabled::class);
        $router->aliasMiddleware('evo.admin', RequireAdmin::class);

        $router->middlewareGroup('evo', (array) config('evo.base.route.middleware', ['web']));

        // Per-feature route files — each only loads when its EVO_BASE_EXAMPLE_*
        // flag is true. With all flags default-false, installing the package
        // adds zero routes to the host's route:list (the "zero routes on
        // install" principle).
        foreach (self::FEATURE_ROUTES as $feature) {
            if (config("evo.base.examples.{$feature}")) {
                Route::middleware('evo')->group(__DIR__."/../routes/features/{$feature}.php");
            }
        }

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
            __DIR__.'/../resources/js/types/evodevops.d.ts' => resource_path('js/types/evodevops.d.ts'),
            __DIR__.'/../resources/js/lib/appearance.ts' => resource_path('js/lib/appearance.ts'),
            __DIR__.'/../resources/js/lib/platform.ts' => resource_path('js/lib/platform.ts'),
        ], 'evodevops-base-frontend');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'evodevops-base-migrations');

        $this->publishes([
            __DIR__.'/../patches' => base_path('patches'),
        ], 'evodevops-base-patches');

        $this->publishes([
            __DIR__.'/../stubs/package-json-additions.json' => base_path('package-json-additions.evodevops.json'),
        ], 'evodevops-base-npm');
    }
}
