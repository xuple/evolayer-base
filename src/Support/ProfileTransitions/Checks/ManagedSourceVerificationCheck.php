<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions\Checks;

use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;

final class ManagedSourceVerificationCheck implements ProfileVerificationCheck
{
    public function id(): string
    {
        return 'base.managed-source';
    }

    public function apiVersion(): int
    {
        return ProfileVerificationManager::CONTRACT_VERSION;
    }

    public function capability(): string
    {
        return 'managed-source';
    }

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult
    {
        return $context->status['managed_findings'] === []
            ? ProfileVerificationResult::pass()
            : ProfileVerificationResult::fail('managed-source-drift', 'Run profile status and reconcile each managed-source ownership finding before rerunning verification.');
    }

    public function fingerprint(ProfileVerificationContext $context): array
    {
        return ['manifest_sha256' => $context->bindings['manifest'], 'managed_state_sha256' => $context->bindings['managed']];
    }
}
