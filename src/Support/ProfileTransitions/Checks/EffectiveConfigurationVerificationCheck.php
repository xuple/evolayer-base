<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks;

use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;

final class EffectiveConfigurationVerificationCheck implements ProfileVerificationCheck
{
    public function id(): string
    {
        return 'base.effective-configuration';
    }

    public function apiVersion(): int
    {
        return ProfileVerificationManager::CONTRACT_VERSION;
    }

    public function capability(): string
    {
        return 'effective-configuration';
    }

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult
    {
        return $context->status['effective_mismatches'] === []
            ? ProfileVerificationResult::pass()
            : ProfileVerificationResult::fail('effective-configuration-drift', 'Correct the deployment projection, clear or rebuild the configuration cache, then rerun profile verification.');
    }

    public function fingerprint(ProfileVerificationContext $context): array
    {
        return ['effective_state_sha256' => $context->bindings['effective']];
    }
}
