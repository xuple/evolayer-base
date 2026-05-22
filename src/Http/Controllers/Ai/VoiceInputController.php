<?php

namespace EvoDevOps\Base\Http\Controllers\Ai;

use EvoDevOps\Base\Http\Controllers\Controller;
use EvoDevOps\Base\Http\Requests\Ai\TranscribeAudioRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Transcription;
use Throwable;

/**
 * @evo-example voice_input
 */
class VoiceInputController extends Controller
{
    public function transcribe(TranscribeAudioRequest $request): JsonResponse
    {
        try {
            $transcript = Transcription::fromUpload($request->file('audio'))->generate();

            return response()->json([
                'text' => (string) $transcript,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Transcription is unavailable right now. Try again in a moment.',
            ], 502);
        }
    }
}
