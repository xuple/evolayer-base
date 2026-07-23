<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ManagedRoute;
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

function bindGeneratedContractsVerification(
    string $input,
    bool $passes = true,
    bool $throws = false,
    ?string $correctiveAction = null,
): void {
    $hostCheck = new class($input, $passes, $throws, $correctiveAction) implements ProfileVerificationCheck
    {
        public function __construct(
            private string $input,
            private bool $passes,
            private bool $throws,
            private ?string $correctiveAction,
        ) {}

        public function id(): string
        {
            return 'starter.generated-contracts';
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
            if ($this->throws) {
                throw new RuntimeException('secret-value at '.$this->input);
            }

            return $this->passes
                ? ProfileVerificationResult::pass()
                : ProfileVerificationResult::fail(
                    'generated-contracts-failed',
                    $this->correctiveAction ?? 'Run the Starter-owned generated-contract verification and retry.',
                );
        }

        public function fingerprint(ProfileVerificationContext $context): array
        {
            return ['input_sha256' => hash_file('sha256', $this->input)];
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
    $this->base = sys_get_temp_dir().'/evo-profile-'.uniqid();
    $this->env = $this->base.'/.env';
    $this->source = $this->base.'/source/page.tsx';
    $this->target = $this->base.'/host/page.tsx';
    $this->manifest = $this->base.'/.evolayer/resync.lock.json';

    File::ensureDirectoryExists(dirname($this->source));
    file_put_contents($this->source, "PACKAGE\n");

    $source = $this->source;
    $target = $this->target;
    $manifest = $this->manifest;

    app()->bind(PublishMap::class, fn () => new class($source, $target, $manifest) extends PublishMap
    {
        public function __construct(
            private string $source,
            private string $target,
            private string $manifest,
        ) {}

        public function core(): array
        {
            return [];
        }

        public function packageRoot(): string
        {
            return dirname($this->source);
        }

        public function surfaces(): array
        {
            return [
                'thread-studio' => new ManagedSurface(
                    id: 'thread-studio',
                    configKey: 'thread_studio',
                    routeFile: __FILE__,
                    ejectable: true,
                    paths: [$this->source => $this->target],
                    routes: [new ManagedRoute(['GET', 'HEAD'], 'thread-studio', 'evolayer.base.thread-studio')],
                ),
            ];
        }

        public function manifestPath(): string
        {
            return $this->manifest;
        }

        public function hostRoot(): string
        {
            return dirname($this->source, 2);
        }
    });
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('lean profile turns every example flag off and leaves other env lines alone', function () {
    file_put_contents($this->env, "APP_NAME=Test\nEVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $contents = file_get_contents($this->env);
    expect($contents)->toContain('EVOLAYER_BASE_PROFILE=lean')
        ->and($contents)->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false')
        ->and($contents)->toContain('EVOLAYER_BASE_EXAMPLE_PRD_STUDIO=false') // appended when absent
        ->and($contents)->toContain('APP_NAME=Test');                        // untouched
});

test('demo profile turns every example flag on', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\n");

    $this->artisan('evolayer:profile', ['profile' => 'demo', '--path' => $this->env])->assertSuccessful();

    expect(file_get_contents($this->env))->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true');
});

test('explicit example and feature overrides are projected and committed canonically', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\nEVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS=false\n");

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--example' => ['thread_studio=true'],
        '--feature' => ['contact_attachments=true'],
    ])->assertSuccessful();

    $environment = file_get_contents($this->env);
    $metadata = json_decode((string) file_get_contents($this->base.'/.evolayer/project.json'), true);

    expect($environment)->toContain('EVOLAYER_BASE_PROFILE=lean')
        ->and($environment)->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true')
        ->and($environment)->toContain('EVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS=true')
        ->and($metadata['overrides'])->toBe([
            'examples' => ['thread_studio' => true],
            'features' => ['contact_attachments' => true],
        ]);
});

test('invalid duplicate and unknown overrides fail before any mutation', function (array $arguments) {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        ...$arguments,
    ])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and($this->base.'/.evolayer/project.json')->not->toBeFile();
})->with([
    'malformed' => [['--example' => ['thread_studio=1']]],
    'unknown example' => [['--example' => ['unknown=true']]],
    'unknown feature' => [['--feature' => ['unknown=false']]],
    'duplicate' => [['--example' => ['thread_studio=true', 'thread_studio=false']]],
]);

