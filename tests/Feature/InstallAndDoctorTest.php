<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
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
