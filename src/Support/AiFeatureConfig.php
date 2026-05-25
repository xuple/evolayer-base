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
     * @return array<int, string>
     */
    public function supportedProviders(): array
    {
        return ['anthropic', 'gemini', 'nvidia', 'opencode', 'openrouter'];
    }

    /**
     * @return array<string, string>
     */
    protected function providerLabels(): array
    {
        return [
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
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
