<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ManagedSurface;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\CommittedIntentVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\EffectiveConfigurationVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\ManagedRoutesVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\ManagedSourceVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks\RouteCollisionsVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;
use Xuple\EvoLayer\Base\Support\PublishMap;

function legacyUpgradeFixtureRoot(): string
{
    return __DIR__.'/../Fixtures/starter-v0.1.19-base-v0.1.9';
}

function legacyUpgradeCopyFixture(string $target): void
{
    $fixture = legacyUpgradeFixtureRoot();

    foreach (File::allFiles($fixture, hidden: true) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if ($relative === 'provenance.json') {
            continue;
        }

        $destination = $target.'/'.$relative;
        File::ensureDirectoryExists(dirname($destination));
        File::copy($file->getPathname(), $destination);
    }

    File::copy($target.'/.env.example', $target.'/.env');
}

function legacyUpgradePublishMap(string $hostRoot): PublishMap
{
    return new class($hostRoot) extends PublishMap
    {
        public function __construct(private readonly string $root) {}

        public function hostRoot(): string
        {
            return $this->root;
        }

        public function manifestPath(): string
        {
            return $this->root.'/.evolayer/resync.lock.json';
        }

        public function core(): array
        {
            return $this->remap(parent::core());
        }

        public function surfaces(): array
        {
            return array_map(function (ManagedSurface $surface): ManagedSurface {
                return new ManagedSurface(
                    id: $surface->id,
                    configKey: $surface->configKey,
                    routeFile: $surface->routeFile,
                    ejectable: $surface->ejectable,
                    paths: $this->remap($surface->paths),
                    routes: $surface->routes,
                );
            }, parent::surfaces());
        }

        /** @param array<string, string> $pairs @return array<string, string> */
        private function remap(array $pairs): array
        {
            $resourceRoot = rtrim(str_replace('\\', '/', resource_path()), '/');
            $remapped = [];

            foreach ($pairs as $source => $target) {
                $normalizedTarget = str_replace('\\', '/', $target);
                $relative = ltrim(substr($normalizedTarget, strlen($resourceRoot)), '/');
                $remapped[$source] = $this->root.'/resources/'.$relative;
            }

            return $remapped;
        }
    };
}

/** @return array<string, string> */
function legacyUpgradeSnapshot(string $root): array
{
    $snapshot = [];

    foreach (File::allFiles($root, hidden: true) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if ($relative === '.evolayer/mutation.lock') {
            continue;
        }

        $snapshot[$relative] = hash_file('sha256', $file->getPathname());
    }

    ksort($snapshot);

    return $snapshot;
}

function legacyUpgradeBindVerificationCheck(): void
{
    $hostCheck = new class implements ProfileVerificationCheck
    {
        public function id(): string
        {
            return 'fixture.generated-contracts';
        }

        public function apiVersion(): int
        {
            return ProfileVerificationManager::CONTRACT_VERSION;
        }

        public function capability(): string
        {
            return 'generated-contracts';
        }

        public function verify(ProfileVerificationContext $context): ProfileVerificationResult
        {
            return ProfileVerificationResult::pass();
        }

        public function fingerprint(ProfileVerificationContext $context): array
        {
            return ['fixture' => 'starter-v0.1.19-base-v0.1.9'];
        }
    };

    app()->forgetInstance(ProfileVerificationManager::class);
    app()->instance(ProfileVerificationManager::class, new ProfileVerificationManager(
        app(CommittedIntentVerificationCheck::class),
        app(EffectiveConfigurationVerificationCheck::class),
        app(ManagedSourceVerificationCheck::class),
        app(ManagedRoutesVerificationCheck::class),
        app(RouteCollisionsVerificationCheck::class),
        $hostCheck,
    ));
}

beforeEach(function () {
    $this->legacyHost = sys_get_temp_dir().'/evolayer-legacy-upgrade-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->legacyHost);
    legacyUpgradeCopyFixture($this->legacyHost);
    app()->instance(PublishMap::class, legacyUpgradePublishMap($this->legacyHost));
    config()->set('evolayer.base.profile', null);
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), true));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), true));
});

afterEach(function () {
    File::deleteDirectory($this->legacyHost);
});

