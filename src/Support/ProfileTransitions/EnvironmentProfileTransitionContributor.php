<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;

final class EnvironmentProfileTransitionContributor implements ProfileTransitionContributor
{
    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
    {
        $environment = file_get_contents($context->environmentPath);

        if ($environment === false) {
            $plan->conflict("Unable to read environment file [{$context->environmentPath}].");

            return;
        }

        foreach ($context->targetExamples as $key => $enabled) {
            $environment = $this->setValue(
                $environment,
                'EVOLAYER_BASE_EXAMPLE_'.strtoupper($key),
                $enabled ? 'true' : 'false',
                $plan,
            );
        }

        $plan->replace($context->environmentPath, $environment);
    }

    private function setValue(string $environment, string $key, string $value, ProfileTransitionPlan $plan): string
    {
        $pattern = '/^(?:export\s+)?'.preg_quote($key, '/').'\s*=.*$/m';
        $matches = preg_match_all($pattern, $environment);

        if ($matches === false) {
            $plan->conflict("Unable to parse [{$key}] in the environment file.");

            return $environment;
        }

        if ($matches > 1) {
            $plan->conflict("Environment key [{$key}] is defined more than once.");

            return $environment;
        }

        $line = "{$key}={$value}";

        if ($matches === 1) {
            return (string) preg_replace($pattern, $line, $environment, 1);
        }

        return rtrim($environment, "\n")."\n{$line}\n";
    }
}
