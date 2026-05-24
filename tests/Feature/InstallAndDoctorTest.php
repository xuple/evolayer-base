<?php

use EvoDevOps\Base\Contracts\AdminGate;
use Illuminate\Support\Facades\File;

test('evodevops:install publishes assets, migrates, and compiles the ontology', function () {
    // Clean prior artifacts in the testbench workspace.
    foreach ([config_path('evodevops.php'), base_path('bootstrap/cache/ontology.php')] as $p) {
        if (File::exists($p)) {
            File::delete($p);
        }
    }

    $this->artisan('evodevops:install', ['--no-seed' => true])
        ->assertSuccessful();

    expect(File::exists(config_path('evodevops.php')))->toBeTrue()
        ->and(File::exists(base_path('bootstrap/cache/ontology.php')))->toBeTrue();
});

test('evodevops:install --no-migrate skips migration', function () {
    $this->artisan('evodevops:install', ['--no-migrate' => true, '--no-seed' => true])
        ->assertSuccessful();
});

test('evodevops:doctor reports the AdminGate, UserResolver, and ontology checks', function () {
    // Ensure ontology is compiled so that check passes.
    $this->artisan('ontology:compile', ['--no-erd' => true])->assertSuccessful();

    $this->artisan('evodevops:doctor')
        ->expectsOutputToContain('AdminGate is bound')
        ->expectsOutputToContain('UserResolver is bound')
        ->expectsOutputToContain('Ontology compiled')
        ->assertSuccessful();
});

test('evodevops:doctor flags a custom AdminGate binding distinctly from the default', function () {
    app()->instance(AdminGate::class, new class implements AdminGate
    {
        public function isAdmin(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
        {
            return false;
        }

        public function can(?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability, mixed $resource = null): bool
        {
            return false;
        }
    });

    $this->artisan('evodevops:doctor')
        ->expectsOutputToContain('custom implementation')
        ->assertSuccessful();
});
