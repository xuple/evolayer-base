<?php

namespace Xuple\EvoLayer\Base\Contracts;

use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;

interface ProfileTransitionContributor
{
    public function id(): string;

    public function apiVersion(): int;

    /** @return list<string> */
    public function provides(): array;

    /** @return list<string> */
    public function requires(): array;

    public function priority(): int;

    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void;
}
