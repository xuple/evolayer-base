<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ProfileTransitionContext
{
    /**
     * @param  array<string, bool>  $currentExamples
     * @param  array<string, bool>  $targetExamples
     * @param  array<string, bool>  $currentFeatures
     * @param  array<string, bool>  $targetFeatures
     * @param  array<string, bool>  $exampleOverrides
     * @param  array<string, bool>  $featureOverrides
     */
    public function __construct(
        public string $profile,
        public string $environmentPath,
        public array $currentExamples,
        public array $targetExamples,
        public array $currentFeatures = [],
        public array $targetFeatures = [],
        public ?ProfileDefinition $definition = null,
        public bool $writeEnvironment = true,
        public array $exampleOverrides = [],
        public array $featureOverrides = [],
    ) {}
}
