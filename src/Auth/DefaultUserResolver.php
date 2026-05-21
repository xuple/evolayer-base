<?php

namespace EvoDevOps\Base\Auth;

use EvoDevOps\Base\Contracts\UserResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class DefaultUserResolver implements UserResolver
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function current(): ?Authenticatable
    {
        return $this->auth->guard()->user();
    }
}
