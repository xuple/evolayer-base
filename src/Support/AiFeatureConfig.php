<?php

namespace Xuple\EvoLayer\Base\Support;

use InvalidArgumentException;

abstract class AiFeatureConfig
{
    public function __construct(
        protected string $featureKey,
        protected string $featureName,
    ) {}

    /**
     * The runtime-approved ThreadStudio provider roster — providers with
     * **directly verified** provider-specific structured-streaming support
     * (ADR-020, the Verified Runtime Strategy). This is NOT every SDK-known or
     * diagnostically-eligible provider.
     *
     * Feature-generic: this base method owns the runtime-approved list; the
     * consumer-facing seam is {@see ThreadStudioProviderPolicy::runtimeApprovedProviders()}
     * (per ADR-019), which consumers should depend on rather than calling this
     * directly.
     *
     * Membership means a provider passed structured streaming on its own
     * matrix row — Gemini and OpenAI today. Notably NOT here:
     * - `anthropic` — diagnostic-eligible but **blocked / pending re-verification**:
     *   its structured streaming currently emits no usable `TextDelta` events.
     * - `nvidia`, `opencode`, `openrouter` — OpenAI-compatible **router-backed
     *   candidates**, probeable but not directly verified per provider.
     * Their labels, the OpenCode catalogue, and the capability ledger are
     * retained as probe/router infrastructure (and for future adaptive mode);
     * they are simply not runtime-approved for ThreadStudio selection.
     *
     * Smoke/probe diagnostics stay broad and accept any Lab provider, so a
     * router or Anthropic can still be exercised via `evolayer:ai:stream-check`
     * / `evolayer:ai:probe`. Passing a smoke is eligibility for consideration,
     * not automatic runtime approval. See ADR-019 (classification) and ADR-020
     * (this roster).
     *
     * @return array<int, string>
     */
    public function runtimeApprovedProviders(): array
    {
        return ['gemini', 'openai'];
    }

    /**
     * Display labels. Includes both runtime-approved providers (gemini, openai)
     * and the reclassified router-backed/blocked candidates (anthropic, nvidia,
     * opencode, openrouter) — the latter are retained for probe tooling,
     * diagnostics, and future adaptive mode, NOT because they are runtime-approved.
     * `providerLabel()` only resolves labels for runtime-approved providers (it
     * routes through `provider()`, which rejects non-approved names).
     *
     * @return array<string, string>
     */
    protected function providerLabels(): array
    {
        return [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI',
            // Reclassified (not runtime-approved) — retained as probe/router metadata:
            'anthropic' => 'Anthropic',
            'nvidia' => 'NVIDIA',
            'opencode' => 'OpenCode Go',
            'openrouter' => 'OpenRouter',
        ];
    }

    public function provider(?string $provider = null): string
    {
        $provider = $provider !== null && $provider !== ''
            ? $provider
            : (string) config("ai.{$this->featureKey}.provider", 'gemini');

        if (! in_array($provider, $this->runtimeApprovedProviders(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Provider [%s] is not runtime-approved for %s. Runtime-approved providers are: %s.',
                $provider,
                $this->featureName,
                implode(', ', $this->runtimeApprovedProviders()),
            ));
        }

        return $provider;
    }

    public function providerLabel(?string $provider = null): string
    {
        $labels = $this->providerLabels();
        $provider = $this->provider($provider);

        return $labels[$provider] ?? $provider;
    }

    public function defaultModel(?string $provider = null): string
    {
        $provider = $this->provider($provider);

        // Read the model default from the package's own 'evolayer-ai' namespace
        // first. `mergeConfigFrom(evolayer-ai.php, 'ai')` is shallow at
        // `providers.*`, so the laravel/ai SDK's bare provider blocks win the
        // merge and the package's `models.text.default` never lands in
        // config('ai') — leaving this as the 'provider default' sentinel and
        // breaking ThreadStudio's UI compose for the default provider. The
        // 'evolayer-ai' namespace carries the package's full provider config.
        $defaultModel = config("evolayer-ai.providers.{$provider}.models.text.default")
            ?? config("ai.providers.{$provider}.models.text.default");

        return is_string($defaultModel) && $defaultModel !== '' ? $defaultModel : 'provider default';
    }

    /**
     * @return array{name: string, source: 'provider_default'}
     */
    public function modelDisplay(?string $provider = null): array
    {
        return [
            'name' => $this->defaultModel($provider),
            'source' => 'provider_default',
        ];
    }

    public function timeout(): int
    {
        return (int) config("ai.{$this->featureKey}.timeout", 60);
    }

    /**
     * @return array{name: string, label: string, model: array{name: string, source: 'provider_default'}}
     */
    public function metadata(?string $provider = null): array
    {
        $provider = $this->provider($provider);

        return [
            'name' => $provider,
            'label' => $this->providerLabel($provider),
            'model' => $this->modelDisplay($provider),
        ];
    }
}
