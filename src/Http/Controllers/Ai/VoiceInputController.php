<?php

namespace Xuple\EvoLayer\Base\Http\Controllers\Ai;

use Illuminate\Http\JsonResponse;
use Laravel\Ai\Transcription;
use Throwable;
use Xuple\EvoLayer\Base\Http\Controllers\Controller;
use Xuple\EvoLayer\Base\Http\Requests\Ai\TranscribeAudioRequest;

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
