<?php

use EvoDevOps\Base\Http\Controllers\Admin\InboxController;
use EvoDevOps\Base\Http\Controllers\Admin\SubmissionsController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_ADMIN_INBOX=true.
| Provides the admin inbox UI plus the older submissions detail/index views.
*/

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('evodevops.base.admin.')
    ->group(function (): void {
        Route::get('inbox', [InboxController::class, 'show'])->name('inbox.show');
        Route::get('inbox/search', [InboxController::class, 'search'])
            ->middleware('throttle:60,1')
            ->name('inbox.search');
        Route::get('inbox/{submission}', [InboxController::class, 'detail'])->name('inbox.detail');

        Route::get('submissions', [SubmissionsController::class, 'index'])->name('submissions.index');
        Route::get('submissions/{submission}', [SubmissionsController::class, 'show'])->name('submissions.show');
        Route::patch('submissions/{submission}/mark-read', [SubmissionsController::class, 'markRead'])->name('submissions.mark-read');
        Route::patch('submissions/{submission}/archive', [SubmissionsController::class, 'archive'])->name('submissions.archive');
    });
