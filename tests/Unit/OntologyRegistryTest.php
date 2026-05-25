<?php

use Xuple\EvoLayer\Base\Support\OntologyCompiler;
use Xuple\EvoLayer\Base\Support\OntologyRegistry;

beforeEach(function () {
    $this->registry = new OntologyRegistry;
    $this->fixturesDir = sys_get_temp_dir().'/evo-ontology-'.uniqid();
    mkdir($this->fixturesDir);
});

afterEach(function () {
    if (is_dir($this->fixturesDir)) {
        foreach (glob($this->fixturesDir.'/*') as $f) {
            unlink($f);
        }
        rmdir($this->fixturesDir);
    }
});

test('OntologyRegistry::register stores a namespace-to-path mapping', function () {
    $path = $this->fixturesDir.'/base.yaml';
    file_put_contents($path, "version: 1\nnamespace: evolayer.base\nentities: {}\nevents: {}\nblocks: {}\njobs: {}\nagents: {}\nprojections: {}\nlanes: {}\n");

    $this->registry->register('evolayer.base', $path);

    expect($this->registry->all())->toBe(['evolayer.base' => $path])
        ->and($this->registry->has('evolayer.base'))->toBeTrue()
        ->and($this->registry->get('evolayer.base'))->toBe($path);
});

test('OntologyRegistry rejects registering a missing file', function () {
    expect(fn () => $this->registry->register('evolayer.base', '/nonexistent/ontology.yaml'))
        ->toThrow(RuntimeException::class, 'file not found');
});

test('OntologyRegistry rejects re-registering a namespace with a different path', function () {
    $a = $this->fixturesDir.'/a.yaml';
    $b = $this->fixturesDir.'/b.yaml';
    file_put_contents($a, "version: 1\nnamespace: evo.x\n");
    file_put_contents($b, "version: 1\nnamespace: evo.x\n");

    $this->registry->register('evo.x', $a);

    expect(fn () => $this->registry->register('evo.x', $b))
        ->toThrow(RuntimeException::class, 'already registered');
});

test('OntologyRegistry rejects mismatched declared vs registered namespace', function () {
    $path = $this->fixturesDir.'/mismatched.yaml';
    file_put_contents($path, "version: 1\nnamespace: evolayer.commerce\n");

    expect(fn () => $this->registry->register('evolayer.base', $path))
        ->toThrow(RuntimeException::class, 'namespace mismatch');
});

test('OntologyCompiler::compileAll merges registered ontologies by namespace', function () {
    // Build two minimal-but-valid ontologies (mirror Base's required keys)
    $minimalSpec = function (string $namespace): string {
        return "version: 1\nnamespace: {$namespace}\nname: Test {$namespace}\nentities: {}\nevents: {}\nblocks: {}\njobs: {}\nagents: {}\nprojections: {}\nlanes: {}\n";
    };

    $baseFile = $this->fixturesDir.'/base.yaml';
    $commerceFile = $this->fixturesDir.'/commerce.yaml';
    file_put_contents($baseFile, $minimalSpec('evolayer.base'));
    file_put_contents($commerceFile, $minimalSpec('evolayer.commerce'));

    $this->registry->register('evolayer.base', $baseFile);
    $this->registry->register('evolayer.commerce', $commerceFile);

    $compiled = (new OntologyCompiler)->compileAll($this->registry);

    expect($compiled)->toHaveKey('compiled_at')
        ->toHaveKey('namespaces')
        ->and($compiled['namespaces'])->toHaveKeys(['evolayer.base', 'evolayer.commerce'])
        ->and($compiled['namespaces']['evolayer.base']['namespace'])->toBe('evolayer.base')
        ->and($compiled['namespaces']['evolayer.commerce']['namespace'])->toBe('evolayer.commerce');
});
