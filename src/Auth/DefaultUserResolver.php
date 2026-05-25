<?php

namespace Xuple\EvoLayer\Base\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Xuple\EvoLayer\Base\Contracts\UserResolver;

class DefaultUserResolver implements UserResolver
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function current(): ?Authenticatable
    {
        return $this->auth->guard()->user();
    }
}
