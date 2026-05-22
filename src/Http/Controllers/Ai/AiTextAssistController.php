<?php

namespace EvoDevOps\Base\Http\Controllers\Ai;

use EvoDevOps\Base\Ai\Agents\TextAssistAgent;
use EvoDevOps\Base\Http\Controllers\Controller;
use EvoDevOps\Base\Http\Requests\Ai\StreamTextAssistRequest;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @evo-example ai_text_field
 */
class AiTextAssistController extends Controller
{
    public function streamAssist(StreamTextAssistRequest $request): StreamedResponse
    {
        $fieldHint = $request->string('field_hint')->toString();
        $context = $request->string('context')->toString() ?: null;

        return response()->stream(function () use ($fieldHint, $context): void {
            $this->emit('start', '{}');

            try {
                $prompt = "Field: {$fieldHint}";

                if ($context !== null && $context !== '') {
                    $prompt .= "\n\nContext:\n{$context}";
                }

                $stream = TextAssistAgent::make()->stream($prompt);
                $full = '';

                foreach ($stream as $event) {
                    if (! ($event instanceof TextDelta)) {
                        continue;
                    }

                    $full .= $event->delta;
                    $this->emit('text_delta', json_encode(
                        ['delta' => $event->delta],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ));
                }

                $this->emit('done', json_encode(
                    ['text' => $full],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ));
            } catch (Throwable $exception) {
                report($exception);
                $this->emit('error', json_encode(
                    ['message' => 'The AI assist is unavailable right now. Try again in a moment.'],
                    JSON_THROW_ON_ERROR,
                ));
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function emit(string $event, string $data): void
    {
        echo "event: {$event}\ndata: {$data}\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