test('an unknown profile fails', function () {
    file_put_contents($this->env, "X=1\n");

    $this->artisan('evolayer:profile', ['profile' => 'wat', '--path' => $this->env])->assertFailed();
});

test('a missing env file fails', function () {
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => '/no/such/path/.env'])->assertFailed();
});

test('lean profile atomically prunes pristine managed files', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:resync')->assertSuccessful();

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $manifest = json_decode((string) file_get_contents($this->manifest), true);

    expect(file_get_contents($this->env))->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false')
        ->and($this->target)->not->toBeFile()
        ->and($manifest['files'])->toBe([]);
});

test('a modified managed file aborts before changing flags or source', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);
    $this->artisan('evolayer:resync')->assertSuccessful();
    file_put_contents($this->target, "HOST CHANGE\n");
    $manifest = file_get_contents($this->manifest);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->target))->toBe("HOST CHANGE\n")
        ->and(file_get_contents($this->manifest))->toBe($manifest);
});

test('duplicate environment keys abort before pruning managed files', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\nEVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\n";
    file_put_contents($this->env, $environment);
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = file_get_contents($this->manifest);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->target))->toBe("PACKAGE\n")
        ->and(file_get_contents($this->manifest))->toBe($manifest);
});

test('dry run preserves environment managed source and manifest', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);
    $this->artisan('evolayer:resync')->assertSuccessful();
    $manifest = file_get_contents($this->manifest);

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->target))->toBe("PACKAGE\n")
        ->and(file_get_contents($this->manifest))->toBe($manifest);
});

test('profile dry run does not create the mutation lock or metadata directory', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect($this->base.'/.evolayer')->not->toBeDirectory();
});

test('profile rejects dotenv-compatible duplicate environment key forms', function (string $duplicate) {
    $environment = "EVOLAYER_BASE_PROFILE=demo\n{$duplicate}\n";
    file_put_contents($this->env, $environment);

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
    ])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and($this->base.'/.evolayer/project.json')->not->toBeFile();
})->with([
    'leading whitespace' => '  EVOLAYER_BASE_PROFILE=lean',
    'double quoted key' => '"EVOLAYER_BASE_PROFILE"=lean',
    'single quoted key' => "'EVOLAYER_BASE_PROFILE'=lean",
]);

test('profile refuses a manifest-invented deletion path and changes nothing', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);
    $this->artisan('evolayer:resync')->assertSuccessful();

    $victim = $this->base.'/victim.txt';
    file_put_contents($victim, "DO NOT DELETE\n");
    $manifest = json_decode((string) file_get_contents($this->manifest), true);
    $manifest['files'][$victim] = [
        'surface' => 'thread-studio',
        'source_sha' => hash_file('sha256', $victim),
        'installed_sha' => hash_file('sha256', $victim),
    ];
    file_put_contents($this->manifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $manifestBytes = file_get_contents($this->manifest);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->target))->toBe("PACKAGE\n")
        ->and(file_get_contents($victim))->toBe("DO NOT DELETE\n")
        ->and(file_get_contents($this->manifest))->toBe($manifestBytes);
});

test('profile fails closed on a malformed manifest before changing intent or source', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);
    File::ensureDirectoryExists(dirname($this->target));
    file_put_contents($this->target, "PACKAGE\n");
    File::ensureDirectoryExists(dirname($this->manifest));
    file_put_contents($this->manifest, '{broken');

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->target))->toBe("PACKAGE\n")
        ->and(file_get_contents($this->manifest))->toBe('{broken');
});

test('profile refuses a linked environment file without changing its referent', function () {
    $referent = $this->base.'/real.env';
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($referent, $environment);
    expect(symlink($referent, $this->env))->toBeTrue();

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(is_link($this->env))->toBeTrue()
        ->and(file_get_contents($referent))->toBe($environment)
        ->and($this->target)->not->toBeFile()
        ->and($this->manifest)->not->toBeFile();

    unlink($this->env);
});

test('profile records schema-v2 intent separately from repository identity', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $metadata = json_decode((string) file_get_contents($this->base.'/.evolayer/project.json'), true);
    expect($metadata['schema_version'])->toBe(2)
        ->and($metadata['kind'])->toBe('package-host')
        ->and($metadata['profile'])->toBe('lean')
        ->and($metadata['overrides'])->toBe(['examples' => [], 'features' => []])
        ->and($metadata)->not->toHaveKey('verification');
});

