<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Contracts\AdminGate;

test('evolayer:install publishes assets, migrates, and compiles the ontology', function () {
    // Clean prior artifacts in the testbench workspace.
    foreach ([config_path('evolayer.php'), base_path('bootstrap/cache/ontology.php')] as $p) {
        if (File::exists($p)) {
            File::delete($p);
        }
    }

    $this->artisan('evolayer:install', ['--no-seed' => true])
        ->assertSuccessful();

    expect(File::exists(config_path('evolayer.php')))->toBeTrue()
        ->and(File::exists(base_path('bootstrap/cache/ontology.php')))->toBeTrue();
});

test('evolayer:install --no-migrate skips migration', function () {
    $this->artisan('evolayer:install', ['--no-migrate' => true, '--no-seed' => true])
        ->assertSuccessful();
});

test('evolayer:doctor reports the AdminGate, UserResolver, and ontology checks', function () {
    // Ensure ontology is compiled so that check passes.
    $this->artisan('evolayer:ontology:compile', ['--no-erd' => true])->assertSuccessful();

    $this->artisan('evolayer:doctor')
        ->expectsOutputToContain('AdminGate is bound')
        ->expectsOutputToContain('UserResolver is bound')
        ->expectsOutputToContain('Ontology compiled')
        ->assertSuccessful();
});

test('evolayer:doctor flags a custom AdminGate binding distinctly from the default', function () {
    app()->instance(AdminGate::class, new class implements AdminGate
    {
        public function isAdmin(?Authenticatable $user): bool
        {
            return false;
        }

        public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool
        {
            return false;
        }
    });

    $this->artisan('evolayer:doctor')
        ->expectsOutputToContain('custom implementation')
        ->assertSuccessful();
});

test('evolayer:doctor --strict exits failure when any check is advisory', function () {
    // Force the ontology-compiled check to be advisory by removing the artifact.
    $cached = base_path('bootstrap/cache/ontology.php');
    if (File::exists($cached)) {
        File::delete($cached);
    }

    $this->artisan('evolayer:doctor', ['--strict' => true])
        ->expectsOutputToContain('advisory item(s)')
        ->assertExitCode(1);
});

test('evolayer:doctor without --strict stays informational and exits success even with advisories', function () {
    $cached = base_path('bootstrap/cache/ontology.php');
    if (File::exists($cached)) {
        File::delete($cached);
    }

    $this->artisan('evolayer:doctor')
        ->expectsOutputToContain('advisory item(s)')
        ->assertExitCode(0);
});

test('evolayer:doctor production fails on an unallowlisted host package route collision', function () {
    Route::get('/contact', fn () => 'host')->name('host.contact');

    $exitCode = Artisan::call('evolayer:doctor', [
        '--production' => true,
        '--json' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('host-shadows-package')
        ->and($output)->toContain('route-collision:');
});

test('evolayer:doctor production rejects a known-public contact evidence disk', function () {
    config()->set('evolayer.base.features.contact_attachments', true);
    config()->set('media-library.disk_name', 'public');
    config()->set('filesystems.disks.public', [
        'driver' => 'local',
        'root' => public_path('storage'),
        'visibility' => 'public',
    ]);

    Artisan::call('evolayer:doctor', ['--production' => true, '--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Contact evidence storage is known-public')
        ->and($output)->toContain('Configure the effective medialibrary disk as private');
});

test('evolayer:doctor production accepts a non-public contact evidence disk', function () {
    config()->set('evolayer.base.features.contact_attachments', true);
    config()->set('media-library.disk_name', 'private-evidence');
    config()->set('filesystems.disks.private-evidence', [
        'driver' => 'local',
        'root' => storage_path('app/private-evidence'),
        'visibility' => 'private',
    ]);

    Artisan::call('evolayer:doctor', ['--production' => true, '--json' => true]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $check = collect($payload['checks'])->firstWhere('label', 'Contact evidence storage is not known-public');

    expect($check['ok'])->toBeTrue()
        ->and($check['corrective_action'])->toBeNull();
});
