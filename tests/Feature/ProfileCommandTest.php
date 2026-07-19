<?php

use Illuminate\Support\Facades\File;
use Xuple\EvoLayer\Base\Support\ManagedSurface;
use Xuple\EvoLayer\Base\Support\PublishMap;

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

        public function surfaces(): array
        {
            return [
                'thread-studio' => new ManagedSurface(
                    id: 'thread-studio',
                    configKey: 'thread_studio',
                    routeFile: __FILE__,
                    ejectable: true,
                    paths: [$this->source => $this->target],
                ),
            ];
        }

        public function manifestPath(): string
        {
            return $this->manifest;
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
    expect($contents)->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false')
        ->and($contents)->toContain('EVOLAYER_BASE_EXAMPLE_PRD_STUDIO=false') // appended when absent
        ->and($contents)->toContain('APP_NAME=Test');                        // untouched
});

test('demo profile turns every example flag on', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\n");

    $this->artisan('evolayer:profile', ['profile' => 'demo', '--path' => $this->env])->assertSuccessful();

    expect(file_get_contents($this->env))->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true');
});

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
