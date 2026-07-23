<?php

namespace Xuple\EvoLayer\Base;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Xuple\EvoLayer\Base\Auth\DefaultUserResolver;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Console\Commands\Ai\AiStreamCheck;
use Xuple\EvoLayer\Base\Console\Commands\AiProbeCommand;
use Xuple\EvoLayer\Base\Console\Commands\AiSmokeTest;
use Xuple\EvoLayer\Base\Console\Commands\DoctorCommand;
use Xuple\EvoLayer\Base\Console\Commands\EjectCommand;
use Xuple\EvoLayer\Base\Console\Commands\FontsSelfHost;
use Xuple\EvoLayer\Base\Console\Commands\InstallCommand;
use Xuple\EvoLayer\Base\Console\Commands\ManifestAdoptCommand;
use Xuple\EvoLayer\Base\Console\Commands\ManifestInspectCommand;
use Xuple\EvoLayer\Base\Console\Commands\OntologyCompileCommand;
use Xuple\EvoLayer\Base\Console\Commands\ProfileCommand;
use Xuple\EvoLayer\Base\Console\Commands\ProfileStatusCommand;
use Xuple\EvoLayer\Base\Console\Commands\ProfileVerifyCommand;
use Xuple\EvoLayer\Base\Console\Commands\PromoteUserCommand;
use Xuple\EvoLayer\Base\Console\Commands\ResyncCommand;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Contracts\UserResolver;
use Xuple\EvoLayer\Base\Http\Middleware\EnsureExampleEnabled;
use Xuple\EvoLayer\Base\Http\Middleware\RequireAdmin;
use Xuple\EvoLayer\Base\Support\OntologyRegistry;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\CommittedIntentVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\EffectiveConfigurationVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\ManagedRoutesVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\ManagedSourceVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\RouteCollisionsVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\EnvironmentProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ManagedSurfaceProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileDefinition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileRegistry;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

class BaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evolayer.php', 'evolayer');
        $this->mergeConfigFrom(__DIR__.'/../config/evolayer-ai.php', 'ai');
        // Also expose the package's AI config under its own namespace. The merge
        // into 'ai' above is shallow at `providers.*`, so the laravel/ai SDK's
        // bare provider blocks (driver/key/url, no `models`) win and the package's
        // per-provider `models.text.default` is dropped from config('ai'). The
        // package reads its model defaults from 'evolayer-ai' instead, which is
        // reliably populated here (and via the host's published config file).
        $this->mergeConfigFrom(__DIR__.'/../config/evolayer-ai.php', 'evolayer-ai');

        $this->app->singleton(AdminGate::class, SpatieAdminGate::class);
        $this->app->singleton(UserResolver::class, DefaultUserResolver::class);
        $this->app->singleton(OntologyRegistry::class);
        $this->app->singleton(ProfileRegistry::class, function (): ProfileRegistry {
            $registry = new ProfileRegistry;
            $exampleKeys = array_keys((array) config('evolayer.base.examples'));
            $featureKeys = array_keys((array) config('evolayer.base.features'));

            $registry->register(new ProfileDefinition(
                id: 'demo',
                schemaVersion: 1,
                examples: array_fill_keys($exampleKeys, true),
                features: array_fill_keys($featureKeys, true),
                requiredCapabilities: ['profile.committed-intent', 'profile.environment-projection', 'profile.managed-surfaces'],
                allowedOverrides: ['examples', 'features'],
                verificationRequirements: ['generated-contracts'],
            ));
            $registry->register(new ProfileDefinition(
                id: 'lean',
                schemaVersion: 1,
                examples: array_fill_keys($exampleKeys, false),
                features: array_fill_keys($featureKeys, false),
                requiredCapabilities: ['profile.committed-intent', 'profile.environment-projection', 'profile.managed-surfaces'],
                allowedOverrides: ['examples', 'features'],
                verificationRequirements: ['generated-contracts'],
            ));

            return $registry;
        });

        $this->app->tag([
            ProjectMetadataProfileTransitionContributor::class,
            EnvironmentProfileTransitionContributor::class,
            ManagedSurfaceProfileTransitionContributor::class,
        ], ProfileTransitionManager::CONTRIBUTOR_TAG);

        $this->app->when(ProfileTransitionManager::class)
            ->needs(ProfileTransitionContributor::class)
            ->giveTagged(ProfileTransitionManager::CONTRIBUTOR_TAG);

        $this->app->tag([
            CommittedIntentVerificationCheck::class,
            EffectiveConfigurationVerificationCheck::class,
            ManagedSourceVerificationCheck::class,
            ManagedRoutesVerificationCheck::class,
            RouteCollisionsVerificationCheck::class,
        ], ProfileVerificationManager::CHECK_TAG);

        $this->app->when(ProfileVerificationManager::class)
            ->needs(ProfileVerificationCheck::class)
            ->giveTagged(ProfileVerificationManager::CHECK_TAG);
    }

    public function boot(PublishMap $map, RouteCollisionInspector $routeCollisions): void
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
        if (! $this->app->routesAreCached()) {
            foreach ($map->surfaces() as $surface) {
                if (! config("evolayer.base.examples.{$surface->configKey}")) {
                    continue;
                }

                $shadowed = [];

                foreach ($surface->routes as $contract) {
                    $shadowed[$contract->name] = $routeCollisions->matchingRoutes(
                        $router->getRoutes(),
                        $contract,
                    );
                }

                Route::middleware('evolayer')->group($surface->routeFile);

                foreach ($surface->routes as $contract) {
                    $route = collect($routeCollisions->matchingRoutes(
                        $router->getRoutes(),
                        $contract,
                    ))->first(fn ($candidate): bool => $candidate->getName() === $contract->name);

                    if ($route !== null) {
                        $routeCollisions->markPackageRoute(
                            $route,
                            $surface->id,
                            $shadowed[$contract->name],
                        );
                    }
                }
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
                ManifestInspectCommand::class,
                ManifestAdoptCommand::class,
                DoctorCommand::class,
                AiProbeCommand::class,
                AiSmokeTest::class,
                AiStreamCheck::class,
                FontsSelfHost::class,
                OntologyCompileCommand::class,
                PromoteUserCommand::class,
                ResyncCommand::class,
                EjectCommand::class,
                ProfileCommand::class,
                ProfileStatusCommand::class,
                ProfileVerifyCommand::class,
            ]);

            $this->registerPublishables($map);
        }
    }

    private function registerPublishables(PublishMap $map): void
    {
        $this->publishes([
            __DIR__.'/../config/evolayer.php' => config_path('evolayer.php'),
            __DIR__.'/../config/evolayer-ai.php' => config_path('evolayer-ai.php'),
        ], 'evolayer-base-config');

        // ── Frontend: core (always-on UI primitives, no feature flag) ──────────
        // Blocks, command palette, shared hooks/types/config/layouts. Publish
        // this once; it has no cross-feature route dependencies.
        $coreFrontend = $map->core();
        $this->publishes($coreFrontend, 'evolayer-base-frontend-core');

        // ── Frontend: per-feature page sets ───────────────────────────────────
        // Each tag mirrors a routes/features/*.php file. Publish only the tags
        // for features you've enabled, so published pages never import a
        // controller whose route isn't registered (which would break tsc).
        // Source of truth: Support\PublishMap (shared with evolayer:resync).
        $featureFrontend = $map->features();

        $everything = $coreFrontend;
        $preserveOverrides = $coreFrontend;
        foreach ($featureFrontend as $feature => $paths) {
            $this->publishes($paths, "evolayer-base-frontend-{$feature}");
            $everything = array_merge($everything, $paths);

            if ($feature !== 'marketing-pages') {
                $preserveOverrides = array_merge($preserveOverrides, $paths);
            }
        }

        // Convenience meta-tag: publishes core + every feature page set at once.
        // Intended for demos / "show me everything"; production installs should
        // publish core + only the feature tags they've enabled.
        $this->publishes($everything, 'evolayer-base-frontend');

        // DEPRECATED — superseded by `evolayer:resync` + `evolayer:eject`.
        // about/home are no longer a bespoke exclusion: rebrand them via
        // config('evolayer.base.brand') (shared through Support\EvoLayerProps),
        // or take ownership with `evolayer:eject marketing-pages`. Kept only for
        // backward compatibility with hosts that still call this vendor:publish
        // tag directly; remove once the starter adopts the resync command.
        $this->publishes($preserveOverrides, 'evolayer-base-frontend-preserve-overrides');

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
