<?php

use EvoDevOps\Base\Http\Controllers\Ai\AiTextAssistController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_AI_TEXT_FIELD=true.
*/

Route::middleware(['auth', 'verified', 'evo.admin'])
    ->post('ai/text-assist/stream', [AiTextAssistController::class, 'streamAssist'])
    ->middleware('throttle:20,1')
    ->name('evodevops.base.ai.text-assist.stream');
