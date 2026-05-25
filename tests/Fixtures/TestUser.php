<?php

namespace Xuple\EvoLayer\Base\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Stand-in User model for package tests. The host project supplies its own
 * User model in production — this fixture exists only so the package can
 * exercise routes/controllers/recorders/migrations against a working users
 * table. Spatie's HasRoles is deliberately omitted; tests fake the
 * AdminGate binding instead.
 *
 * @method static \Xuple\EvoLayer\Base\Tests\Fixtures\TestUserFactory factory()
 */
class TestUser extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): TestUserFactory
    {
        return TestUserFactory::new();
    }
}
