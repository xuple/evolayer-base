<?php

use Illuminate\Support\Facades\Route;

/*
| Loaded only when EVO_BASE_EXAMPLE_MARKETING_PAGES=true.
| Publishes the showcase /about and authenticated /home routes that map onto
| the package's published pages (evodevops/about.tsx, evodevops/home.tsx).
*/

Route::inertia('/about', 'evodevops/about')->name('evodevops.base.about');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('home', 'evodevops/home')->name('evodevops.base.home');
});
