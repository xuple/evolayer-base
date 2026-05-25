<?php

namespace Xuple\EvoLayer\Base\Support;

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiCapability;
use InvalidArgumentException;

/**
 * @evo-example thread_studio
 */
class ThreadStudioAiConfig extends AiFeatureConfig
{
    public function __construct()
    {
        parent::__construct('thread_studio', 'ThreadStudio');
    }

    /**
     * OpenCode Go model capability with Laravel AI SDK structured output.
     *
     * `supported` + `json_schema` models are accepted for ThreadStudio.
     * `experimental` models are visible for smoke testing but are not accepted
     * by the feature until a runtime strategy exists for their output mode.
     *
     * This is the single source of truth — keep it aligned with the OpenCode Go
     * model catalogue at https://opencode.ai/go.
     *
     * @return array<string, array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported', default?: bool, note: string}>
     */
    public static function opencodeModelCompatibility(): array
    {
        return [
            'kimi-k2.6' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'default' => true,
                'note' => 'Recommended OpenCode Go default.',
            ],
            'kimi-k2.5' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'note' => 'Supports Laravel AI SDK structured output.',
            ],
            'mimo-v2-pro' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'note' => 'Supports Laravel AI SDK structured output.',
            ],
            'mimo-v2-omni' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'note' => 'Supports Laravel AI SDK structured output.',
            ],
            'mimo-v2.5-pro' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'note' => 'Supports Laravel AI SDK structured output.',
            ],
            'mimo-v2.5' => [
                'status' => 'supported',
                'output_mode' => 'json_schema',
                'note' => 'Supports Laravel AI SDK structured output.',
            ],
            'deepseek-v4-pro' => [
                'status' => 'blocked',
                'output_mode' => 'prompt_json',
                'note' => 'Rejects response_format through OpenCode Go; prompt-only strategy is unimplemented.',
            ],
            'deepseek-v4-flash' => [
                'status' => 'blocked',
                'output_mode' => 'prompt_json',
                'note' => 'Rejects response_format through OpenCode Go; prompt-only strategy is unimplemented.',
            ],
            'glm-5' => [
                'status' => 'blocked',
                'output_mode' => 'unsupported',
                'note' => 'Returns an empty structured response.',
            ],
            'glm-5.1' => [
                'status' => 'blocked',
                'output_mode' => 'unsupported',
                'note' => 'Returns an empty structured response.',
            ],
            'minimax-m2.7' => [
                'status' => 'blocked',
                'output_mode' => 'unsupported',
                'note' => 'Returns an empty structured response.',
            ],
            'minimax-m2.5' => [
                'status' => 'experimental',
                'output_mode' => 'json_object',
                'note' => 'Passed exploratory json_object smoke testing; runtime strategy is not implemented.',
            ],
            'qwen3.5-plus' => [
                'status' => 'experimental',
                'output_mode' => 'json_object',
                'note' => 'Passed exploratory json_object smoke testing; runtime strategy is not implemented.',
            ],
            'qwen3.6-plus' => [
                'status' => 'experimental',
                'output_mode' => 'json_object',
                'note' => 'Passed exploratory json_object smoke testing; runtime strategy is not implemented.',
            ],
        ];
    }

    /**
     * Capability snapshot for the currently-selected provider+model pair.
     *
     * Only OpenCode Go has a documented per-model capability matrix today.
     * Other providers return null because we don't yet track per-model output
     * modes for them — the UI treats null as "not applicable" rather than
     * "unsupported".
     *
     * @return array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null
     */
    public function capability(?string $provider = null, ?string $model = null): ?array
    {
        $provider = $this->provider($provider);

        if ($provider !== 'opencode') {
            return null;
        }

        $model ??= $this->defaultModel($provider);
        $options = collect($this->modelOptions($provider))->keyBy('name');

        if (! $options->has($model)) {
            return null;
        }

        return $options->get($model)['capability'];
    }

    public function resolveModel(?string $provider = null, ?string $model = null): string
    {
        $provider = $this->provider($provider);
        $model = $model !== null && $model !== '' ? $model : $this->defaultModel($provider);

        if (! in_array($model, $this->selectableModelNames($provider), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported ThreadStudio model [%s] for provider [%s]. Supported models are: %s.',
                $model,
                $provider,
                implode(', ', $this->selectableModelNames($provider)),
            ));
        }

        return $model;
    }

    /**
     * @return array<int, string>
     */
    public function selectableModelNames(?string $provider = null): array
    {
        return array_values(array_map(
            fn (array $model): string => $model['name'],
            array_filter($this->modelOptions($provider), fn (array $model): bool => $model['selectable']),
        ));
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     label: string,
     *     source: 'provider_default'|'catalogue',
     *     selectable: bool,
     *     default: bool,
     *     capability: array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null,
     *     note: string|null
     * }>
     */
    public function modelOptions(?string $provider = null): array
    {
        $provider = $this->provider($provider);
        $defaultModel = $this->defaultModel($provider);

        if ($provider !== 'opencode') {
            return [[
                'name' => $defaultModel,
                'label' => $defaultModel,
                'source' => 'provider_default',
                'selectable' => true,
                'default' => true,
                'capability' => null,
                'note' => null,
            ]];
        }

        $dbCapabilities = once(function () use ($provider) {
            $agent = new ThreadStudioAgent;
            $schemaHash = AiCapabilityHash::fromAgent($agent);

            return AiCapability::where([
                'agent_class' => ThreadStudioAgent::class,
                'provider' => $provider,
                'schema_hash' => $schemaHash,
            ])
                ->whereNull('superseded_at')
                ->get()
                ->keyBy('model');
        });

        $compatibility = $dbCapabilities->isEmpty()
            ? static::opencodeModelCompatibility()
            : $dbCapabilities->all();

        return array_map(
            function (string $model, mixed $info) use ($defaultModel): array {
                $status = is_array($info) ? $info['status'] : $info->status;
                $outputMode = is_array($info) ? $info['output_mode'] : $info->output_mode;
                $note = is_array($info) ? ($info['note'] ?? null) : $info->note;

                $selectable = $status === 'supported' && $outputMode === 'json_schema';

                return [
                    'name' => $model,
                    'label' => $model,
                    'source' => 'catalogue',
                    'selectable' => $selectable,
                    'default' => $model === $defaultModel,
                    'capability' => [
                        'status' => $status,
                        'output_mode' => $outputMode,
                    ],
                    'note' => $note,
                ];
            },
            array_keys($compatibility),
            $compatibility,
        );
    }

    /**
     * @return array{
     *     name: string,
     *     label: string,
     *     model: array{name: string, source: 'provider_default'},
     *     models: array<int, array{name: string, label: string, source: 'provider_default'|'catalogue', selectable: bool, default: bool, capability: array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null, note: string|null}>,
     *     capability: array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null
     * }
     */
    public function metadata(?string $provider = null): array
    {
        $provider = $this->provider($provider);

        return [
            ...parent::metadata($provider),
            'models' => $this->modelOptions($provider),
            'capability' => $this->capability($provider),
        ];
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     label: string,
     *     model: array{name: string, source: 'provider_default'},
     *     models: array<int, array{name: string, label: string, source: 'provider_default'|'catalogue', selectable: bool, default: bool, capability: array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null, note: string|null}>,
     *     capability: array{status: 'supported'|'experimental'|'blocked', output_mode: 'json_schema'|'json_object'|'prompt_json'|'unsupported'}|null
     * }>
     */
    public function providerOptions(): array
    {
        return array_map(
            fn (string $provider): array => $this->metadata($provider),
            $this->supportedProviders(),
        );
    }
}
