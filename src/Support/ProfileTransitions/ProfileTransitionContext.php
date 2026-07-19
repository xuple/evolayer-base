<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ProfileTransitionContext
{
    /**
     * @param  array<string, bool>  $currentExamples
     * @param  array<string, bool>  $targetExamples
     */
    public function __construct(
        public string $profile,
        public string $environmentPath,
        public array $currentExamples,
        public array $targetExamples,
    ) {}
}
