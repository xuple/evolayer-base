<?php

namespace Xuple\EvoLayer\Base\Support;

/**
 * Feature-specific product policy for ThreadStudio's provider eligibility.
 *
 * This class is the consumer-facing API for "which providers may a host
 * configure as `AI_THREAD_STUDIO_PROVIDER`?". It wraps the lower-level
 * curated provider list today and is the future home for explain() (per-
 * provider rejection messages) and adaptive-mode lookups against the
 * capability ledger.
 *
 * Per ADR-019, this class is the seam that consumers should depend on —
 * not `AiFeatureConfig::supportedProviders()` directly. The split exists
 * because future policy decisions (capability-ledger-driven gating, mode
 * toggles, per-provider rejection messages) belong here, not in the
 * config layer that owns the curated list and labels.
 *
 * The first implementation delegates to `ThreadStudioAiConfig::supportedProviders()`
 * so behaviour is preserved exactly. Subsequent ADRs / commits will widen
 * this class without disturbing its callers.
 */
class ThreadStudioProviderPolicy
{
    public function __construct(
        protected readonly ThreadStudioAiConfig $config,
    ) {}

    /**
     * Providers the policy currently allows as ThreadStudio's
     * `AI_THREAD_STUDIO_PROVIDER` setting and accepts in request validation.
     *
     * Today this is the curated list owned by ThreadStudioAiConfig. Per
     * ADR-019 the criteria for membership are deliberate product decisions
     * (matrix-verified OR documented OpenAI-compatible router path), not
     * purely the output of smoke results — `evolayer:ai:stream-smoke`
     * passing is eligibility for consideration, not automatic curation.
     *
     * @return array<int, string>
     */
    public function curatedProviders(): array
    {
        return $this->config->supportedProviders();
    }
}
