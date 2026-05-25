<?php

use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;
use Xuple\EvoLayer\Base\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Returns an admin TestUser and binds the AdminGate to a fake that
 * recognises this exact user as admin. Tests then `actingAs($admin)`.
 */
function makeAdmin(): TestUser
{
    $admin = TestUser::factory()->create();

    app()->instance(AdminGate::class, new class($admin) implements AdminGate {
        public function __construct(private readonly TestUser $admin) {}

        public function isAdmin(?Authenticatable $user): bool
        {
            return $this->can($user, 'evodevops.admin');
        }

        public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool
        {
            // Test fake: only the seeded admin authorises for any ability.
            return $user !== null && $user->getAuthIdentifier() === $this->admin->getAuthIdentifier();
        }
    });

    return $admin;
}
