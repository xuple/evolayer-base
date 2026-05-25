<?php

use Illuminate\Support\Facades\Route;

test('marketing routes point at the published evolayer page components', function () {
    expect(Route::getRoutes()->getByName('evolayer.base.about')->defaults['component'] ?? null)
        ->toBe('evolayer/about')
        ->and(Route::getRoutes()->getByName('evolayer.base.home')->defaults['component'] ?? null)
        ->toBe('evolayer/home');
});
