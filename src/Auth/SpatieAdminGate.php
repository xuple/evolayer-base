<?php

namespace Xuple\EvoLayer\Base\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Xuple\EvoLayer\Base\Contracts\AdminGate;

class SpatieAdminGate implements AdminGate
{
    public function isAdmin(?Authenticatable $user): bool
    {
        return $this->can($user, 'evolayer.admin');
    }

    public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool
    {
        if ($user === null) {
            return false;
        }

        // `evolayer.admin` is the package-defined ability for the canonical admin role check.
        // Resolved via spatie/laravel-permission's hasRole() when the user supports it; otherwise
        // we deny. This avoids routing the special role-check ability through Laravel's Gate
        // facade (which fails for users that don't implement Authorizable).
        if ($ability === 'evolayer.admin') {
            return method_exists($user, 'hasRole') && (bool) $user->hasRole('admin');
        }

        // Arbitrary abilities go through Laravel's Gate facade, allowing hosts to register
        // their own policies / gates for fine-grained authorisation.
        return $resource === null
            ? Gate::forUser($user)->allows($ability)
            : Gate::forUser($user)->allows($ability, $resource);
    }
}
