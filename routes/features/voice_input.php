<?php

use Xuple\EvoLayer\Base\Http\Controllers\Ai\VoiceInputController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_VOICE_INPUT=true.
*/

Route::middleware(['auth', 'verified', 'evo.admin'])
    ->post('ai/voice-input/transcribe', [VoiceInputController::class, 'transcribe'])
    ->middleware('throttle:30,1')
    ->name('evodevops.base.ai.voice-input.transcribe');
