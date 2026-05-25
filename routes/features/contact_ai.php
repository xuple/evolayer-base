<?php

use Xuple\EvoLayer\Base\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_CONTACT_AI=true.
| Provides the contact form, thank-you page, AI subject hints, and the
| triage/attachment pipeline triggered on submission.
*/

Route::get('/contact', [ContactController::class, 'show'])->name('evodevops.base.contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('evodevops.base.contact.store');
Route::get('/contact/thank-you', [ContactController::class, 'thankYou'])->name('evodevops.base.contact.thank-you');
Route::get('/contact/subject-hints', [ContactController::class, 'subjectHints'])
    ->middleware('throttle:20,1')
    ->name('evodevops.base.contact.subject-hints');
