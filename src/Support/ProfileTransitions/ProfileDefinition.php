<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use InvalidArgumentException;

final readonly class ProfileDefinition
{
    /**
     * @param  array<string, bool>  $examples
     * @param  array<string, bool>  $features
     * @param  list<string>  $requiredCapabilities
     * @param  list<string>  $allowedOverrides
     * @param  list<string>  $verificationRequirements
     */
    public function __construct(
        public string $id,
        public int $schemaVersion,
        public array $examples,
        public array $features,
        public array $requiredCapabilities,
        public array $allowedOverrides,
        public array $verificationRequirements,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
            throw new InvalidArgumentException("Invalid profile ID [{$id}].");
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Profile schema versions must be positive integers.');
        }
    }
}
