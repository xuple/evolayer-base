<?php

namespace EvoDevOps\Base\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AdminGate
{
    public function isAdmin(?Authenticatable $user): bool;
}
