<?php

use Illuminate\Support\Facades\File;
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

        public function features(): array
        {
            return ['demo' => [$this->fakeSrc.'/page.tsx' => $this->fakeDst.'/page.tsx']];
        }

        public function manifestPath(): string
        {
            return $this->fakeManifest;
        }
    });
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('resync creates a missing managed file and writes a manifest', function () {
    File::delete($this->dst.'/page.tsx');

    $this->artisan('evolayer:resync')->assertSuccessful();

    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("SOURCE v1\n");
    expect(is_file($this->manifestPath))->toBeTrue();
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

    file_put_contents($this->dst.'/page.tsx', "OWNED\n");
    file_put_contents($this->src.'/page.tsx', "SOURCE v9\n");

    $this->artisan('evolayer:resync')->assertSuccessful();
    expect(file_get_contents($this->dst.'/page.tsx'))->toBe("OWNED\n");
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
});
