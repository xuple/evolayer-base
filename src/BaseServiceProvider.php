<?php

namespace Xuple\EvoLayer\Base;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Xuple\EvoLayer\Base\Auth\DefaultUserResolver;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Console\Commands\Ai\AiStreamSmokeTest;
use Xuple\EvoLayer\Base\Console\Commands\AiProbeCommand;
use Xuple\EvoLayer\Base\Console\Commands\AiSmokeTest;
use Xuple\EvoLayer\Base\Console\Commands\DoctorCommand;
use Xuple\EvoLayer\Base\Console\Commands\FontsSelfHost;
use Xuple\EvoLayer\Base\Console\Commands\InstallCommand;
use Xuple\EvoLayer\Base\Console\Commands\OntologyCompileCommand;
use Xuple\EvoLayer\Base\Console\Commands\PromoteUserCommand;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;
use Xuple\EvoLayer\Base\Http\Middleware\EnsureExampleEnabled;
use Xuple\EvoLayer\Base\Http\Middleware\RequireAdmin;
use Xuple\EvoLayer\Base\Support\OntologyRegistry;

class BaseServiceProvider extends ServiceProvider
{
    /**
     * EVOLAYER_BASE_EXAMPLE_* flags that gate per-feature route files under routes/features/.
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
        $this->mergeConfigFrom(__DIR__.'/../config/evolayer.php', 'evolayer');
        $this->mergeConfigFrom(__DIR__.'/../config/evolayer-ai.php', 'ai');

        $this->app->singleton(AdminGate::class, SpatieAdminGate::class);
        $this->app->singleton(UserResolver::class, DefaultUserResolver::class);
        $this->app->singleton(OntologyRegistry::class);
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('example', EnsureExampleEnabled::class);
        $router->aliasMiddleware('evolayer.admin', RequireAdmin::class);

        $router->middlewareGroup('evolayer', (array) config('evolayer.base.route.middleware', ['web']));

        // Per-feature route files — each only loads when its EVOLAYER_BASE_EXAMPLE_*
        // flag is true. With all flags default-false, installing the package
        // adds zero routes to the host's route:list (the "zero routes on
        // install" principle).
        foreach (self::FEATURE_ROUTES as $feature) {
            if (config("evolayer.base.examples.{$feature}")) {
                Route::middleware('evolayer')->group(__DIR__."/../routes/features/{$feature}.php");
            }
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register Base's ontology under the evolayer.base namespace. Variant
        // packages register their own (evolayer.commerce, etc.) the same way; the
        // compiler merges them. The host may publish + customise this copy via
        // the evolayer-base-ontology tag — if they do, point the registry at
        // base_path('ontology.yaml') from the host's AppServiceProvider.
        $this->app->make(OntologyRegistry::class)
            ->register('evolayer.base', __DIR__.'/../stubs/ontology.yaml');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
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
            __DIR__.'/../config/evolayer.php' => config_path('evolayer.php'),
            __DIR__.'/../config/evolayer-ai.php' => config_path('evolayer-ai.php'),
        ], 'evolayer-base-config');

        // ── Frontend: core (always-on UI primitives, no feature flag) ──────────
        // Blocks, command palette, shared hooks/types/config/layouts. Publish
        // this once; it has no cross-feature route dependencies.
        $coreFrontend = [
            __DIR__.'/../resources/js/blocks' => resource_path('js/blocks'),
            __DIR__.'/../resources/js/components' => resource_path('js/components'),
            __DIR__.'/../resources/js/providers' => resource_path('js/providers'),
            __DIR__.'/../resources/js/layouts' => resource_path('js/layouts'),
            __DIR__.'/../resources/js/config' => resource_path('js/config'),
            __DIR__.'/../resources/js/hooks/use-evolayer-props.ts' => resource_path('js/hooks/use-evolayer-props.ts'),
            __DIR__.'/../resources/js/hooks/use-example-nav-items.ts' => resource_path('js/hooks/use-example-nav-items.ts'),
            __DIR__.'/../resources/js/types/layout.ts' => resource_path('js/types/layout.ts'),
            __DIR__.'/../resources/js/types/evolayer.d.ts' => resource_path('js/types/evolayer.d.ts'),
            __DIR__.'/../resources/js/lib/appearance.ts' => resource_path('js/lib/appearance.ts'),
            __DIR__.'/../resources/js/lib/platform.ts' => resource_path('js/lib/platform.ts'),
        ];
        $this->publishes($coreFrontend, 'evolayer-base-frontend-core');

        // ── Frontend: per-feature page sets ───────────────────────────────────
        // Each tag mirrors a routes/features/*.php file. Publish only the tags
        // for features you've enabled, so published pages never import a
        // controller whose route isn't registered (which would break tsc).
        $featureFrontend = [
            'thread-studio' => [
                __DIR__.'/../resources/js/pages/evolayer/ai/thread-studio.tsx' => resource_path('js/pages/evolayer/ai/thread-studio.tsx'),
                __DIR__.'/../resources/js/hooks/use-thread-studio-stream.ts' => resource_path('js/hooks/use-thread-studio-stream.ts'),
                __DIR__.'/../resources/js/hooks/use-typewriter.ts' => resource_path('js/hooks/use-typewriter.ts'),
            ],
            'prd-studio' => [
                __DIR__.'/../resources/js/pages/evolayer/admin/prd.tsx' => resource_path('js/pages/evolayer/admin/prd.tsx'),
            ],
            'admin-inbox' => [
                __DIR__.'/../resources/js/pages/evolayer/admin/inbox' => resource_path('js/pages/evolayer/admin/inbox'),
                __DIR__.'/../resources/js/pages/evolayer/admin/submissions' => resource_path('js/pages/evolayer/admin/submissions'),
            ],
            'contact-ai' => [
                __DIR__.'/../resources/js/pages/evolayer/contact.tsx' => resource_path('js/pages/evolayer/contact.tsx'),
                __DIR__.'/../resources/js/pages/evolayer/contact-thank-you.tsx' => resource_path('js/pages/evolayer/contact-thank-you.tsx'),
            ],
            'marketing-pages' => [
                __DIR__.'/../resources/js/pages/evolayer/about.tsx' => resource_path('js/pages/evolayer/about.tsx'),
                __DIR__.'/../resources/js/pages/evolayer/home.tsx' => resource_path('js/pages/evolayer/home.tsx'),
            ],
        ];

        $everything = $coreFrontend;
        foreach ($featureFrontend as $feature => $paths) {
            $this->publishes($paths, "evolayer-base-frontend-{$feature}");
            $everything = array_merge($everything, $paths);
        }

        // Convenience meta-tag: publishes core + every feature page set at once.
        // Intended for demos / "show me everything"; production installs should
        // publish core + only the feature tags they've enabled.
        $this->publishes($everything, 'evolayer-base-frontend');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'evolayer-base-migrations');

        $this->publishes([
            __DIR__.'/../patches' => base_path('patches'),
        ], 'evolayer-base-patches');

        $this->publishes([
            __DIR__.'/../stubs/package-json-additions.json' => base_path('package-json-additions.evolayer.json'),
        ], 'evolayer-base-npm');

        $this->publishes([
            __DIR__.'/../stubs/ontology.yaml' => base_path('ontology.yaml'),
        ], 'evolayer-base-ontology');
    }
}