test('the fixture is bound to the exact public Starter and Base release evidence', function () {
    $fixture = legacyUpgradeFixtureRoot();
    $provenance = json_decode((string) file_get_contents($fixture.'/provenance.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($fixture.'/.evolayer/resync.lock.json'), true, flags: JSON_THROW_ON_ERROR);
    $composer = json_decode((string) file_get_contents($fixture.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $treeLines = [];

    foreach (File::allFiles($fixture, hidden: true) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if (in_array($relative, ['.evolayer/project.json', 'provenance.json'], true)) {
            continue;
        }

        $treeLines[$relative] = hash_file('sha256', $file->getPathname()).'  ./'.$relative."\n";
    }

    ksort($treeLines);

    expect(hash('sha256', implode('', $treeLines)))->toBe($provenance['starter']['extracted_files_tree_sha256'])
        ->and($composer['require']['xuple/evolayer-base'])->toBe('0.1.9')
        ->and(hash_file('sha256', $fixture.'/.evolayer/resync.lock.json'))->toBe($provenance['starter']['resync_manifest_sha256'])
        ->and($manifest['package_version'])->toBe('v0.1.9')
        ->and($manifest['files'])->toHaveCount(28)
        ->and($manifest['files'])->toHaveKey('resources/js/pages/evolayer/contact-thank-you.tsx')
        ->and($manifest['files'])->not->toHaveKey('resources/js/pages/evolayer/contact.tsx')
        ->and(hash_file('sha256', $fixture.'/resources/js/pages/evolayer/contact.tsx'))
        ->toBe($provenance['known_contact_gap']['starter_installed_sha256'])
        ->and(hash_file('sha256', $fixture.'/.evolayer/project.json'))
        ->toBe($provenance['generated_project_metadata']['sha256'])
        ->and($fixture.'/resources/js/actions')->not->toBeDirectory()
        ->and($fixture.'/resources/js/routes')->not->toBeDirectory()
        ->and($fixture.'/resources/js/types/ontology.ts')->not->toBeFile();

    foreach ($manifest['files'] as $key => $record) {
        expect($fixture.'/'.$key)->toBeFile()
            ->and(hash_file('sha256', $fixture.'/'.$key))->toBe($record['installed_sha']);
    }
});

test('legacy identity remains separate while the exact effective default is proposed as demo', function () {
    $metadataBefore = file_get_contents($this->legacyHost.'/.evolayer/project.json');

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain(
        '"status": "selection-required"',
        '"repository_kind": "generated-application"',
        '"profile": null',
        '"suggested_profile": "demo"',
    )->and($output)->not->toContain($this->legacyHost)
        ->and(file_get_contents($this->legacyHost.'/.evolayer/project.json'))->toBe($metadataBefore);

    config()->set('evolayer.base.examples.contact_ai', false);
    Artisan::call('evolayer:profile:status', ['--json' => true]);

    expect(Artisan::output())->toContain('"suggested_profile": null')
        ->and(file_get_contents($this->legacyHost.'/.evolayer/project.json'))->toBe($metadataBefore);
});

test('the exact reviewed Contact gap is adopted and repeated adoption is a no-op', function () {
    Artisan::call('evolayer:manifest:inspect', ['--json' => true]);
    $inspection = Artisan::output();

    expect($inspection)->toContain('"status": "adoptable"', 'adoptable-pristine')
        ->and($inspection)->not->toContain($this->legacyHost);

    Artisan::call('evolayer:manifest:inspect', ['--json' => true]);
    expect(Artisan::output())->toBe($inspection);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();
    $manifestPath = $this->legacyHost.'/.evolayer/resync.lock.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $adopted = $manifest['files']['resources/js/pages/evolayer/contact.tsx'];

    expect($adopted)->toBe([
        'surface' => 'contact-ai',
        'source_sha' => 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f',
        'installed_sha' => 'f0f1e6bc09931c1ca1ba0422dfe2c75c0d5bc37792880f212c49e6efb8d5f88d',
    ]);

    $afterFirstAdoption = file_get_contents($manifestPath);
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();

    expect(file_get_contents($manifestPath))->toBe($afterFirstAdoption);
});

test('both reviewed Contact release byte variants can be adopted', function (string $fixturePath, string $installedChecksum) {
    File::copy(
        __DIR__.'/../Fixtures/'.$fixturePath,
        $this->legacyHost.'/resources/js/pages/evolayer/contact.tsx',
    );

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();
    $manifest = json_decode(
        (string) file_get_contents($this->legacyHost.'/.evolayer/resync.lock.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['files']['resources/js/pages/evolayer/contact.tsx'])->toMatchArray([
        'source_sha' => 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f',
        'installed_sha' => $installedChecksum,
    ]);
})->with([
    'Starter formatted bytes' => ['starter-v0.1.19-base-v0.1.9/resources/js/pages/evolayer/contact.tsx', 'f0f1e6bc09931c1ca1ba0422dfe2c75c0d5bc37792880f212c49e6efb8d5f88d'],
    'Base source bytes' => ['base-v0.1.9/contact.tsx', 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f'],
]);

test('modified missing provenance remains unknown and leaves the fixture unchanged', function () {
    $contact = $this->legacyHost.'/resources/js/pages/evolayer/contact.tsx';
    file_put_contents($contact, file_get_contents($contact)."\nHOST MODIFICATION\n");
    $before = legacyUpgradeSnapshot($this->legacyHost);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertFailed();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($before)
        ->and(file_get_contents($contact))->toContain('HOST MODIFICATION');
});

test('reviewed bytes with mismatched release identity are not adopted', function (string $field, string $value) {
    $metadataPath = $this->legacyHost.'/.evolayer/project.json';
    $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
    $metadata[$field] = $value;
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $before = legacyUpgradeSnapshot($this->legacyHost);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertFailed();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($before);
})->with([
    'Starter mismatch' => ['distribution_version', 'v0.1.18'],
    'Base mismatch' => ['framework_version', 'v0.1.8'],
]);

test('the exact legacy fixture completes an idempotent lean transition and bounded verification', function () {
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();
    $envPath = $this->legacyHost.'/.env';

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $envPath,
        '--json' => true,
    ])->assertSuccessful();

    $metadata = json_decode(
        (string) file_get_contents($this->legacyHost.'/.evolayer/project.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $map = app(PublishMap::class);

    expect($metadata)->toMatchArray([
        'schema_version' => 2,
        'kind' => 'generated-application',
        'profile' => 'lean',
    ])->and($metadata)->not->toHaveKey('mode')
        ->and($metadata['distribution'])->toBe('xuple/evolayer-base-starter')
        ->and($metadata['generated_app']['source_starter'])->toBe('xuple/evolayer-base-starter');

    foreach ($map->features() as $pairs) {
        foreach ($map->expand($pairs) as $target) {
            expect($target)->not->toBeFile();
        }
    }

    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    app('router')->setRoutes(new RouteCollection);

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    expect(Artisan::output())->toContain(
        '"status": "pending-verification"',
        '"effective_drift": false',
        '"managed_state": "aligned"',
    );

    $this->artisan('evolayer:resync')->assertSuccessful();
    $afterResync = legacyUpgradeSnapshot($this->legacyHost);
    $this->artisan('evolayer:resync')->assertSuccessful();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($afterResync);

    Artisan::call('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $envPath,
        '--json' => true,
    ]);
    $repeat = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($repeat['operations']['total'])->toBe(0);

    legacyUpgradeBindVerificationCheck();
    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $verification = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($verification)->toContain(
            '"lifecycle_status": "verified"',
            '"profile": "lean"',
            '"receipt_state": "written"',
        )->and($verification)->not->toContain($this->legacyHost, 'EVOLAYER_BASE_', 'secret');
});

test('resync refuses to erase unresolved legacy adoption context', function () {
    $envPath = $this->legacyHost.'/.env';
    $this->artisan('evolayer:profile', [
        'profile' => 'demo',
        '--path' => $envPath,
    ])->assertSuccessful();
    $before = legacyUpgradeSnapshot($this->legacyHost);

    $this->artisan('evolayer:resync')
        ->expectsOutputToContain('evolayer:manifest:adopt --pristine-only')
        ->assertFailed();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($before);
});

test('re-enabling restores current package source without reviving disabled source during lean resync', function () {
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();
    $envPath = $this->legacyHost.'/.env';
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $envPath])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    $this->artisan('evolayer:resync')->assertSuccessful();
    $contactTarget = $this->legacyHost.'/resources/js/pages/evolayer/contact.tsx';

    expect($contactTarget)->not->toBeFile();

    $this->artisan('evolayer:profile', ['profile' => 'demo', '--path' => $envPath])->assertSuccessful();
    config()->set('evolayer.base.profile', 'demo');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), true));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), true));
    $this->artisan('evolayer:resync')->assertSuccessful();

    expect($contactTarget)->toBeFile()
        ->and(hash_file('sha256', $contactTarget))
        ->toBe(hash_file('sha256', app(PublishMap::class)->managedFiles()['resources/js/pages/evolayer/contact.tsx']['source']));
});

