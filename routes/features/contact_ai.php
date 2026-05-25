<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Http\Controllers\ContactController;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_CONTACT_AI=true.
| Provides the contact form, thank-you page, AI subject hints, and the
| triage/attachment pipeline triggered on submission.
*/

Route::get('/contact', [ContactController::class, 'show'])->name('evolayer.base.contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('evolayer.base.contact.store');
Route::get('/contact/thank-you', [ContactController::class, 'thankYou'])->name('evolayer.base.contact.thank-you');
Route::get('/contact/subject-hints', [ContactController::class, 'subjectHints'])
    ->middleware('throttle:20,1')
    ->name('evolayer.base.contact.subject-hints');
