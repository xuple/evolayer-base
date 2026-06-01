<?php

namespace Xuple\EvoLayer\Base\Support;

/**
 * The result of asking {@see ThreadStudioProviderPolicy::explain()} whether a
 * provider may be used for ThreadStudio, and — when it may not — why.
 *
 * This is a product-policy answer, not a capability observation: the reason
 * a provider is rejected (blocked / router-candidate / unknown) is a
 * deliberate curation decision (ADR-020), distinct from what a probe observed
 * about it (which lives on the `AiCapability` ledger). The `message` is
 * human/agent-facing and is surfaced as the request-validation rejection.
 */
final class ProviderAvailability
{
    public const STATUS_CURATED = 'curated';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CANDIDATE = 'candidate';

    public const STATUS_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $provider,
        public readonly bool $allowed,
        public readonly string $status,
        public readonly string $message,
    ) {}

    public static function curated(string $provider): self
    {
        return new self($provider, true, self::STATUS_CURATED, "Provider [{$provider}] is curated for ThreadStudio.");
    }

    public static function blocked(string $provider, string $message): self
    {
        return new self($provider, false, self::STATUS_BLOCKED, $message);
    }

    public static function candidate(string $provider, string $message): self
    {
        return new self($provider, false, self::STATUS_CANDIDATE, $message);
    }

    public static function unknown(string $provider, string $message): self
    {
        return new self($provider, false, self::STATUS_UNKNOWN, $message);
    }
}
