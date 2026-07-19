<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use RuntimeException;

final class ProfileTransitionConflict extends RuntimeException
{
    /** @param  list<string>  $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('Profile transition preflight found '.count($conflicts).' conflict(s).');
    }
}
