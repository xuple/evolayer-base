<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;

final readonly class ProfileTransitionManager
{
    public const CONTRIBUTOR_TAG = 'evolayer.profile-transition-contributors';

    /** @var list<ProfileTransitionContributor> */
    private array $contributors;

    public function __construct(
        private ProfileTransitionExecutor $executor,
        ProfileTransitionContributor ...$contributors,
    ) {
        $this->contributors = $contributors;
    }

    public function plan(ProfileTransitionContext $context): ProfileTransitionPlan
    {
        $plan = new ProfileTransitionPlan;

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $plan);
        }

        return $plan;
    }

    public function execute(ProfileTransitionContext $context, bool $dryRun = false): ProfileTransitionResult
    {
        return $this->executor->execute($this->plan($context), $dryRun);
    }
}
