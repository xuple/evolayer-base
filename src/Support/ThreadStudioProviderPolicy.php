<?php

namespace Xuple\EvoLayer\Base\Support;

/**
 * Feature-specific product policy for ThreadStudio's provider eligibility.
 *
 * This class is the consumer-facing API for "which providers may a host
 * configure as `AI_THREAD_STUDIO_PROVIDER`?" and "why is this one rejected?".
 * Per ADR-019 it is the seam consumers depend on — not
 * `AiFeatureConfig::supportedProviders()` directly — because product
 * decisions (curation + per-provider rejection reasons, and later
 * capability-ledger-driven gating) belong here, not in the config layer
 * that owns the curated list and labels.
 */
class ThreadStudioProviderPolicy
{
    /**
     * Providers diagnostic-known but blocked for ThreadStudio, with the reason.
     * Reclassified by ADR-020 (not deleted — still exercisable via the broad
     * smoke/probe diagnostics).
     *
     * @var array<string, string>
     */
    protected const BLOCKED = [
        'anthropic' => 'Anthropic is known to the diagnostic layer but is blocked for ThreadStudio because structured streaming currently emits no usable TextDelta events.',
    ];

    /**
     * OpenAI-compatible router / probe candidates — not directly verified per
     * provider, so not curated for ThreadStudio runtime selection (ADR-020).
     *
     * @var array<int, string>
     */
    protected const CANDIDATES = ['nvidia', 'opencode', 'openrouter'];

    public function __construct(
        protected readonly ThreadStudioAiConfig $config,
    ) {}

    /**
     * Providers the policy allows as ThreadStudio's `AI_THREAD_STUDIO_PROVIDER`
     * setting and accepts in request validation — the directly-verified
     * curated roster (ADR-020: Gemini + OpenAI).
     *
     * @return array<int, string>
     */
    public function curatedProviders(): array
    {
        return $this->config->supportedProviders();
    }

    /**
     * Explain whether a provider may be used for ThreadStudio, and why not.
     *
     * Provider-level only today. Model-level / capability-ledger gating
     * (`availability(provider, model)`) is adaptive mode and stays deferred.
     */
    public function explain(string $provider): ProviderAvailability
    {
        if (in_array($provider, $this->curatedProviders(), true)) {
            return ProviderAvailability::curated($provider);
        }

        if (isset(self::BLOCKED[$provider])) {
            return ProviderAvailability::blocked($provider, self::BLOCKED[$provider]);
        }

        if (in_array($provider, self::CANDIDATES, true)) {
            return ProviderAvailability::candidate(
                $provider,
                ucfirst($provider).' is an OpenAI-compatible router/probe candidate for ThreadStudio, not a directly-verified provider. '
                .'Exercise it with `evolayer:ai:stream-smoke` / `evolayer:ai:probe`; it is not selectable in ThreadStudio until verified. '
                .'Curated providers are: '.implode(', ', $this->curatedProviders()).'.',
            );
        }

        return ProviderAvailability::unknown(
            $provider,
            "Unknown ThreadStudio provider [{$provider}]. Curated providers are: ".implode(', ', $this->curatedProviders()).'.',
        );
    }
}
