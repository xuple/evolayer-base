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
     * The curated ThreadStudio provider roster — providers with **directly
     * verified** provider-specific structured-streaming support (ADR-020,
     * D-prime). This is NOT every SDK-known or diagnostically-smokable
     * provider.
     *
     * Per ADR-019, consumers deciding ThreadStudio eligibility should depend
     * on {@see ThreadStudioProviderPolicy::curatedProviders()} (the
     * feature-policy seam), not call this method directly.
     *
     * Membership means a provider passed structured streaming on its own
     * matrix row — Gemini and OpenAI today. Notably NOT here:
     * - `anthropic` — diagnostic-known but **blocked/pending**: its structured
     *   streaming currently emits no usable `TextDelta` events.
     * - `nvidia`, `opencode`, `openrouter` — OpenAI-compatible **router
     *   candidates**, probeable but not directly verified per provider.
     * Their labels, the OpenCode catalogue, and the capability ledger are
     * retained as probe/router infrastructure (and for future adaptive mode);
     * they are simply not curated for ThreadStudio runtime selection.
     *
     * Smoke/probe diagnostics stay broad and accept any Lab provider, so a
     * router or Anthropic can still be exercised via `evolayer:ai:stream-smoke`
     * / `evolayer:ai:probe`. Passing a smoke is eligibility for consideration,
     * not automatic curation. See ADR-019 (classification) and ADR-020 (this
     * roster).
     *
     * @return array<int, string>
     */
    public function supportedProviders(): array
    {
        return ['gemini', 'openai'];
    }

    /**
     * Display labels. Includes both curated providers (gemini, openai) and
     * the reclassified router/blocked candidates (anthropic, nvidia, opencode,
     * openrouter) — the latter are retained for probe tooling, diagnostics,
     * and future adaptive mode, NOT because they are curated. `providerLabel()`
     * only resolves labels for curated providers (it routes through
     * `provider()`, which rejects non-curated names).
     *
     * @return array<string, string>
     */
    protected function providerLabels(): array
    {
        return [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI',
            // Reclassified (not curated) — retained as probe/router metadata:
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

        if (! in_array($provider, $this->supportedProviders(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported %s provider [%s]. Supported providers are: %s.',
                $this->featureName,
                $provider,
                implode(', ', $this->supportedProviders()),
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
        $defaultModel = config("ai.providers.{$provider}.models.text.default");

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
