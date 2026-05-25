<?php

namespace Xuple\EvoLayer\Base\Http\Controllers\Admin;

use Xuple\EvoLayer\Base\Http\Controllers\Controller;
use Xuple\EvoLayer\Base\Http\Requests\Admin\GeneratePrdRequest;
use Xuple\EvoLayer\Base\Support\PrdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * @evo-example prd_studio
 */
class PrdController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('evodevops/admin/prd', [
            // Cross-feature URLs passed at runtime so the page never compile-time
            // depends on the ai_text_field / voice_input features' Wayfinder
            // controllers. Null when those features aren't enabled.
            'aiTextAssistUrl' => Route::has('evodevops.base.ai.text-assist.stream')
                ? route('evodevops.base.ai.text-assist.stream')
                : null,
            'voiceInputUrl' => Route::has('evodevops.base.ai.voice-input.transcribe')
                ? route('evodevops.base.ai.voice-input.transcribe')
                : null,
        ]);
    }

    public function generate(GeneratePrdRequest $request, PrdGenerator $generator): JsonResponse
    {
        try {
            return response()->json([
                'prd' => $generator->generate($request->validated()),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The PRD generator is unavailable right now. Try again in a moment.',
            ], 502);
        }
    }
}
