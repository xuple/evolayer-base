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

test('enabled features produce no route warnings (advisory validation)', function () {
    // Every example flag is enabled in the test environment, so all endpoints
    // are registered — there should be zero route-existence warnings.
    $compiled = app(OntologyCompiler::class)->compileAll(app(OntologyRegistry::class));
    $warnings = $compiled['namespaces']['evo.base']['warnings'];

    expect(collect($warnings)->filter(fn ($w) => str_contains($w, 'route')))->toBeEmpty();
});

test('a block whose file is absent produces an advisory warning, not a failure', function () {
    // Compile a controlled fixture referencing a definitely-absent block path.
    // This must warn, not throw — design-time vs runtime: an unpublished
    // frontend is not an authoring error.
    $dir = sys_get_temp_dir().'/evo-onto-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/ontology.yaml', <<<'YAML'
        version: 1
        namespace: evo.test
        name: Test
        entities: {}
        events: {}
        jobs: {}
        agents: {}
        projections: {}
        lanes: {}
        blocks:
          ghost:
            path: resources/js/blocks/definitely-not-here/index.tsx
        YAML);

    $compiled = app(OntologyCompiler::class)->compile($dir.'/ontology.yaml');

    expect($compiled['warnings'])
        ->toHaveCount(1)
        ->and($compiled['warnings'][0])->toContain('definitely-not-here');

    unlink($dir.'/ontology.yaml');
    rmdir($dir);
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