test('an explicit profile selection migrates legacy starter identity and preserves unknown metadata', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    File::ensureDirectoryExists($this->base.'/.evolayer');
    file_put_contents($this->base.'/.evolayer/project.json', json_encode([
        'mode' => 'application',
        'suggested_package_name' => 'private/example',
    ], JSON_PRETTY_PRINT).PHP_EOL);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $metadata = json_decode((string) file_get_contents($this->base.'/.evolayer/project.json'), true);
    expect($metadata['kind'])->toBe('generated-application')
        ->and($metadata['profile'])->toBe('lean')
        ->and($metadata['suggested_package_name'])->toBe('private/example')
        ->and($metadata)->not->toHaveKey('mode');
});

test('unsupported future project metadata fails before changing environment or managed files', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);
    File::ensureDirectoryExists($this->base.'/.evolayer');
    $metadata = json_encode([
        'schema_version' => 999,
        'kind' => 'generated-application',
        'profile' => 'demo',
        'overrides' => ['examples' => [], 'features' => []],
        'applied_with' => [],
    ], JSON_PRETTY_PRINT).PHP_EOL;
    file_put_contents($this->base.'/.evolayer/project.json', $metadata);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertFailed();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->base.'/.evolayer/project.json'))->toBe($metadata)
        ->and($this->target)->not->toBeFile();
});

test('repeating an applied profile produces an empty dry-run plan', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    $environment = file_get_contents($this->env);
    $metadata = file_get_contents($this->base.'/.evolayer/project.json');

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--dry-run' => true,
    ])->expectsOutputToContain('across 0 file(s)')->assertSuccessful();

    expect(file_get_contents($this->env))->toBe($environment)
        ->and(file_get_contents($this->base.'/.evolayer/project.json'))->toBe($metadata);
});

test('no-env records intent while leaving deployment projection untouched', function () {
    $environment = "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n";
    file_put_contents($this->env, $environment);

    $this->artisan('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--no-env' => true,
    ])->assertSuccessful();

    $metadata = json_decode((string) file_get_contents($this->base.'/.evolayer/project.json'), true);
    expect(file_get_contents($this->env))->toBe($environment)
        ->and($metadata['profile'])->toBe('lean');
});

test('environment projection preserves UTF-8 BOM and CRLF newlines', function () {
    $environment = "\xEF\xBB\xBFEVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\r\nAPP_NAME=Test\r\n";
    file_put_contents($this->env, $environment);

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $updated = file_get_contents($this->env);
    expect($updated)->toStartWith("\xEF\xBB\xBF")
        ->and($updated)->toContain("EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\r\n")
        ->and(str_replace("\r\n", '', substr($updated, 3)))->not->toContain("\n");
});

test('profile status reports matching intent as pending until verification succeeds', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.examples', array_fill_keys(
        array_keys((array) config('evolayer.base.examples')),
        false,
    ));
    config()->set('evolayer.base.features', array_fill_keys(
        array_keys((array) config('evolayer.base.features')),
        false,
    ));
    config()->set('evolayer.base.profile', 'lean');

    $exitCode = Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('"status": "pending-verification"')
        ->and($output)->toContain('"profile": "lean"')
        ->and($output)->toContain('"verification_state": "unverified"');
});

test('profile status reports effective drift and exits failure', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $exitCode = Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('"status": "drift"')
        ->and($output)->toContain('"effective_drift": true');
});

test('profile status detects effective profile drift even when all flags align', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.examples', array_fill_keys(
        array_keys((array) config('evolayer.base.examples')),
        false,
    ));
    config()->set('evolayer.base.profile', 'demo');

    $this->artisan('evolayer:profile:status', ['--json' => true])
        ->expectsOutputToContain('"effective_drift": true')
        ->assertFailed();
});

test('profile status reports legacy metadata as selection-required without rewriting it', function () {
    File::ensureDirectoryExists($this->base.'/.evolayer');
    $metadata = json_encode(['mode' => 'application', 'custom' => 'preserved'], JSON_PRETTY_PRINT).PHP_EOL;
    file_put_contents($this->base.'/.evolayer/project.json', $metadata);

    $this->artisan('evolayer:profile:status', ['--json' => true])
        ->expectsOutputToContain('"status": "selection-required"')
        ->assertFailed();

    expect(file_get_contents($this->base.'/.evolayer/project.json'))->toBe($metadata);
});

