<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean any prior publish artifacts in the testbench app workspace.
    foreach ([
        config_path('evodevops.php'),
        config_path('evodevops-ai.php'),
        resource_path('js/blocks'),
        resource_path('js/pages/evodevops'),
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

test('publishing evodevops-base-config drops both config files into the host config path', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-config',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(config_path('evodevops.php')))->toBeTrue()
        ->and(File::exists(config_path('evodevops-ai.php')))->toBeTrue();
});

test('publishing evodevops-base-frontend-core drops blocks, components, shared hooks, providers, layouts, config, types, lib — and NO feature pages', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-frontend-core',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/blocks/ai-text-field/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/blocks/streaming-card/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/command-bar.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/ui/command.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/providers/command-palette-provider.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/layouts/public-layout.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/config/navigation.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-evo-props.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-example-nav-items.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/types/evodevops.d.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/lib/appearance.ts')))->toBeTrue()
        // Core must NOT carry feature pages or feature-specific hooks:
        ->and(File::exists(resource_path('js/pages/evodevops/ai/thread-studio.tsx')))->toBeFalse()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeFalse();
});

test('per-feature frontend tags publish only their own page sets', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-frontend-thread-studio',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/evodevops/ai/thread-studio.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-typewriter.ts')))->toBeTrue()
        // thread-studio tag must NOT pull in inbox/prd/contact pages:
        ->and(File::exists(resource_path('js/pages/evodevops/admin/prd.tsx')))->toBeFalse()
        ->and(File::exists(resource_path('js/pages/evodevops/contact.tsx')))->toBeFalse();

    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-frontend-admin-inbox',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/evodevops/admin/inbox/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/admin/submissions/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/admin/submissions/show.tsx')))->toBeTrue();
});

test('the evodevops-base-frontend meta tag publishes core plus every feature page set', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-frontend',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(resource_path('js/blocks/ai-text-field/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/ai/thread-studio.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/admin/inbox/index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/admin/prd.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/contact.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/evodevops/home.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/hooks/use-thread-studio-stream.ts')))->toBeTrue()
        ->and(File::exists(resource_path('js/types/evodevops.d.ts')))->toBeTrue();
});

test('publishing evodevops-base-npm drops the package-json additions snippet', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-npm',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('package-json-additions.evodevops.json')))->toBeTrue();
});

test('published page stubs reference the package controller namespace, not App', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-frontend',
        '--force' => true,
    ])->assertSuccessful();

    $thread = File::get(resource_path('js/pages/evodevops/ai/thread-studio.tsx'));

    expect($thread)->toContain('@/actions/EvoDevOps/Base/Http/Controllers/Ai/ThreadStudioController')
        ->and($thread)->not->toContain('@/actions/App/Http/Controllers/');
});

test('publishing evodevops-base-patches drops the structured streaming patch into the host', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'evodevops-base-patches',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('patches/laravel-ai-structured-streaming.patch')))->toBeTrue()
        ->and(File::exists(base_path('patches/README.md')))->toBeTrue();
});
