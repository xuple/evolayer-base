<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ExpectedProfileState
{
    /**
     * @param  array<string, bool>  $examples
     * @param  array<string, bool>  $features
     */
    public function __construct(
        public ProfileDefinition $definition,
        public array $examples,
        public array $features,
    ) {}
}
