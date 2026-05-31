<?php

namespace Xuple\EvoLayer\Base\Ai;

use Illuminate\Http\Client\RequestException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use RuntimeException;
use Throwable;
use Xuple\EvoLayer\Base\Ai\Contracts\Probeable;
use Xuple\EvoLayer\Base\Models\AiCapability;
use Xuple\EvoLayer\Base\Support\AiCapabilityHash;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;

/**
 * Shared capability-probe service (ADR-019 seam #7). Runs one structured-
 * output probe of an agent against a provider/model, classifies the
 * outcome, synthesises a conditions-lite observation, and (optionally)
 * records it on the capability ledger.
 *
 * This is the single home for probe logic the three `evolayer:ai:*`
 * commands previously reimplemented; a future in-app / admin probe reuses
 * it rather than re-deriving the behaviour. The service produces
 * observations only — product policy (what ThreadStudio allows) lives in
 * `ThreadStudioProviderPolicy`, never here.
 */
class AiCapabilityProbe
{
    public function __construct(
        protected readonly ConditionsBuilder $conditions = new ConditionsBuilder,
    ) {}

    /**
     * Run one probe and return the observation. Pure — no persistence,
     * no cooldown. This is exactly what a console-only smoke needs.
     */
    public function probe(string $agentClass, string $provider, ?string $model, int $timeout = 45): ProbeResult
    {
        /** @var Agent&HasStructuredOutput $agent */
        $agent = app($agentClass);
        $schemaHash = AiCapabilityHash::fromAgent($agent);

        $config = app(AiManager::class)->getInstanceConfig($provider);

        if (empty($config['key'] ?? '')) {
            // Short-circuit: the agent was never run. The StructuredStreaming
            // condition is Unknown (observed nothing), not False.
            return new ProbeResult(
                ok: false,
                message: 'API key not configured',
                outputMode: 'unsupported',
                status: 'blocked',
                conditions: [$this->conditions->structuredStreaming(
                    exercised: false, passed: false,
                    reason: 'CredentialsMissing', message: 'API key not configured',
                    schemaHash: $schemaHash,
                )],
                model: $model,
                schemaHash: $schemaHash,
            );
        }

        $aiConfig = app(ThreadStudioAiConfig::class);
        $originalProvider = null;
        if ($provider !== $aiConfig->provider() && $provider !== 'opencode') {
            $originalProvider = config('ai.thread_studio.provider');
            config()->set('ai.thread_studio.provider', $provider);
        }

        if ($model === null && $provider === 'opencode') {
            $model = config('ai.providers.opencode.models.text.default', 'kimi-k2.6');
        }

        try {
            $start = microtime(true);
            try {
                $prompt = $agent instanceof Probeable ? $agent->probePrompt() : 'Test prompt for probe.';

                $response = $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: $timeout,
                );
            } finally {
                if ($originalProvider !== null) {
                    config()->set('ai.thread_studio.provider', $originalProvider);
                }
            }
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $payload = $response->toArray();
            $modelInfo = $model ? " (model: {$model})" : '';
            $message = "Structured output works{$modelInfo}.";

            return $this->observed(
                ok: true,
                message: $message,
                outputMode: $this->detectOutputMode($model),
                status: 'supported',
                exercised: true, passed: true,
                reason: 'StructuredOutputValidated',
                schemaHash: $schemaHash, model: $model, latencyMs: $latencyMs, payload: $payload,
            );
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];
            $error = $body['error']['message'] ?? $e->getMessage();

            if (str_contains($error, 'json_schema')) {
                return $this->failed($schemaHash, $model, 'Provider does not support json_schema', 'unsupported', 'ProviderRejectedJsonSchema');
            }

            if (str_contains($error, 'response_format')) {
                return $this->failed($schemaHash, $model, 'Provider response_format incompatibility', 'unsupported', 'ResponseFormatIncompatible');
            }

            if (str_contains($error, 'Model') && str_contains($error, 'not supported')) {
                return $this->failed($schemaHash, $model, 'Model not supported by provider', 'unsupported', 'ModelNotSupported');
            }

