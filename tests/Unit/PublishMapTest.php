<?php

use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

test('every configured example has one canonical managed surface', function () {
    $map = app(PublishMap::class);
    $surfaces = $map->surfaces();

    expect(array_values(array_map(fn ($surface): string => $surface->configKey, $surfaces)))
        ->toHaveCount(count(config('evolayer.base.examples')))
        ->toEqualCanonicalizing(array_keys(config('evolayer.base.examples')));
});

test('canonical surfaces reference existing route and source files', function () {
    $map = app(PublishMap::class);
    $targetFiles = [];

    foreach ($map->surfaces() as $name => $surface) {
        expect($surface->id)->toBe($name)
            ->and($surface->routeFile)->toBeFile("Surface [{$name}] route file does not exist.");

        foreach ($map->expand($surface->paths) as $source => $target) {
            expect($source)->toBeFile("Surface [{$name}] source file does not exist.")
                ->and($targetFiles)->not->toHaveKey($target, "Target [{$target}] belongs to multiple surfaces.");

            $targetFiles[$target] = $source;
        }
    }
});

test('legacy feature and ejection maps are derived from canonical surfaces', function () {
    $map = app(PublishMap::class);
    $expected = collect($map->surfaces())
        ->filter(fn ($surface): bool => $surface->ejectable)
        ->map(fn ($surface): array => $surface->paths)
        ->all();

    expect($map->features())->toBe($expected)
        ->and($map->ejectableSurfaces())->toBe(array_keys($expected));
});

test('the canonical map excludes official Starter host integration source', function () {
    $files = app(PublishMap::class)->managedFiles();

    expect($files)
        ->not->toHaveKeys([
            'resources/js/config/navigation.ts',
            'resources/js/layouts/public-layout.tsx',
        ])
        ->toHaveKeys([
            'resources/js/config/command-palette.ts',
            'resources/js/config/docs.ts',
            'resources/js/pages/evolayer/contact.tsx',
        ])
        ->and($files['resources/js/pages/evolayer/contact.tsx']['surface'])
        ->toBe('contact-ai');
});

test('managed route contracts match the registered package routes', function () {
    $map = app(PublishMap::class);
    $routes = app('router')->getRoutes();
    $missingOwnership = [];

    foreach ($map->surfaces() as $surface) {
        foreach ($surface->routes as $contract) {
            $route = $routes->getByName($contract->name);

            expect($route)->not->toBeNull()
                ->and($route->methods())->toBe($contract->methods)
                ->and(trim($route->uri(), '/'))->toBe(trim($contract->uri, '/'))
                ->and($route->getDomain())->toBe($contract->domain);

            if (($route->getAction()['evolayer_owner'] ?? null) !== RouteCollisionInspector::OWNER
                || ($route->getAction()['evolayer_surface'] ?? null) !== $surface->id) {
                $missingOwnership[] = $contract->name;
            }
        }
    }

    expect($missingOwnership)->toBe([]);
});
