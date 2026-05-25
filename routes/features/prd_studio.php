<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\Admin\PrdController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_PRD_STUDIO=true.
*/

Route::middleware(['auth', 'verified', 'evolayer.admin'])
    ->prefix('admin')
    ->name('evolayer.base.admin.prd.')
    ->group(function (): void {
        Route::get('prd', [PrdController::class, 'show'])->name('show');
        Route::post('prd/generate', [PrdController::class, 'generate'])
            ->middleware('throttle:10,1')
            ->name('generate');
    });