test('profile JSON dry-run reports redacted operation counts without paths', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:resync')->assertSuccessful();

    $exitCode = Artisan::call('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--dry-run' => true,
        '--json' => true,
    ]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('planned')
        ->and($payload['operations']['replace'])->toBeGreaterThanOrEqual(2)
        ->and($payload['operations']['delete'])->toBe(1)
        ->and($payload['verification_state'])->toBe('not-applicable')
        ->and($output)->not->toContain($this->base)
        ->and($this->target)->toBeFile();
});

test('profile JSON conflicts expose stable codes and counts without local paths', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:resync')->assertSuccessful();
    file_put_contents($this->target, "HOST CHANGE\n");

    $exitCode = Artisan::call('evolayer:profile', [
        'profile' => 'lean',
        '--path' => $this->env,
        '--json' => true,
    ]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($payload)->toMatchArray([
            'schema_version' => 1,
            'status' => 'conflict',
            'error' => 'transition-conflict',
            'conflict_count' => 1,
        ])
        ->and($output)->not->toContain($this->base);
});

test('profile JSON redacts an unsafe mutation lock path', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    File::ensureDirectoryExists($this->base.'/.evolayer');
    $referent = $this->base.'/outside.lock';
    file_put_contents($referent, 'outside');
    symlink($referent, $this->base.'/.evolayer/mutation.lock');

    try {
        $exitCode = Artisan::call('evolayer:profile', [
            'profile' => 'lean',
            '--path' => $this->env,
            '--json' => true,
        ]);
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and(json_decode($output, true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
                'status' => 'conflict',
                'error' => 'mutation-lock-unavailable',
            ])
            ->and($output)->not->toContain($this->base)
            ->and(file_get_contents($referent))->toBe('outside');
    } finally {
        if (is_link($this->base.'/.evolayer/mutation.lock')) {
            unlink($this->base.'/.evolayer/mutation.lock');
        }
    }
});

test('profile verification writes a redacted receipt and promotes current status to verified', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input);

    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $receiptPath = $this->base.'/storage/framework/cache/data/evolayer-profile-verification.json';
    $receipt = (string) file_get_contents($receiptPath);

    expect($exitCode)->toBe(0)
        ->and($payload['lifecycle_status'])->toBe('verified')
        ->and($payload['receipt_state'])->toBe('written')
        ->and($receiptPath)->toBeFile()
        ->and($receipt)->not->toContain($this->base, 'secret-value', 'EVOLAYER_BASE_')
        ->and($receipt)->not->toContain('"status"')
        ->and($output)->not->toContain($this->base, 'CURRENT');

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    $statusOutput = Artisan::output();

    expect($statusOutput)->toContain('"status": "verified"', '"receipt_state": "current"');
});

test('profile verification fails when a required host capability is missing', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));

    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('required.generated-contracts', 'verification-capability-missing')
        ->and($output)->not->toContain($this->base);
});

test('profile verification fails when committed profile intent requires selection', function () {
    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('profile-selection-required', 'Select and apply a registered profile')
        ->and($output)->not->toContain($this->base);
});

test('profile verification attributes and redacts an unexpected host check failure', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/private-input';
    file_put_contents($input, 'secret-value');
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input, throws: true);

    Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('starter.generated-contracts', 'verification-check-error')
        ->and($output)->not->toContain($this->base, 'secret-value');
});

test('profile verification replaces an unsafe host corrective action', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/private-input';
    file_put_contents($input, 'secret-value');
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification(
        $input,
        passes: false,
        correctiveAction: 'Inspect '.$this->base.'/private-input containing secret-value.',
    );

    Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('starter.generated-contracts', 'generated-contracts-failed', 'Review and rerun verification check')
        ->and($output)->not->toContain($this->base, 'secret-value');
});

test('profile verification fails closed on malformed provenance', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    file_put_contents($this->manifest, '{broken');
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input);

    Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('managed-state-invalid')
        ->and($output)->not->toContain($this->base, '{broken');
});

test('profile verification fails on effective and managed drift', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'demo');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    File::ensureDirectoryExists(dirname($this->target));
    file_put_contents($this->target, "STALE DISABLED\n");
    bindGeneratedContractsVerification($input);

    Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('effective-configuration-drift', 'managed-source-drift')
        ->and($output)->not->toContain($this->base, 'STALE DISABLED');
});

