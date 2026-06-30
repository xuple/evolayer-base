<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES=true.
| Publishes the showcase /about and authenticated /home routes that map onto
| the package's published pages (evolayer/about.tsx, evolayer/home.tsx).
*/

Route::inertia('/about', 'evolayer/about')->name('evolayer.base.about');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Greeting hour is computed server-side and passed as a prop so the
    // SSR render and client hydration agree (no Date()-in-render mismatch).
    // The `component` default mirrors what Route::inertia() sets internally,
    // keeping the published-component contract introspectable.
    Route::get('home', fn () => Inertia::render('evolayer/home', [
        'greetingHour' => now()->hour,
    ]))->defaults('component', 'evolayer/home')->name('evolayer.base.home');
});
