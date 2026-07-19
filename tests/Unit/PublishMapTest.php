<?php

use Xuple\EvoLayer\Base\Support\PublishMap;

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