test('recorded modified and ejected legacy source block incompatible lean transitions without mutation', function (string $variant) {
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertSuccessful();
    $manifestPath = $this->legacyHost.'/.evolayer/resync.lock.json';

    if ($variant === 'modified') {
        $target = $this->legacyHost.'/resources/js/pages/evolayer/contact-thank-you.tsx';
        file_put_contents($target, file_get_contents($target)."\nHOST MODIFICATION\n");
    } else {
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $manifest['surfaces']['contact-ai'] = 'ejected';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    $before = legacyUpgradeSnapshot($this->legacyHost);
    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->legacyHost.'/.env',
    ])->assertFailed();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($before);
})->with(['modified', 'ejected']);

test('malformed legacy provenance fails closed across every migration mutation', function (string $contents) {
    file_put_contents($this->legacyHost.'/.evolayer/resync.lock.json', $contents);
    $before = legacyUpgradeSnapshot($this->legacyHost);

    $this->artisan('evolayer:manifest:inspect', ['--json' => true])->assertFailed();
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertFailed();
    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->legacyHost.'/.env',
    ])->assertFailed();
    $this->artisan('evolayer:resync')->assertFailed();

    expect(legacyUpgradeSnapshot($this->legacyHost))->toBe($before);
})->with([
    'malformed JSON' => '{broken',
    'structurally invalid' => '{"schema_version":1,"surfaces":{"contact-ai":"unknown"},"files":{}}',
]);
