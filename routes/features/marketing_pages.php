<?php

use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES=true.
| Publishes the showcase /about and authenticated /home routes that map onto
| the package's published pages (evolayer/about.tsx, evolayer/home.tsx).
*/

Route::inertia('/about', 'evolayer/about')->name('evolayer.base.about');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('home', 'evolayer/home')->name('evolayer.base.home');
});
