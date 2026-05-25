<?php

namespace Xuple\EvoLayer\Base\Http\Controllers\Ai;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Xuple\EvoLayer\Base\Http\Controllers\Controller;
use Xuple\EvoLayer\Base\Http\Requests\Ai\ComposeThreadStudioRequest;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Xuple\EvoLayer\Base\Support\ThreadStudioComposer;

/**
 * @evo-example thread_studio
 */
class ThreadStudioController extends Controller
{
    public function show(ThreadStudioAiConfig $aiConfig): Response
    {
        return Inertia::render('evodevops/ai/thread-studio', [
            'aiProvider' => $aiConfig->metadata(),
            'aiProviders' => $aiConfig->providerOptions(),
            // Cross-feature URL passed at runtime so the page never compile-time
            // depends on the voice_input feature's Wayfinder controller. Null
            // when voice_input isn't enabled — the page hides the Dictate button.
            'voiceInputUrl' => Route::has('evolayer.base.ai.voice-input.transcribe')
                ? route('evolayer.base.ai.voice-input.transcribe')
                : null,
        ]);
    }

    public function streamCompose(
        ComposeThreadStudioRequest $request,
        ThreadStudioComposer $composer,
    ): StreamedResponse {
        $customerMessage = $request->string('customer_message')->toString();
        $tone = $request->string('tone')->toString();
        $provider = $request->string('provider')->toString() ?: null;
        $model = $request->string('model')->toString() ?: null;

        return response()->stream(function () use ($customerMessage, $tone, $provider, $model, $composer): void {
            foreach ($composer->streamCompose($customerMessage, $tone, $provider, $model) as $event) {
                $type = $event['type'];
                unset($event['type']);

                $data = $event === [] ? '{}' : json_encode(
                    $event,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                echo "event: {$type}\ndata: {$data}\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function store(
        ComposeThreadStudioRequest $request,
        ThreadStudioComposer $composer,
    ): JsonResponse {
        try {
            return response()->json([
                'result' => $composer->compose(
                    $request->string('customer_message')->toString(),
                    $request->string('tone')->toString(),
                    $request->string('provider')->toString() ?: null,
                    $request->string('model')->toString() ?: null,
                )->result->toArray(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The AI provider did not return a usable reply. Try again in a moment.',
            ], 502);
        }
    }
}
