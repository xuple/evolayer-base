<?php

namespace EvoDevOps\Base\Auth;

use EvoDevOps\Base\Contracts\AdminGate;
use Illuminate\Contracts\Auth\Authenticatable;

class SpatieAdminGate implements AdminGate
{
    public function isAdmin(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) $user->hasRole('admin');
    }
}
