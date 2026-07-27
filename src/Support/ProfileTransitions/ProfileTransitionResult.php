<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ProfileTransitionResult
{
    /** @param  list<string>  $affectedPaths */
    public function __construct(
        public bool $dryRun,
        public array $affectedPaths,
        public int $replacementCount = 0,
        public int $deletionCount = 0,
    ) {}
}
