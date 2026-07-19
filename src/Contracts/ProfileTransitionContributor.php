<?php

namespace Xuple\EvoLayer\Base\Contracts;

use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;

interface ProfileTransitionContributor
{
    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void;
}
