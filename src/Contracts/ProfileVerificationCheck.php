<?php

namespace Xuple\EvoLayer\Base\Contracts;

use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationResult;

interface ProfileVerificationCheck
{
    public function id(): string;

    public function apiVersion(): int;

    public function capability(): string;

    public function verify(ProfileVerificationContext $context): ProfileVerificationResult;

    /** @return array<string, mixed> */
    public function fingerprint(ProfileVerificationContext $context): array;
}
