<?php

use EvoDevOps\Base\Contracts\AdminGate;
use EvoDevOps\Base\Tests\Fixtures\TestUser;
use EvoDevOps\Base\Tests\TestCase;
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
            return $user !== null && $user->getAuthIdentifier() === $this->admin->getAuthIdentifier();
        }
    });

    return $admin;
}
