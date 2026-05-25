<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiCapability;
use Xuple\EvoLayer\Base\Support\AiCapabilityHash;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use RuntimeException;
use Throwable;

#[Signature('ai:probe {--agent=} {--provider=} {--model=} {--all-models} {--reprobe-stale} {--max-models=} {--max-probes=} {--budget-cents=} {--persist} {--force}')]
#[Description('Probe AI provider connectivity and structured-output compatibility')]
class AiProbeCommand extends Command
{
    public function handle(): int
    {
        $agentClass = $this->option('agent') ?? ThreadStudioAgent::class;
        $provider = $this->option('provider');
        $model = $this->option('model');
        $allModels = $this->option('all-models');
        $reprobeStale = $this->option('reprobe-stale');
        $persist = $this->option('persist');

        if (! class_exists($agentClass)) {
            $this->components->error("Agent class [{$agentClass}] not found.");

            return self::FAILURE;
        }

        if ($reprobeStale) {
            if ($allModels) {
                $this->components->error('The --reprobe-stale and --all-models flags are mutually exclusive.');

                return self::FAILURE;
            }
            if (! $this->option('max-probes')) {
                $this->components->error('The --reprobe-stale flag requires --max-probes=N to prevent runaway probing. (--budget-cents is reserved for a future cost-estimation layer and is not yet enforced.)');

                return self::FAILURE;
            }

            return $this->runReprobeStale();
        }

        if ($allModels) {
            if (! $this->option('max-models')) {
                $this->components->error('The --all-models flag requires --max-models=N to prevent runaway probing. (--budget-cents is reserved for a future cost-estimation layer and is not yet enforced.)');

                return self::FAILURE;
            }
            if ($provider !== 'opencode') {
                $this->components->error('The --all-models flag is currently only supported for the opencode provider.');

                return self::FAILURE;
            }

            return $this->runAllModels($agentClass, $provider, $persist);
        }

        if ($provider === null) {
            return $this->runAllProviders($agentClass, $persist);
        }

        return $this->runSingleProvider($agentClass, $provider, $model, $persist);
    }

