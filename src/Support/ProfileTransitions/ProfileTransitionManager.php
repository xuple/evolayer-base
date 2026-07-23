<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;

final readonly class ProfileTransitionManager
{
    public const CONTRIBUTOR_API_VERSION = 1;

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

        $contributors = $this->orderedContributors($context, $plan);

        if ($plan->conflicts() !== []) {
            return $plan;
        }

        foreach ($contributors as $contributor) {
            $contributor->contribute($context, $plan);
        }

        return $plan;
    }

    public function execute(ProfileTransitionContext $context, bool $dryRun = false): ProfileTransitionResult
    {
        return $this->executor->execute($this->plan($context), $dryRun);
    }

    /** @return list<ProfileTransitionContributor> */
    private function orderedContributors(ProfileTransitionContext $context, ProfileTransitionPlan $plan): array
    {
        $byId = [];
        $capabilityProviders = [];

        foreach ($this->contributors as $contributor) {
            if (isset($byId[$contributor->id()])) {
                $plan->conflict("Duplicate profile contributor ID [{$contributor->id()}].");

                continue;
            }

            if ($contributor->apiVersion() !== self::CONTRIBUTOR_API_VERSION) {
                $plan->conflict("Profile contributor [{$contributor->id()}] uses unsupported API version [{$contributor->apiVersion()}].");
            }

            $byId[$contributor->id()] = $contributor;

            foreach ($contributor->provides() as $capability) {
                if (isset($capabilityProviders[$capability])) {
                    $plan->conflict("Profile capability [{$capability}] has more than one provider.");

                    continue;
                }

                $capabilityProviders[$capability] = $contributor->id();
            }
        }

        foreach ($context->definition?->requiredCapabilities ?? [] as $capability) {
            if (! isset($capabilityProviders[$capability])) {
                $plan->conflict("Profile [{$context->profile}] requires missing capability [{$capability}].");
            }
        }

        $dependencies = array_fill_keys(array_keys($byId), []);

        foreach ($byId as $id => $contributor) {
            foreach ($contributor->requires() as $capability) {
                $provider = $capabilityProviders[$capability] ?? null;

                if ($provider === null) {
                    $plan->conflict("Profile contributor [{$id}] requires missing capability [{$capability}].");

                    continue;
                }

                if ($provider !== $id) {
                    $dependencies[$id][$provider] = true;
                }
            }
        }

        if ($plan->conflicts() !== []) {
            return [];
        }

        $ordered = [];

        while ($dependencies !== []) {
            $ready = array_keys(array_filter($dependencies, fn (array $required): bool => $required === []));

            if ($ready === []) {
                $plan->conflict('Profile contributor dependencies contain a cycle.');

                return [];
            }

            usort($ready, function (string $left, string $right) use ($byId): int {
                return [$byId[$left]->priority(), $left] <=> [$byId[$right]->priority(), $right];
            });

            foreach ($ready as $id) {
                $ordered[] = $byId[$id];
                unset($dependencies[$id]);

                foreach ($dependencies as &$required) {
                    unset($required[$id]);
                }
                unset($required);
            }
        }

        return $ordered;
    }
}
