<?php

namespace EvoDevOps\Base\Http\Controllers\Ai;

use EvoDevOps\Base\Http\Controllers\Controller;
use EvoDevOps\Base\Http\Requests\Ai\ComposeThreadStudioRequest;
use EvoDevOps\Base\Support\ThreadStudioAiConfig;
use EvoDevOps\Base\Support\ThreadStudioComposer;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