            return $this->failed($schemaHash, $model, 'HTTP error: '.substr($error, 0, 100), 'unknown', 'HttpError');
        } catch (RuntimeException $e) {
            return $this->failed($schemaHash, $model, 'Runtime: '.$e->getMessage(), 'unknown', 'RuntimeError');
        } catch (Throwable $e) {
            return $this->failed($schemaHash, $model, get_class($e).': '.substr($e->getMessage(), 0, 100), 'unknown', 'UnhandledError');
        }
    }

    /**
     * Run a probe with cooldown + conditional persistence — what the
     * `evolayer:ai:probe` command needs.
     */
    public function probeAndRecord(
        string $agentClass,
        string $provider,
        ?string $model,
        bool $persist,
        bool $force,
        int $timeout = 45,
    ): ProbeResult {
        $schemaHash = AiCapabilityHash::fromAgent(app($agentClass));

        if (! $force && $persist && $model) {
            // Honour the cooldown only if there's a LIVE row at this hash.
            // Without whereNull('superseded_at'), a schema revert (hash
            // returning to a previously-superseded value) would skip the
            // probe and leave ThreadStudioAiConfig::modelOptions() with no
            // active row.
            $recentProbe = AiCapability::where([
                'agent_class' => $agentClass,
                'provider' => $provider,
                'model' => $model,
                'schema_hash' => $schemaHash,
            ])
                ->whereNull('superseded_at')
                ->where('probed_at', '>=', now()->subHours(24))
                ->first();

            if ($recentProbe) {
                return new ProbeResult(
                    ok: $recentProbe->probe_passed,
                    message: 'Skipped (cooldown). Last passed: '.($recentProbe->probe_passed ? 'Yes' : 'No').'. Use --force to reprobe.',
                    outputMode: (string) $recentProbe->output_mode,
                    status: (string) $recentProbe->status,
                    model: $model,
                    schemaHash: $schemaHash,
                );
            }
        }

        $result = $this->probe($agentClass, $provider, $model, $timeout);

        if ($persist) {
            $this->persist($agentClass, $provider, $result);
        }

        return $result;
    }

    /**
     * Record an observation on the capability ledger. Returns null when
     * there is no model (the model is part of the unique key — a row
     * cannot be written without it; this is an explicit contract, not a
     * silent no-op buried in a truthiness check).
     */
    public function persist(string $agentClass, string $provider, ProbeResult $result): ?AiCapability
    {
        if ($result->model === null || $result->schemaHash === null) {
            return null;
        }

        // `probe_passed` is the projection of the StructuredStreaming
        // condition (Unknown -> false), so the boolean and the conditions
        // array derive from one source and cannot drift.
        $probePassed = $this->conditions->structuredStreamingPassed($result->conditions);

        return AiCapability::updateOrCreate(
            [
                'agent_class' => $agentClass,
                'provider' => $provider,
                'model' => $result->model,
                'schema_hash' => $result->schemaHash,
            ],
            [
                'probe_schema' => AiCapabilityHash::probeSchema($agentClass),
                'status' => $result->status === 'supported' ? 'supported' : 'blocked',
                'output_mode' => $result->outputMode,
                'probe_passed' => $probePassed,
                'conditions' => $result->conditions,
                'failure_reason' => $result->ok ? null : $result->message,
                'latency_ms' => $result->latencyMs,
                'probed_at' => now(),
                'superseded_at' => null,
                // `note` is deliberately omitted so human-authored
                // annotations survive updateOrCreate.
            ]
        );
    }

    /**
     * Detect output_mode for a successful probe. For models present in the
     * curated OpenCode catalogue, preserve the declared mode rather than
     * stamping `json_schema` — this prevents a `--force` reprobe from
     * clobbering a hand-curated `json_object`/`experimental` classification.
     */
    protected function detectOutputMode(?string $model): string
    {
        if ($model !== null) {
            $declared = ThreadStudioAiConfig::opencodeModelCompatibility()[$model]['output_mode'] ?? null;
            if (is_string($declared) && $declared !== '') {
                return $declared;
            }
        }

        return 'json_schema';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function observed(
        bool $ok,
        string $message,
        string $outputMode,
        string $status,
        bool $exercised,
        bool $passed,
        string $reason,
        string $schemaHash,
        ?string $model,
        ?int $latencyMs = null,
        ?array $payload = null,
    ): ProbeResult {
        return new ProbeResult(
            ok: $ok,
            message: $message,
            outputMode: $outputMode,
            status: $status,
            conditions: [$this->conditions->structuredStreaming(
                exercised: $exercised, passed: $passed, reason: $reason, message: $message, schemaHash: $schemaHash,
            )],
            latencyMs: $latencyMs,
            payload: $payload,
            model: $model,
            schemaHash: $schemaHash,
        );
    }

    protected function failed(string $schemaHash, ?string $model, string $message, string $outputMode, string $reason): ProbeResult
    {
        return $this->observed(
            ok: false, message: $message, outputMode: $outputMode, status: 'blocked',
            exercised: true, passed: false, reason: $reason, schemaHash: $schemaHash, model: $model,
        );
    }
}