test('profile verification fails on managed route state mismatch and collision', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    config()->set('evolayer.base.examples.thread_studio', true);
    app('router')->get('/thread-studio', fn (): string => 'host')->name('host.thread-studio');
    bindGeneratedContractsVerification($input);

    Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('managed-route-state-drift', 'managed-route-collision')
        ->and($output)->not->toContain($this->base, 'HostController');
});

test('profile verification returns a redacted internal error for an invalid verifier contract', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    app()->forgetInstance(ProfileVerificationManager::class);
    app()->instance(ProfileVerificationManager::class, new ProfileVerificationManager(
        app(CommittedIntentVerificationCheck::class),
        app(CommittedIntentVerificationCheck::class),
    ));

    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('internal-error')
        ->and($output)->not->toContain('Duplicate profile verification check', $this->base);
});

test('profile verification refuses a receipt when check inputs change before the write', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    $hostCheck = new class implements ProfileVerificationCheck
    {
        private int $fingerprints = 0;

        public function id(): string
        {
            return 'starter.generated-contracts';
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
            return ['revision' => ++$this->fingerprints];
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

    $exitCode = Artisan::call('evolayer:profile:verify', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('verification-input-changed')
        ->and($this->base.'/storage/framework/cache/data/evolayer-profile-verification.json')->not->toBeFile()
        ->and($output)->not->toContain($this->base);
});

test('profile status treats a malformed receipt as invalid non-authoritative evidence', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input);
    $this->artisan('evolayer:profile:verify', ['--json' => true])->assertSuccessful();
    file_put_contents($this->base.'/storage/framework/cache/data/evolayer-profile-verification.json', '{broken');

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('"status": "pending-verification"', '"receipt_state": "invalid"')
        ->and($output)->not->toContain($this->base, '{broken');
});

test('profile verification receipt becomes stale when bound inputs change', function (string $binding) {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input);
    $this->artisan('evolayer:profile:verify', ['--json' => true])->assertSuccessful();

    if ($binding === 'intent') {
        $metadataPath = $this->base.'/.evolayer/project.json';
        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        $metadata['receipt_test'] = true;
        file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    } elseif ($binding === 'effective') {
        config()->set('evolayer.base.brand.name', 'Changed but unbound');
        config()->set('evolayer.base.profile', 'demo');
    } elseif ($binding === 'manifest') {
        $manifest = is_file($this->manifest)
            ? json_decode((string) file_get_contents($this->manifest), true)
            : ['schema_version' => 1, 'surfaces' => [], 'files' => []];
        $manifest['receipt_test'] = true;
        File::ensureDirectoryExists(dirname($this->manifest));
        file_put_contents($this->manifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    } else {
        file_put_contents($input, "CHANGED\n");
    }

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->not->toContain('"status": "verified"')
        ->and($output)->toContain($binding === 'effective' ? '"status": "drift"' : '"receipt_state": "stale"');
})->with(['intent', 'effective', 'manifest', 'host-check']);

test('profile verification receipt becomes stale when a version binding changes', function (string $binding, string $field) {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");
    $input = $this->base.'/generated-contract-input';
    file_put_contents($input, "CURRENT\n");
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();
    config()->set('evolayer.base.profile', 'lean');
    config()->set('evolayer.base.examples', array_fill_keys(array_keys((array) config('evolayer.base.examples')), false));
    config()->set('evolayer.base.features', array_fill_keys(array_keys((array) config('evolayer.base.features')), false));
    bindGeneratedContractsVerification($input);
    $this->artisan('evolayer:profile:verify', ['--json' => true])->assertSuccessful();
    $receiptPath = $this->base.'/storage/framework/cache/data/evolayer-profile-verification.json';
    $receipt = json_decode((string) file_get_contents($receiptPath), true, flags: JSON_THROW_ON_ERROR);
    $receipt['bindings'][$binding][$field] = 'changed-binding';
    file_put_contents($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    Artisan::call('evolayer:profile:status', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('"status": "pending-verification"', '"receipt_state": "stale"')
        ->and($output)->not->toContain($this->base);
})->with([
    ['base', 'version'],
    ['base', 'reference'],
    ['host', 'version'],
    ['host', 'reference'],
]);
