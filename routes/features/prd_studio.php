<?php

use Xuple\EvoLayer\Base\Http\Controllers\Admin\PrdController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_PRD_STUDIO=true.
*/

Route::middleware(['auth', 'verified', 'evo.admin'])
    ->prefix('admin')
    ->name('evodevops.base.admin.prd.')
    ->group(function (): void {
        Route::get('prd', [PrdController::class, 'show'])->name('show');
        Route::post('prd/generate', [PrdController::class, 'generate'])
            ->middleware('throttle:10,1')
            ->name('generate');
    });
