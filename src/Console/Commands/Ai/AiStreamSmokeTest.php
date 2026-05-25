<?php

namespace Xuple\EvoLayer\Base\Console\Commands\Ai;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;
use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Support\PartialJsonExtractor;

#[Signature('evolayer:ai:stream-smoke {provider=gemini : Provider key (gemini, openai, anthropic, ...)}')]
#[Description('Live smoke test of structured streaming through ThreadStudioAgent. Use to verify a provider supports field-level streaming end-to-end.')]
class AiStreamSmokeTest extends Command
{
    public function handle(): int
    {
        $providerName = (string) $this->argument('provider');
        $provider = Lab::tryFrom($providerName);

        if ($provider === null) {
            $this->error("Unknown provider '{$providerName}'.");

            return self::FAILURE;
        }

        $agent = ThreadStudioAgent::make();
        $extractor = new PartialJsonExtractor;

        $prompt = "Preferred reply tone: warm\n\nSupport product: EvoLayer Base Laravel + Inertia starter kit.\n\nCustomer message:\nI upgraded my plan this morning and now I cannot download any of my invoices. This is blocking finance.";

        $this->info("Starting live stream via {$provider->value}...");

        $start = microtime(true);
        $firstTokenAt = null;
        $tokenCount = 0;
        $fieldsCompleted = [];

        try {
            $stream = $agent->stream($prompt, provider: $provider, timeout: 60);

            foreach ($stream as $event) {
                if (! ($event instanceof TextDelta)) {
                    continue;
                }
                $tokenCount++;
                if ($firstTokenAt === null) {
                    $firstTokenAt = microtime(true);
                    $ms = round(($firstTokenAt - $start) * 1000);
                    $this->line("  first token: +{$ms}ms");
                }
                foreach ($extractor->feed($event->delta) as $delta) {
                    if ($delta['complete']) {
                        $fieldsCompleted[] = $delta['name'];
                        $ms = round((microtime(true) - $start) * 1000);
                        $this->line("  field_complete: {$delta['name']} (+{$ms}ms)");
                    }
                }
            }

            $total = round((microtime(true) - $start) * 1000);
            $this->newLine();
            $this->info("Total: {$total}ms, {$tokenCount} TextDelta events");
            $this->info('Fields completed: '.implode(', ', $fieldsCompleted));

            $payload = json_decode((string) $stream->text, true);
            if (is_array($payload)) {
                $this->info('Final payload keys: '.implode(', ', array_keys($payload)));
                $this->line('  urgency: '.($payload['urgency'] ?? '(none)'));
                $this->line('  sentiment: '.($payload['sentiment'] ?? '(none)'));
                $this->line('  summary: '.substr((string) ($payload['summary'] ?? '(none)'), 0, 100));
            } else {
                $this->error('Could not decode final payload (length='.strlen((string) $stream->text).')');

                return self::FAILURE;
            }

            if (count($fieldsCompleted) !== 6) {
                $this->warn('Expected 6 fields completed, got '.count($fieldsCompleted));

                return self::FAILURE;
            }

            $this->info('✓ Structured streaming verified end-to-end.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERROR ('.$e::class.'): '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
