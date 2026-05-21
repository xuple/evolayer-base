<?php

use EvoDevOps\Base\Auth\DefaultUserResolver;
use EvoDevOps\Base\Auth\SpatieAdminGate;
use EvoDevOps\Base\Console\Commands\DoctorCommand;
use EvoDevOps\Base\Contracts\AdminGate;
use EvoDevOps\Base\Contracts\UserResolver;

test('the AdminGate contract resolves to the Spatie-backed default implementation', function () {
    expect(app(AdminGate::class))->toBeInstanceOf(SpatieAdminGate::class);
});

test('the UserResolver contract resolves to the default auth-guard implementation', function () {
    expect(app(UserResolver::class))->toBeInstanceOf(DefaultUserResolver::class);
});

test('the evodevops:doctor command is registered with the artisan kernel', function () {
    $kernel = app(Illuminate\Contracts\Console\Kernel::class);

    expect($kernel->all())->toHaveKey('evodevops:doctor');
});

test('evodevops:doctor exits successfully in the Phase B scaffold', function () {
    $this->artisan('evodevops:doctor')
        ->assertSuccessful();
});

test('AdminGate returns false for a null user', function () {
    expect(app(AdminGate::class)->isAdmin(null))->toBeFalse();
});

test('SpatieAdminGate returns false for a user without hasRole capability', function () {
    $user = new class implements Illuminate\Contracts\Auth\Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    expect(app(AdminGate::class)->isAdmin($user))->toBeFalse();
});
