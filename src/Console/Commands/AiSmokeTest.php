<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;
use Xuple\EvoLayer\Base\Ai\AiCapabilityProbe;
use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Xuple\EvoLayer\Base\Support\ThreadStudioResult;

#[Signature('evolayer:ai:smoke-test {provider?} {--model=} {--timeout=45}')]
#[Description('Smoke-test AI provider connectivity and structured-output compatibility using the thread-studio schema')]
class AiSmokeTest extends Command
{
    /**
     * @return array<string, array{status: string, output_mode: string, default?: bool, note: string}>
     */
    private function opencodeModels(): array
    {
        return ThreadStudioAiConfig::opencodeModelCompatibility();
    }

    public function handle(): int
    {
        $provider = $this->argument('provider');
        $model = $this->option('model');
        $timeout = (int) $this->option('timeout');

        if ($provider === null) {
            return $this->runAllProviders($timeout);
        }

        return $this->runSingleProvider($provider, $model, $timeout);
    }

    private function runAllProviders(int $timeout): int
    {
        $this->components->info('Testing all configured AI providers…');

        $providers = app(ThreadStudioAiConfig::class)->supportedProviders();
        $results = [];

        foreach ($providers as $provider) {
            $results[$provider] = $this->runProviderTest($provider, null, $timeout);
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

    private function runSingleProvider(string $provider, ?string $model, int $timeout): int
    {
        $this->components->info(sprintf('Testing provider: %s', $provider));

        if ($provider === 'opencode' && $model === null) {
            return $this->runOpenCodeModels($timeout);
        }

        $result = $this->runProviderTest($provider, $model, $timeout);

        $this->newLine();
        if ($result['ok']) {
            $this->components->success($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        return self::FAILURE;
    }

    private function runOpenCodeModels(int $timeout): int
    {
        $this->components->warn('Testing all OpenCode Go models…');
        $this->line('Runs the schema-backed ThreadStudio reply-generation workflow; declared capabilities are shown for context.');
        $this->newLine();

        $results = [];
        foreach ($this->opencodeModels() as $model => $capability) {
            $this->output->write(sprintf(
                '  %-20s %-28s ',
                $model,
                "[{$capability['status']}/{$capability['output_mode']}]",
            ));

            $result = $this->runProviderTest('opencode', $model, $timeout);
            $results[$model] = $result;

            if ($result['ok']) {
                $this->output->writeln('<fg=green>PASS</>');
            } else {
                $this->output->writeln('<fg=red>FAIL</> '.$result['message']);
            }
        }

        $this->newLine();
        $working = collect($results)
            ->filter(fn ($r, string $model) => $r['ok'] && ($this->opencodeModels()[$model]['status'] ?? null) === 'supported')
            ->keys()
            ->all();

        $this->components->info(sprintf(
            'Supported json_schema models: %s',
            empty($working) ? 'none' : implode(', ', $working)
        ));

        return empty($working) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Console-only structured-output check. Delegates the agent call,
     * API-key short-circuit, provider override, and exception
     * classification to the shared AiCapabilityProbe service (ADR-019) —
     * persisting nothing — then applies the smoke command's own stricter
     * success criterion (full ThreadStudioResult validation) and its
     * `Response: {summary}` message.
     *
     * @return array{ok: bool, message: string}
     */
    private function runProviderTest(string $provider, ?string $model, int $timeout): array
    {
        $result = app(AiCapabilityProbe::class)->probe(ThreadStudioAgent::class, $provider, $model, $timeout);

        if (! $result->ok) {
            // Identical classified failure messages (API key, json_schema,
            // response_format, model-not-supported, HTTP, runtime, other).
            return $result->toLegacyArray();
        }

        // Smoke is stricter than the probe on success: the payload must pass
        // full ThreadStudio schema validation, not merely decode.
        try {
            ThreadStudioResult::fromArray($result->payload ?? []);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => get_class($e).': '.substr($e->getMessage(), 0, 100)];
        }

        $modelInfo = $result->model ? " (model: {$result->model})" : '';

        return [
            'ok' => true,
            'message' => "Structured output works{$modelInfo}. Response: {$result->payload['summary']}",
        ];
    }
}
