<?php

use EvoDevOps\Base\Support\OntologyCompiler;
use EvoDevOps\Base\Support\OntologyRegistry;
use Illuminate\Support\Facades\File;

test('Base registers its ontology under the evo.base namespace', function () {
    $registry = app(OntologyRegistry::class);

    expect($registry->has('evo.base'))->toBeTrue()
        ->and($registry->get('evo.base'))->toEndWith('stubs/ontology.yaml');
});

test('compileAll merges the registered Base ontology and validates structurally', function () {
    $compiled = app(OntologyCompiler::class)->compileAll(app(OntologyRegistry::class));

    expect($compiled)->toHaveKey('namespaces')
        ->and($compiled['namespaces'])->toHaveKey('evo.base')
        ->and($compiled['namespaces']['evo.base']['namespace'])->toBe('evo.base')
        ->and($compiled['namespaces']['evo.base']['ontology']['entities'])->toHaveKey('form_submission');
});

test('route/file references for enabled features produce no warnings, disabled features warn', function () {
    // All example flags are enabled in the test environment, and the frontend
    // is not published into the testbench app — so block-path references warn
    // (files absent) but route references do not (routes registered).
    $compiled = app(OntologyCompiler::class)->compileAll(app(OntologyRegistry::class));
    $warnings = $compiled['namespaces']['evo.base']['warnings'];

    // Block path warnings are expected (frontend not published in test app).
    expect(collect($warnings)->filter(fn ($w) => str_contains($w, 'path'))->isNotEmpty())->toBeTrue();

    // No route warnings — every feature flag is on in the test environment, so
    // the endpoints are registered.
    expect(collect($warnings)->filter(fn ($w) => str_contains($w, 'route')))->toBeEmpty();
});

test('the ontology:compile command writes the cache and TypeScript artifacts', function () {
    $output = base_path('bootstrap/cache/ontology.php');
    $types = base_path('resources/js/types/ontology.ts');

    foreach ([$output, $types] as $path) {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    $this->artisan('ontology:compile', [
        '--no-erd' => true,
    ])->assertSuccessful();

    expect(File::exists($output))->toBeTrue()
        ->and(File::exists($types))->toBeTrue()
        ->and(File::get($types))->toContain('export const ontology')
        ->and(File::get($types))->toContain('evo.base');
});
