<?php

namespace EvoDevOps\Base\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AdminGate
{
    /**
     * Shortcut for the most common check. Equivalent to `can($user, 'evodevops.admin')`.
     */
    public function isAdmin(?Authenticatable $user): bool;

    /**
     * Per-ability and optionally per-resource authorisation.
     *
     * Examples:
     *   `can($user, 'evodevops.admin')`          // role-style check
     *   `can($user, 'edit', $order)`              // policy-style check on a model
     *   `can($user, 'evodevops.commerce.orders.edit')`  // namespaced ability
     *
     * Default implementations should delegate to Laravel's Gate facade.
     */
    public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool;
}
