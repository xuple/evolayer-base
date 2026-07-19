<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ProfileTransitionResult
{
    /** @param  list<string>  $affectedPaths */
    public function __construct(public bool $dryRun, public array $affectedPaths) {}
}
