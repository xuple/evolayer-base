<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\Ai\AiTextAssistController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_AI_TEXT_FIELD=true.
*/

Route::middleware(['auth', 'verified', 'evolayer.admin'])
    ->post('ai/text-assist/stream', [AiTextAssistController::class, 'streamAssist'])
    ->middleware('throttle:20,1')
    ->name('evolayer.base.ai.text-assist.stream');
