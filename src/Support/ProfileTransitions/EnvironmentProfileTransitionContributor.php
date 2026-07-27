<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;

final class EnvironmentProfileTransitionContributor implements ProfileTransitionContributor
{
    public function __construct(private readonly ManagedPathPolicy $paths) {}

    public function id(): string
    {
        return 'base.environment';
    }

    public function apiVersion(): int
    {
        return 1;
    }

    public function provides(): array
    {
        return ['profile.environment-projection'];
    }

    public function requires(): array
    {
        return [];
    }

    public function priority(): int
    {
        return 100;
    }

    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
    {
        if (! $context->writeEnvironment) {
            return;
        }

        try {
            $this->paths->assertRegularFile($context->environmentPath);
            $precondition = FilePrecondition::capture($context->environmentPath);
        } catch (ManagedPathException $exception) {
            $plan->conflict($exception->getMessage());

            return;
        }

        $environment = file_get_contents($context->environmentPath);

        if ($environment === false) {
            $plan->conflict("Unable to read environment file [{$context->environmentPath}].");

            return;
        }

        $bom = str_starts_with($environment, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
        $environment = $bom === '' ? $environment : substr($environment, 3);
        $newline = str_contains($environment, "\r\n") ? "\r\n" : "\n";

        $environment = $this->setValue(
            $environment,
            'EVOLAYER_BASE_PROFILE',
            $context->profile,
            $plan,
            $newline,
        );

        foreach ($context->targetExamples as $key => $enabled) {
            $environment = $this->setValue(
                $environment,
                'EVOLAYER_BASE_EXAMPLE_'.strtoupper($key),
                $enabled ? 'true' : 'false',
                $plan,
                $newline,
            );
        }

        foreach ($context->targetFeatures as $key => $enabled) {
            $environment = $this->setValue(
                $environment,
                'EVOLAYER_BASE_FEATURE_'.strtoupper($key),
                $enabled ? 'true' : 'false',
                $plan,
                $newline,
            );
        }

        $plan->replace($context->environmentPath, $bom.$environment, $precondition);
    }

    private function setValue(
        string $environment,
        string $key,
        string $value,
        ProfileTransitionPlan $plan,
        string $newline,
    ): string {
        $quotedKey = '(?:'.preg_quote($key, '/').'|"'.preg_quote($key, '/').'"|\''.preg_quote($key, '/').'\')';
        $pattern = '/^[\t ]*(?:export[\t ]+)?'.$quotedKey.'[\t ]*=[^\r\n]*/m';
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

        return rtrim($environment, "\r\n").$newline.$line.$newline;
    }
}
