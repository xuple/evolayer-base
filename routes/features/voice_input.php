<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\Ai\VoiceInputController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_VOICE_INPUT=true.
*/

Route::middleware(['auth', 'verified', 'evolayer.admin'])
    ->post('ai/voice-input/transcribe', [VoiceInputController::class, 'transcribe'])
    ->middleware('throttle:30,1')
    ->name('evolayer.base.ai.voice-input.transcribe');
