<?php

use Xuple\EvoLayer\Base\Auth\DefaultUserResolver;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Console\Commands\DoctorCommand;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;

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
    expect(app(AdminGate::class)->isAdmin(null))->toBeFalse()
        ->and(app(AdminGate::class)->can(null, 'evodevops.admin'))->toBeFalse()
        ->and(app(AdminGate::class)->can(null, 'arbitrary.ability'))->toBeFalse();
});

test('AdminGate::isAdmin delegates to can(evodevops.admin)', function () {
    $user = \Xuple\EvoLayer\Base\Tests\Fixtures\TestUser::factory()->create();

    // The default SpatieAdminGate denies because TestUser does not have HasRoles trait.
    expect(app(AdminGate::class)->can($user, 'evodevops.admin'))->toBeFalse()
        ->and(app(AdminGate::class)->isAdmin($user))->toBeFalse();
});

test('AdminGate::can routes arbitrary abilities through Laravel Gate', function () {
    \Illuminate\Support\Facades\Gate::define('test-ability', fn () => true);
    \Illuminate\Support\Facades\Gate::define('denied-ability', fn () => false);

    $user = \Xuple\EvoLayer\Base\Tests\Fixtures\TestUser::factory()->create();

    expect(app(AdminGate::class)->can($user, 'test-ability'))->toBeTrue()
        ->and(app(AdminGate::class)->can($user, 'denied-ability'))->toBeFalse();
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
