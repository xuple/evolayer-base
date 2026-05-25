<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Gate;
use Xuple\EvoLayer\Base\Auth\DefaultUserResolver;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

test('the AdminGate contract resolves to the Spatie-backed default implementation', function () {
    expect(app(AdminGate::class))->toBeInstanceOf(SpatieAdminGate::class);
});

test('the UserResolver contract resolves to the default auth-guard implementation', function () {
    expect(app(UserResolver::class))->toBeInstanceOf(DefaultUserResolver::class);
});

test('the evolayer:doctor command is registered with the artisan kernel', function () {
    $kernel = app(Kernel::class);

    expect($kernel->all())->toHaveKey('evolayer:doctor');
});

test('evolayer:doctor exits successfully in the Phase B scaffold', function () {
    $this->artisan('evolayer:doctor')
        ->assertSuccessful();
});

test('AdminGate returns false for a null user', function () {
    expect(app(AdminGate::class)->isAdmin(null))->toBeFalse()
        ->and(app(AdminGate::class)->can(null, 'evolayer.admin'))->toBeFalse()
        ->and(app(AdminGate::class)->can(null, 'arbitrary.ability'))->toBeFalse();
});

test('AdminGate::isAdmin delegates to can(evolayer.admin)', function () {
    $user = TestUser::factory()->create();

    // The default SpatieAdminGate denies because TestUser does not have HasRoles trait.
    expect(app(AdminGate::class)->can($user, 'evolayer.admin'))->toBeFalse()
        ->and(app(AdminGate::class)->isAdmin($user))->toBeFalse();
});

test('AdminGate::can routes arbitrary abilities through Laravel Gate', function () {
    Gate::define('test-ability', fn () => true);
    Gate::define('denied-ability', fn () => false);

    $user = TestUser::factory()->create();

    expect(app(AdminGate::class)->can($user, 'test-ability'))->toBeTrue()
        ->and(app(AdminGate::class)->can($user, 'denied-ability'))->toBeFalse();
});

test('SpatieAdminGate returns false for a user without hasRole capability', function () {
    $user = new class implements Authenticatable
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
