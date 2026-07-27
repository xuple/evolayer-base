<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Xuple\EvoLayer\Base\Support\LegacyManifestAdoptionCatalog;
use Xuple\EvoLayer\Base\Support\ManagedSurface;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\PublishMap;

beforeEach(function () {
    $base = sys_get_temp_dir().'/evo-resync-'.uniqid();
    $this->base = $base;
    $this->src = $base.'/src';
    $this->dst = $base.'/dst';
    $this->manifestPath = $base.'/.evolayer/resync.lock.json';

    File::ensureDirectoryExists($this->src);
    File::ensureDirectoryExists($this->dst);
    file_put_contents($this->src.'/page.tsx', "SOURCE v1\n");

    $src = $this->src;
    $dst = $this->dst;
    $manifest = $this->manifestPath;

    // Fake the publish map at temp paths so the command exercises real file
    // logic without writing into the Testbench skeleton's resource_path.
    app()->bind(PublishMap::class, fn () => new class($src, $dst, $manifest) extends PublishMap
    {
        public function __construct(
            private string $fakeSrc,
            private string $fakeDst,
            private string $fakeManifest,
        ) {}

        public function core(): array
        {
            return [];
        }

        public function packageRoot(): string
        {
            return $this->fakeSrc;
        }

        public function features(): array
        {
            return ['demo' => [
                $this->fakeSrc.'/page.tsx' => $this->fakeDst.'/page.tsx',
                $this->fakeSrc.'/second.tsx' => $this->fakeDst.'/second.tsx',
            ]];
        }

        public function surfaces(): array
        {
            return [
                'demo' => new ManagedSurface(
                    id: 'demo',
                    configKey: 'demo',
                    routeFile: __FILE__,
                    ejectable: true,
                    paths: $this->features()['demo'],
                ),
            ];
        }

        public function manifestPath(): string
        {
            return $this->fakeManifest;
        }

        public function hostRoot(): string
        {
            return dirname($this->fakeDst);
        }
    });

    $this->installedChecksum = hash('sha256', "SOURCE v1\n");
    $this->historicalSourceChecksum = str_repeat('a', 64);
    app()->instance(LegacyManifestAdoptionCatalog::class, new LegacyManifestAdoptionCatalog([
        '0.1.9' => [
            '0.1.19' => [
                'dst/page.tsx' => [
                    $this->installedChecksum => $this->historicalSourceChecksum,
                ],
            ],
        ],
    ]));

    $this->writeLegacyManifest = function (array $files = [], array $surfaces = [], bool $withMetadata = false): string {
        File::ensureDirectoryExists(dirname($this->manifestPath));

        if ($withMetadata) {
            file_put_contents($this->base.'/.evolayer/project.json', json_encode([
                'distribution' => 'xuple/evolayer-base-starter',
                'distribution_version' => 'v0.1.19',
                'framework' => 'xuple/evolayer-base',
                'framework_version' => 'v0.1.9',
                'mode' => 'application',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        $bytes = json_encode([
            'surfaces' => $surfaces,
            'files' => $files,
            'package_version' => 'v0.1.9',
            'generated_at' => '2026-07-03T16:08:04+00:00',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        file_put_contents($this->manifestPath, $bytes);

        return $bytes;
    };
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('resync creates a missing managed file and writes a manifest', function () {
    File::delete($this->dst.'/page.tsx');

    $this->artisan('evolayer:resync')->assertSuccessful();

    $installedBytes = (string) file_get_contents($this->dst.'/page.tsx');
    $installedSha = hash('sha256', $installedBytes);
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true, flags: JSON_THROW_ON_ERROR);

    expect($installedBytes)->toBe("SOURCE v1\n")
        ->and($manifest['files']['dst/page.tsx'])->toMatchArray([
            'source_sha' => $installedSha,
            'installed_sha' => $installedSha,
        ]);
});

test('resync keeps a host-modified file but --force overrides it', function () {
    $this->artisan('evolayer:resync')->assertSuccessful(); // establishes provenance

    file_put_contents($this->dst.'/page.tsx', "HOST EDIT\n");
    file_put_contents($this->src.'/page.tsx', "SOURCE v2\n");

    $this->artisan('evolayer:resync')->assertSuccessful();
    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("HOST EDIT\n");

    $this->artisan('evolayer:resync', ['--force' => true])->assertSuccessful();
    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("SOURCE v2\n");
});

test('resync updates a pristine file when the source changes', function () {
    $this->artisan('evolayer:resync')->assertSuccessful(); // dst pristine == v1

    file_put_contents($this->src.'/page.tsx', "SOURCE v2\n");

    $this->artisan('evolayer:resync')->assertSuccessful();
    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("SOURCE v2\n");
});

test('eject makes a surface app-owned and resync stops touching it', function () {
    $this->artisan('evolayer:eject', ['surface' => 'demo'])->assertSuccessful();
    $ejectedBytes = (string) file_get_contents($this->dst.'/page.tsx');
    $ejectedSha = hash('sha256', $ejectedBytes);
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['files']['dst/page.tsx'])->toMatchArray([
        'source_sha' => $ejectedSha,
        'installed_sha' => $ejectedSha,
    ]);

    file_put_contents($this->dst.'/page.tsx', "OWNED\n");
    file_put_contents($this->src.'/page.tsx', "SOURCE v9\n");

    $this->artisan('evolayer:resync')->assertSuccessful();
    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("OWNED\n");
});

test('resync refuses to record unchanged provenance after concurrent target modification', function () {
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifestState = json_decode((string) file_get_contents($this->manifestPath), true);
    $manifestState['package_version'] = 'v0.1.8';
    $manifest = json_encode($manifestState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    file_put_contents($this->manifestPath, $manifest);
    $target = $this->dst.'/page.tsx';

    app()->forgetInstance(ProfileTransitionExecutor::class);
    app()->instance(ProfileTransitionExecutor::class, new class($target) extends ProfileTransitionExecutor
    {
        public function __construct(private readonly string $target) {}

        protected function beforeMutation(string $path, int $mutation): void
        {
            file_put_contents($this->target, "CONCURRENT\n");
        }
    });

    $this->artisan('evolayer:resync')->assertFailed();

    expect(file_get_contents($target))->toBe("CONCURRENT\n")
        ->and(file_get_contents($this->manifestPath))->toBe($manifest);
});

test('eject refuses to record ownership after concurrent target modification', function () {
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = file_get_contents($this->manifestPath);
    $target = $this->dst.'/page.tsx';

    app()->forgetInstance(ProfileTransitionExecutor::class);
    app()->instance(ProfileTransitionExecutor::class, new class($target) extends ProfileTransitionExecutor
    {
        public function __construct(private readonly string $target) {}

        protected function beforeMutation(string $path, int $mutation): void
        {
            file_put_contents($this->target, "CONCURRENT\n");
        }
    });

    $this->artisan('evolayer:eject', ['surface' => 'demo'])->assertFailed();

    expect(file_get_contents($target))->toBe("CONCURRENT\n")
        ->and(file_get_contents($this->manifestPath))->toBe($manifest);
});

test('eject rejects core and unknown surfaces', function () {
    $this->artisan('evolayer:eject', ['surface' => 'core'])->assertFailed();
    $this->artisan('evolayer:eject', ['surface' => 'nope'])->assertFailed();
});

test('dry-run writes neither files nor manifest', function () {
    File::delete($this->dst.'/page.tsx');

    $this->artisan('evolayer:resync', ['--dry-run' => true])->assertSuccessful();

    expect(is_file($this->dst.'/page.tsx'))->toBeFalse();
    expect(is_file($this->manifestPath))->toBeFalse();
    expect(is_dir($this->base.'/.evolayer'))->toBeFalse();
});

test('resync fails closed when the manifest contains malformed JSON', function () {
    $target = "HOST BYTES\n";
    file_put_contents($this->dst.'/page.tsx', $target);
    File::ensureDirectoryExists(dirname($this->manifestPath));
    file_put_contents($this->manifestPath, '{broken');

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(file_get_contents($this->dst.'/page.tsx'))->toBe($target)
        ->and(file_get_contents($this->manifestPath))->toBe('{broken');
});

test('eject fails closed when the manifest contains malformed JSON', function () {
    $target = "HOST BYTES\n";
    file_put_contents($this->dst.'/page.tsx', $target);
    File::ensureDirectoryExists(dirname($this->manifestPath));
    file_put_contents($this->manifestPath, '{broken');

    $this->artisan('evolayer:eject', ['surface' => 'demo'])->assertFailed();

    expect(file_get_contents($this->dst.'/page.tsx'))->toBe($target)
        ->and(file_get_contents($this->manifestPath))->toBe('{broken');
});

test('resync rejects manifest paths that are not exact descriptor targets', function (string $hostileKey) {
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true);
    $manifest['files'][$hostileKey] = [
        'surface' => 'demo',
        'source_sha' => str_repeat('a', 64),
        'installed_sha' => str_repeat('b', 64),
    ];
    file_put_contents($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $manifestBytes = file_get_contents($this->manifestPath);
    $targetBytes = file_get_contents($this->dst.'/page.tsx');

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(file_get_contents($this->manifestPath))->toBe($manifestBytes)
        ->and(file_get_contents($this->dst.'/page.tsx'))->toBe($targetBytes);
})->with([
    'absolute' => '/tmp/manifest-victim.tsx',
    'parent traversal' => '../manifest-victim.tsx',
    'dot alias' => './page.tsx',
    'embedded traversal' => 'nested/../page.tsx',
    'Windows drive path' => 'C:/manifest-victim.tsx',
    'backslash alias' => 'nested\\page.tsx',
    'undescribed relative path' => 'manifest-victim.tsx',
]);

test('resync rejects unsupported manifest schemas without mutation', function () {
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true);
    $manifest['schema_version'] = 999;
    file_put_contents($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $manifestBytes = file_get_contents($this->manifestPath);
    $targetBytes = file_get_contents($this->dst.'/page.tsx');

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(file_get_contents($this->manifestPath))->toBe($manifestBytes)
        ->and(file_get_contents($this->dst.'/page.tsx'))->toBe($targetBytes);
});

test('resync preserves compatible unknown top-level metadata', function () {
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true);
    $manifest['extension_metadata'] = ['owner' => 'host'];
    file_put_contents($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    file_put_contents($this->src.'/page.tsx', "SOURCE v2\n");
    $this->artisan('evolayer:resync')->assertSuccessful();

    $updated = json_decode((string) file_get_contents($this->manifestPath), true);
    expect($updated['extension_metadata'])->toBe(['owner' => 'host']);
});

test('resync rejects a linked managed target without touching its referent', function () {
    $victim = $this->base.'/victim.txt';
    file_put_contents($victim, "VICTIM\n");
    File::delete($this->dst.'/page.tsx');
    expect(symlink($victim, $this->dst.'/page.tsx'))->toBeTrue();

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(is_link($this->dst.'/page.tsx'))->toBeTrue()
        ->and(file_get_contents($victim))->toBe("VICTIM\n")
        ->and($this->manifestPath)->not->toBeFile();

    unlink($this->dst.'/page.tsx');
});

test('resync rejects a linked ancestor without writing through it', function () {
    $linkedDirectory = $this->base.'/linked-destination';
    File::deleteDirectory($this->dst);
    File::ensureDirectoryExists($linkedDirectory);
    expect(symlink($linkedDirectory, $this->dst))->toBeTrue();

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect($linkedDirectory.'/page.tsx')->not->toBeFile()
        ->and($this->manifestPath)->not->toBeFile();

    unlink($this->dst);
});

test('resync rejects a multiply-linked managed target', function () {
    $victim = $this->base.'/hard-link-victim.txt';
    file_put_contents($victim, "SHARED\n");
    File::delete($this->dst.'/page.tsx');
    expect(link($victim, $this->dst.'/page.tsx'))->toBeTrue();

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(file_get_contents($victim))->toBe("SHARED\n")
        ->and(file_get_contents($this->dst.'/page.tsx'))->toBe("SHARED\n")
        ->and($this->manifestPath)->not->toBeFile();
});

test('resync rejects a linked manifest without changing its referent', function () {
    $victim = $this->base.'/manifest-victim.json';
    $bytes = json_encode(['surfaces' => [], 'files' => []], JSON_PRETTY_PRINT).PHP_EOL;
    file_put_contents($victim, $bytes);
    File::ensureDirectoryExists(dirname($this->manifestPath));
    expect(symlink($victim, $this->manifestPath))->toBeTrue();

    $this->artisan('evolayer:resync')->assertFailed();

    expect(is_link($this->manifestPath))->toBeTrue()
        ->and(file_get_contents($victim))->toBe($bytes);

    unlink($this->manifestPath);
});

test('resync fails without mutation when another managed operation holds the project lock', function () {
    File::delete($this->dst.'/page.tsx');
    $lockPath = $this->base.'/.evolayer/mutation.lock';
    File::ensureDirectoryExists(dirname($lockPath));
    $handle = fopen($lockPath, 'c+');
    expect($handle)->not->toBeFalse()
        ->and(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();

    try {
        $this->artisan('evolayer:resync')->assertFailed();

        expect($this->dst.'/page.tsx')->not->toBeFile()
            ->and($this->manifestPath)->not->toBeFile();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
});

test('resync does not recreate a surface disabled by committed profile intent', function () {
    File::delete($this->dst.'/page.tsx');
    File::ensureDirectoryExists($this->base.'/.evolayer');
    file_put_contents($this->base.'/.evolayer/project.json', json_encode([
        'schema_version' => 2,
        'kind' => 'generated-application',
        'profile' => 'lean',
        'overrides' => ['examples' => ['demo' => false], 'features' => []],
        'applied_with' => [],
    ], JSON_PRETTY_PRINT).PHP_EOL);

    $this->artisan('evolayer:resync')->assertSuccessful();

    expect($this->dst.'/page.tsx')->not->toBeFile();
    $manifest = json_decode((string) file_get_contents($this->manifestPath), true);
    expect($manifest['files'])->toBe([]);
});

test('resync restores the current package file when committed intent re-enables a surface', function () {
    File::delete($this->dst.'/page.tsx');
    File::ensureDirectoryExists($this->base.'/.evolayer');
    file_put_contents($this->base.'/.evolayer/project.json', json_encode([
        'schema_version' => 2,
        'kind' => 'generated-application',
        'profile' => 'lean',
        'overrides' => ['examples' => ['demo' => true], 'features' => []],
        'applied_with' => [],
    ], JSON_PRETTY_PRINT).PHP_EOL);

    $this->artisan('evolayer:resync')->assertSuccessful();

    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("SOURCE v1\n");
});

test('resync rejects unknown profile overrides without mutation', function () {
    $target = "HOST\n";
    file_put_contents($this->dst.'/page.tsx', $target);
    File::ensureDirectoryExists($this->base.'/.evolayer');
    $metadata = json_encode([
        'schema_version' => 2,
        'kind' => 'generated-application',
        'profile' => 'lean',
        'overrides' => ['examples' => ['unknown' => false], 'features' => []],
        'applied_with' => [],
    ], JSON_PRETTY_PRINT).PHP_EOL;
    file_put_contents($this->base.'/.evolayer/project.json', $metadata);

    $this->artisan('evolayer:resync', ['--force' => true])->assertFailed();

    expect(file_get_contents($this->dst.'/page.tsx'))->toBe($target)
        ->and($this->manifestPath)->not->toBeFile()
        ->and(file_get_contents($this->base.'/.evolayer/project.json'))->toBe($metadata);
});

test('manifest inspection reports exact legacy bytes as adoptable without mutation', function () {
    file_put_contents($this->dst.'/page.tsx', "SOURCE v1\n");
    $manifestBytes = ($this->writeLegacyManifest)(withMetadata: true);

    $exitCode = Artisan::call('evolayer:manifest:inspect', ['--json' => true]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload)->toMatchArray([
            'schema_version' => 1,
            'status' => 'adoptable',
            'package_version' => 'v0.1.9',
        ])
        ->and($payload['findings']['dst/page.tsx'])->toBe([
            'surface' => 'demo',
            'status' => 'adoptable-pristine',
        ])
        ->and(file_get_contents($this->manifestPath))->toBe($manifestBytes);
});

test('manifest inspection fails when legacy bytes require operator selection', function () {
    file_put_contents($this->dst.'/page.tsx', "HOST EDIT\n");
    ($this->writeLegacyManifest)(withMetadata: true);

    $exitCode = Artisan::call('evolayer:manifest:inspect', ['--json' => true]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('selection-required')
        ->and($payload['findings']['dst/page.tsx']['status'])->toBe('selection-required');
});

test('manifest adoption requires the explicit pristine-only boundary', function () {
    file_put_contents($this->dst.'/page.tsx', "SOURCE v1\n");
    $manifestBytes = ($this->writeLegacyManifest)(withMetadata: true);

    $this->artisan('evolayer:manifest:adopt')->assertFailed();

    expect(file_get_contents($this->manifestPath))->toBe($manifestBytes);
});

test('manifest adoption records only exact historical provenance', function () {
    file_put_contents($this->dst.'/page.tsx', "SOURCE v1\n");
    ($this->writeLegacyManifest)(withMetadata: true);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])
        ->assertSuccessful();

    $manifest = json_decode((string) file_get_contents($this->manifestPath), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['files']['dst/page.tsx'])->toBe([
        'surface' => 'demo',
        'source_sha' => $this->historicalSourceChecksum,
        'installed_sha' => $this->installedChecksum,
    ]);
});

test('manifest adoption is all-or-nothing when any existing target is unproven', function () {
    file_put_contents($this->src.'/second.tsx', "SECOND SOURCE\n");
    file_put_contents($this->dst.'/page.tsx', "SOURCE v1\n");
    file_put_contents($this->dst.'/second.tsx', "UNKNOWN\n");
    $manifestBytes = ($this->writeLegacyManifest)(withMetadata: true);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])
        ->assertFailed();

    expect(file_get_contents($this->manifestPath))->toBe($manifestBytes);
});

test('manifest adoption skips ejected and absent descriptor targets', function () {
    $manifestBytes = ($this->writeLegacyManifest)(surfaces: ['demo' => 'ejected'], withMetadata: true);

    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])
        ->assertSuccessful();

    expect(file_get_contents($this->manifestPath))->toBe($manifestBytes);
});

test('manifest inspection emits redacted JSON for invalid manifest state', function () {
    file_put_contents($this->dst.'/page.tsx', "SOURCE v1\n");
    File::ensureDirectoryExists(dirname($this->manifestPath));
    file_put_contents($this->manifestPath, '{broken');

    $exitCode = Artisan::call('evolayer:manifest:inspect', ['--json' => true]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($payload)->toBe([
            'schema_version' => 1,
            'status' => 'conflict',
            'error' => 'invalid-manifest-state',
            'findings' => [],
        ])
        ->and($output)->not->toContain($this->base);
});

test('manifest inspection and adoption reject linked descriptor targets', function () {
    $victim = $this->base.'/adoption-victim.tsx';
    file_put_contents($victim, "SOURCE v1\n");
    expect(symlink($victim, $this->dst.'/page.tsx'))->toBeTrue();
    $manifestBytes = ($this->writeLegacyManifest)(withMetadata: true);

    $this->artisan('evolayer:manifest:inspect', ['--json' => true])->assertFailed();
    $this->artisan('evolayer:manifest:adopt', ['--pristine-only' => true])->assertFailed();

    expect(is_link($this->dst.'/page.tsx'))->toBeTrue()
        ->and(file_get_contents($victim))->toBe("SOURCE v1\n")
        ->and(file_get_contents($this->manifestPath))->toBe($manifestBytes);

    unlink($this->dst.'/page.tsx');
});
