<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks;

use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;

final class CommittedIntentVerificationCheck implements ProfileVerificationCheck
{
    public function id(): string
    {
        return 'base.committed-intent';
    }

    public function apiVersion(): int
    {
        return ProfileVerificationManager::CONTRACT_VERSION;
    }

    public function capability(): string
    {
        return 'committed-intent';
    }

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult
    {
        return ProfileVerificationResult::pass();
    }

    public function fingerprint(ProfileVerificationContext $context): array
    {
        return ['intent_sha256' => $context->bindings['intent']];
    }
}
