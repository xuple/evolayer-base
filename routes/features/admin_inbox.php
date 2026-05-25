<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\Admin\InboxController;
use Xuple\EvoLayer\Base\Http\Controllers\Admin\SubmissionsController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_ADMIN_INBOX=true.
| Provides the admin inbox UI plus the older submissions detail/index views.
*/

Route::middleware(['auth', 'verified', 'evolayer.admin'])
    ->prefix('admin')
    ->name('evolayer.base.admin.')
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
