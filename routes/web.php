<?php

use EvoDevOps\Base\Http\Controllers\Admin\InboxController;
use EvoDevOps\Base\Http\Controllers\Admin\PrdController;
use EvoDevOps\Base\Http\Controllers\Admin\SubmissionsController;
use EvoDevOps\Base\Http\Controllers\Ai\AiTextAssistController;
use EvoDevOps\Base\Http\Controllers\Ai\ThreadStudioController;
use EvoDevOps\Base\Http\Controllers\Ai\VoiceInputController;
use EvoDevOps\Base\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EvoDevOps Base Routes
|--------------------------------------------------------------------------
|
| Loaded by EvoDevOps\Base\BaseServiceProvider inside the middleware group
| declared in config('evo.route.middleware') (default ['web']).
|
| All route NAMES are prefixed with `evodevops.` so they never collide with
| host or other-package routes (e.g. starter's `home` / `about`). URLs are
| not prefixed — the host can override the URL prefix via config('evo.route.prefix')
| if they need a `/evo/...` namespace.
|
*/

Route::inertia('/about', 'evodevops/about')->name('evodevops.about');

Route::get('/contact', [ContactController::class, 'show'])->name('evodevops.contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('evodevops.contact.store');
Route::get('/contact/thank-you', [ContactController::class, 'thankYou'])->name('evodevops.contact.thank-you');
Route::get('/contact/subject-hints', [ContactController::class, 'subjectHints'])
    ->middleware(['example:contact_ai', 'throttle:20,1'])
    ->name('evodevops.contact.subject-hints');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('home', 'evodevops/home')->name('evodevops.home');

    Route::prefix('admin')->name('evodevops.admin.')->group(function () {
        Route::middleware('example:admin_inbox')->group(function () {
            Route::get('inbox', [InboxController::class, 'show'])->name('inbox.show');
            Route::get('inbox/search', [InboxController::class, 'search'])
                ->middleware('throttle:60,1')
                ->name('inbox.search');
            Route::get('inbox/{submission}', [InboxController::class, 'detail'])->name('inbox.detail');
        });

        Route::middleware(['example:prd_studio', 'evo.admin'])->group(function () {
            Route::get('prd', [PrdController::class, 'show'])->name('prd.show');
            Route::post('prd/generate', [PrdController::class, 'generate'])
                ->middleware('throttle:10,1')
                ->name('prd.generate');
        });

        Route::get('submissions', [SubmissionsController::class, 'index'])->name('submissions.index');
        Route::get('submissions/{submission}', [SubmissionsController::class, 'show'])->name('submissions.show');
        Route::patch('submissions/{submission}/mark-read', [SubmissionsController::class, 'markRead'])->name('submissions.mark-read');
        Route::patch('submissions/{submission}/archive', [SubmissionsController::class, 'archive'])->name('submissions.archive');
    });

    Route::middleware(['example:thread_studio', 'evo.admin'])->group(function () {
        Route::get('ai/thread-studio', [ThreadStudioController::class, 'show'])
            ->name('evodevops.ai.thread-studio.show');
        Route::post('ai/thread-studio', [ThreadStudioController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('evodevops.ai.thread-studio.store');
        Route::post('ai/thread-studio/stream', [ThreadStudioController::class, 'streamCompose'])
            ->middleware('throttle:10,1')
            ->name('evodevops.ai.thread-studio.stream');
    });

    Route::post('ai/voice-input/transcribe', [VoiceInputController::class, 'transcribe'])
        ->middleware(['example:voice_input', 'evo.admin', 'throttle:30,1'])
        ->name('evodevops.ai.voice-input.transcribe');

    Route::post('ai/text-assist/stream', [AiTextAssistController::class, 'streamAssist'])
        ->middleware(['example:ai_text_field', 'evo.admin', 'throttle:20,1'])
        ->name('evodevops.ai.text-assist.stream');
});
