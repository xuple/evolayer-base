<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean any prior publish artifacts in the testbench app workspace.
    foreach ([
        config_path('evolayer.php'),
        config_path('evolayer-ai.php'),
        resource_path('js/blocks'),
        resource_path('js/pages/evolayer'),
        resource_path('js/hooks'),
        resource_path('js/components'),
        resource_path('js/providers'),
        resource_path('js/layouts'),
        resource_path('js/config'),
        resource_path('js/types'),
        resource_path('js/lib'),
        base_path('patches'),
    ] as $path) {
        if (File::exists($path)) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }
    }
});

test('publishing evolayer-base-config drops both config files into the host config path', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-config',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(config_path('evolayer.php')))->toBeTrue()
        ->and(File::exists(config_path('evolayer-ai.php')))->toBeTrue();
});

test('publishing evolayer-base-frontend-core drops blocks, components, shared hooks, providers, layouts, config, types, lib — and NO feature pages', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend-core',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/blocks/ai-text-field/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/blocks/streaming-card/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/command-bar.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/ui/command.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/providers/command-palette-provider.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/layouts/public-layout.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/config/navigation.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-evolayer-props.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-example-nav-items.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/types/evolayer.d.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/lib/appearance.ts')))->toBeTrue()
        // Core must NOT carry feature pages or feature-specific hooks:
        ->and(File::exists(resource_path('js/pages/evolayer/ai/thread-studio.tsx')))->toBeFalse()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeFalse();
});

test('per-feature frontend tags publish only their own page sets', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend-thread-studio',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/evolayer/ai/thread-studio.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-typewriter.ts')))->toBeTrue()
        // thread-studio tag must NOT pull in inbox/prd/contact pages:
        ->and(File::exists(resource_path('js/pages/evolayer/admin/prd.tsx')))->toBeFalse()
        ->and(File::exists(resource_path('js/pages/evolayer/contact.tsx')))->toBeFalse();

    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend-admin-inbox',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/evolayer/admin/inbox/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/submissions/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/submissions/show.tsx')))->toBeTrue();

    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend-marketing-pages',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/evolayer/about.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/home.tsx')))->toBeTrue();
});

test('the evolayer-base-frontend meta tag publishes core plus every feature page set', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/blocks/ai-text-field/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/ai/thread-studio.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/inbox/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/prd.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/contact.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/about.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/home.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/types/evolayer.d.ts')))->toBeTrue();
});

test('the preserve-overrides frontend tag skips host-owned landing pages', function () {
    File::ensureDirectoryExists(resource_path('js/pages/evolayer'));

    $aboutOverride = "// _STARTER_OWNED_PAGE_\nexport default function AboutOverride() { return null; }\n";
    $homeOverride = "// _STARTER_OWNED_PAGE_\nexport default function HomeOverride() { return null; }\n";

    File::put(resource_path('js/pages/evolayer/about.tsx'), $aboutOverride);
    File::put(resource_path('js/pages/evolayer/home.tsx'), $homeOverride);

    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend-preserve-overrides',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::get(resource_path('js/pages/evolayer/about.tsx')))->toBe($aboutOverride)
        ->and(File::get(resource_path('js/pages/evolayer/home.tsx')))->toBe($homeOverride)
        ->and(File::exists(resource_path('js/blocks/ai-text-field/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/command-bar.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/ai/thread-studio.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/inbox/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/admin/prd.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evolayer/contact.tsx')))->toBeTrue();
});

test('publishing evolayer-base-npm drops the package-json additions snippet', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-npm',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('package-json-additions.evolayer.json')))->toBeTrue();
});

test('published page stubs reference the package controller namespace, not App', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-frontend',
        '--force' => true,
    ])->assertSuccessful();

    $thread = File::get(resource_path('js/pages/evolayer/ai/thread-studio.tsx'));

    expect($thread)->toContain('@/actions/Xuple/EvoLayer/Base/Http/Controllers/Ai/ThreadStudioController')
        ->and($thread)->not->toContain('@/actions/App/Http/Controllers/');
});

test('publishing evolayer-base-patches drops the structured streaming patch into the host', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evolayer-base-patches',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('patches/laravel-ai-structured-streaming.patch')))->toBeTrue()
        ->and(File::exists(base_path('patches/README.md')))->toBeTrue();
});
