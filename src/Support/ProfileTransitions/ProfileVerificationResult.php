<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use InvalidArgumentException;

final readonly class ProfileVerificationResult
{
    public function __construct(
        public bool $passed,
        public ?string $errorCode = null,
        public ?string $correctiveAction = null,
    ) {
        if ($passed && ($errorCode !== null || $correctiveAction !== null)) {
            throw new InvalidArgumentException('Passing verification results cannot include an error or corrective action.');
        }

        if (! $passed
            && (preg_match('/^[a-z][a-z0-9-]*$/', (string) $errorCode) !== 1
                || trim((string) $correctiveAction) === '')) {
            throw new InvalidArgumentException('Failing verification results require a stable error code and redacted corrective action.');
        }
    }

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $errorCode, string $correctiveAction): self
    {
        return new self(false, $errorCode, $correctiveAction);
    }
}