    /**
     * Re-probe rows whose schema_hash no longer matches the live agent schema,
     * then soft-delete the original row via superseded_at on success. Stale
     * rows for agents whose class no longer exists are skipped with a warning.
     *
     * `--budget-cents` remains in the signature but is reserved for a future
     * cost-estimation layer. Today it is NOT a valid runaway-probe guard;
     * `--max-probes=N` is required as the ceiling.
     */
    private function runReprobeStale(): int
    {
        $maxProbes = (int) ($this->option('max-probes') ?: 0);
        $agentClasses = AiCapability::query()
            ->whereNull('superseded_at')
            ->distinct()
            ->pluck('agent_class');

        $processed = 0;
        $passed = 0;
        $failed = 0;

        foreach ($agentClasses as $agentClass) {
            if (! class_exists($agentClass)) {
                $this->components->warn("Agent class [{$agentClass}] no longer exists; skipping orphan rows.");

                continue;
            }

            /** @var Agent&HasStructuredOutput $agent */
            $agent = app($agentClass);
            $liveHash = AiCapabilityHash::fromAgent($agent);

            $stale = AiCapability::query()
                ->where('agent_class', $agentClass)
                ->where('schema_hash', '!=', $liveHash)
                ->whereNull('superseded_at')
                ->get();

            $this->components->info(sprintf(
                'Found %d stale rows for [%s].',
                $stale->count(),
                class_basename($agentClass),
            ));

            foreach ($stale as $row) {
                if ($maxProbes > 0 && $processed >= $maxProbes) {
                    $this->components->warn("Reached --max-probes={$maxProbes}; stopping.");

                    return $failed > 0 ? self::FAILURE : self::SUCCESS;
                }

                $this->output->write(sprintf('  %-20s %-20s ', $row->provider, $row->model));
                $result = $this->runProbe($agentClass, $row->provider, $row->model, persist: true);
                $processed++;

                if ($result['ok']) {
                    $row->update(['superseded_at' => now()]);
                    $this->output->writeln('<fg=green>PASS</> (stale row marked superseded)');
                    $passed++;
                } else {
                    $this->output->writeln('<fg=red>FAIL</> '.$result['message'].' (stale row preserved)');
                    $failed++;
                }
            }
        }

        $this->components->info(sprintf(
            'Reprobed %d stale rows: %d passed, %d failed.',
            $processed, $passed, $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runAllProviders(string $agentClass, bool $persist): int
    {
        // TODO (Step 2+): The all-providers path passes $model = null for every provider
        // (except OpenCode which resolves its default model later). Because recordResult()
        // bails if $model is null, --persist is effectively a no-op for non-OpenCode providers.
        // This is acceptable for Step 1 since we're only seeding OpenCode persistence.
        $this->components->info('Probing all configured AI providers…');

        $providers = app(ThreadStudioAiConfig::class)->supportedProviders();
        $results = [];

        foreach ($providers as $provider) {
            $results[$provider] = $this->runProbe($agentClass, $provider, null, $persist);
        }

        $this->newLine();
        $this->components->info('Results');
        $this->table(['Provider', 'Status', 'Details'], array_map(
            fn ($name, $result) => [
                $name,
                $result['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                $result['message'],
            ],
            array_keys($results),
            $results,
        ));

        $allPassed = collect($results)->every(fn ($r) => $r['ok']);

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    private function runSingleProvider(string $agentClass, string $provider, ?string $model, bool $persist): int
    {
        $this->components->info(sprintf('Probing provider: %s', $provider));

        if ($provider === 'opencode' && $model === null) {
            $model = config('ai.providers.opencode.models.text.default', 'kimi-k2.6');
        }

        $result = $this->runProbe($agentClass, $provider, $model, $persist);

        $this->newLine();
        if ($result['ok']) {
            $this->components->success($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        return self::FAILURE;
    }

    private function runAllModels(string $agentClass, string $provider, bool $persist): int
    {
        $this->components->warn('Probing all OpenCode Go models…');
        $this->newLine();

        $models = array_keys(ThreadStudioAiConfig::opencodeModelCompatibility());
        $maxModels = (int) $this->option('max-models');
        if ($maxModels > 0) {
            $models = array_slice($models, 0, $maxModels);
        }

        $results = [];
        foreach ($models as $model) {
            $this->output->write(sprintf('  %-20s ', $model));

            $result = $this->runProbe($agentClass, $provider, $model, $persist);
            $results[$model] = $result;

            if ($result['ok']) {
                $this->output->writeln('<fg=green>PASS</>');
            } else {
                $this->output->writeln('<fg=red>FAIL</> '.$result['message']);
            }
        }

        $this->newLine();
        $working = collect($results)
            ->filter(fn ($r) => $r['ok'])
            ->keys()
            ->all();

        $this->components->info(sprintf(
            'Supported models: %s',
            empty($working) ? 'none' : implode(', ', $working)
        ));

        return empty($working) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function runProbe(string $agentClass, string $provider, ?string $model, bool $persist): array
    {
        /** @var Agent&HasStructuredOutput $agent */
        $agent = app($agentClass);
        $schemaHash = AiCapabilityHash::fromAgent($agent);

        if (! $this->option('force') && $persist && $model) {
            // Only honour the cooldown if there's a LIVE row at this hash.
            // Without whereNull('superseded_at'), a schema revert (hash returning
            // to a previously-superseded value) would skip the probe and leave
            // ThreadStudioAiConfig::modelOptions() with no active row.
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
                return [
                    'ok' => $recentProbe->probe_passed,
                    'message' => 'Skipped (cooldown). Last passed: '.($recentProbe->probe_passed ? 'Yes' : 'No').'. Use --force to reprobe.',
                ];
            }
        }

        try {
            $config = app(AiManager::class)->getInstanceConfig($provider);

            if (empty($config['key'] ?? '')) {
                return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'API key not configured', 'unsupported');
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

            $start = microtime(true);
            try {
                // TODO (Step 2+): The prompt here is hardcoded for ThreadStudioAgent.
                // Any future agent probed with this literal string will likely produce
                // malformed output -> false negative. Future direction: add a probePrompt()
                // method to a Probeable interface.
                $prompt = 'Test prompt for probe.';
                if ($agentClass === ThreadStudioAgent::class) {
                    $prompt = <<<'PROMPT'
Preferred reply tone: balanced

Customer message:
Greetings, now what do I do once I have downloaded this?
PROMPT;
                }

                $response = $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 45,
                );
            } finally {
                if ($originalProvider !== null) {
                    config()->set('ai.thread_studio.provider', $originalProvider);
                }
            }
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $data = $response->toArray();
            $modelInfo = $model ? " (model: {$model})" : '';

            // TODO (Step 2+): The probe hardcodes output_mode = 'json_schema' on success.
            // It does not detect output_mode from the response. This means re-probing an
            // 'experimental' json_object model with --force would overwrite it to json_schema.
            return $this->recordResult(
                $persist, $agentClass, $provider, $model, $schemaHash, true,
                "Structured output works{$modelInfo}.", 'json_schema', $latencyMs
            );
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];
            $error = $body['error']['message'] ?? $e->getMessage();

            if (str_contains($error, 'json_schema')) {
                return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'Provider does not support json_schema', 'unsupported');
            }

            if (str_contains($error, 'response_format')) {
                return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'Provider response_format incompatibility', 'unsupported');
            }

            if (str_contains($error, 'Model') && str_contains($error, 'not supported')) {
                return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'Model not supported by provider', 'unsupported');
            }

            return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'HTTP error: '.substr($error, 0, 100), 'unknown');
        } catch (RuntimeException $e) {
            return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, 'Runtime: '.$e->getMessage(), 'unknown');
        } catch (Throwable $e) {
            return $this->recordResult($persist, $agentClass, $provider, $model, $schemaHash, false, get_class($e).': '.substr($e->getMessage(), 0, 100), 'unknown');
        }
    }

    private function recordResult(
        bool $persist, string $agentClass, string $provider, ?string $model, string $schemaHash,
        bool $passed, string $message, string $outputMode, ?int $latencyMs = null
    ): array {
        if ($persist && $model) {
            // `superseded_at => null` un-supersedes any row this probe updates.
            // The unique key (agent_class, provider, model, schema_hash) means
            // a probe at a given hash is the authoritative current snapshot for
            // that hash — a schema revert (live hash matches a previously-
            // superseded row) is correctly resurrected here.
            //
            // `note` is deliberately omitted so human-authored annotations
            // survive updateOrCreate (see "preserves the human-authored note"
            // test).
            AiCapability::updateOrCreate(
                [
                    'agent_class' => $agentClass,
                    'provider' => $provider,
                    'model' => $model,
                    'schema_hash' => $schemaHash,
                ],
                [
                    'probe_schema' => AiCapabilityHash::probeSchema($agentClass),
                    'status' => $passed ? 'supported' : 'blocked',
                    'output_mode' => $outputMode,
                    'probe_passed' => $passed,
                    'failure_reason' => $passed ? null : $message,
                    'latency_ms' => $latencyMs,
                    'probed_at' => now(),
                    'superseded_at' => null,
                ]
            );
        }

        return ['ok' => $passed, 'message' => $message];
    }
}
