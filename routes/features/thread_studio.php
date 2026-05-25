<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\Ai\ThreadStudioController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true.
*/

Route::middleware(['auth', 'verified', 'evolayer.admin'])
    ->prefix('ai/thread-studio')
    ->name('evolayer.base.ai.thread-studio.')
    ->group(function (): void {
        Route::get('/', [ThreadStudioController::class, 'show'])->name('show');
        Route::post('/', [ThreadStudioController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
        Route::post('stream', [ThreadStudioController::class, 'streamCompose'])
            ->middleware('throttle:10,1')
            ->name('stream');
    });
