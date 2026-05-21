<?php

namespace EvoDevOps\Base\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserResolver
{
    public function current(): ?Authenticatable;
}
