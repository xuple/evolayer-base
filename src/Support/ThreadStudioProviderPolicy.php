<?php

namespace Xuple\EvoLayer\Base\Support;

/**
 * Feature-specific product policy for ThreadStudio's provider eligibility.
 *
 * This class is the consumer-facing API for "which providers may a host
 * configure as `AI_THREAD_STUDIO_PROVIDER`?" and "why is this one rejected?".
 * Per ADR-019 it is the seam consumers depend on — not
 * `AiFeatureConfig::runtimeApprovedProviders()` directly — because product
 * decisions (runtime approval + per-provider rejection reasons, and later
 * capability-ledger-driven gating) belong here, not in the config layer
 * that owns the runtime-approved list and labels.
 */
class ThreadStudioProviderPolicy
{
    /**
     * Providers diagnostic-eligible but blocked for ThreadStudio, with the reason.
     * Reclassified by ADR-020 (not deleted — still exercisable via the broad
     * smoke/probe diagnostics).
     *
     * @var array<string, string>
     */
    protected const BLOCKED = [
        'anthropic' => 'Anthropic is diagnostic-eligible but blocked for ThreadStudio runtime and pending re-verification because structured streaming currently emits no usable TextDelta events.',
    ];

    /**
     * OpenAI-compatible router-backed probe candidates — not directly verified per
     * provider, so not runtime-approved for ThreadStudio selection (ADR-020).
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
     * runtime-approved roster (ADR-020: Gemini + OpenAI).
     *
     * @return array<int, string>
     */
    public function runtimeApprovedProviders(): array
    {
        return $this->config->runtimeApprovedProviders();
    }

    /**
     * Explain whether a provider may be used for ThreadStudio, and why not.
     *
     * Provider-level only today. Model-level / capability-ledger gating
     * (`availability(provider, model)`) is adaptive mode and stays deferred.
     */
    public function explain(string $provider): ProviderAvailability
    {
        if (in_array($provider, $this->runtimeApprovedProviders(), true)) {
            return ProviderAvailability::runtimeApproved($provider);
        }

        if (isset(self::BLOCKED[$provider])) {
            return ProviderAvailability::blocked($provider, self::BLOCKED[$provider]);
        }

        if (in_array($provider, self::CANDIDATES, true)) {
            return ProviderAvailability::candidate(
                $provider,
                ucfirst($provider).' is an OpenAI-compatible router-backed provider and diagnostic-eligible probe candidate for ThreadStudio, not a directly verified provider. '
                .'Exercise it with `evolayer:ai:stream-check` / `evolayer:ai:probe`; it is not selectable in ThreadStudio until it is directly verified and runtime-approved. '
                .'Runtime-approved providers are: '.implode(', ', $this->runtimeApprovedProviders()).'.',
            );
        }

        return ProviderAvailability::unknown(
            $provider,
            "Unknown ThreadStudio provider [{$provider}]. Runtime-approved providers are: ".implode(', ', $this->runtimeApprovedProviders()).'.',
        );
    }
}
