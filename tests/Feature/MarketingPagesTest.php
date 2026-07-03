<?php

use Illuminate\Support\Facades\Route;

test('the public marketing route points at the published evolayer/base page', function () {
    expect(Route::getRoutes()->getByName('evolayer.base.about')->defaults['component'] ?? null)
        ->toBe('evolayer/base');
});

test('the package does not own an authenticated home route', function () {
    // /home is host-owned by the starter; the package must never reclaim it,
    // even with EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES enabled.
    expect(Route::getRoutes()->getByName('evolayer.base.home'))->toBeNull();
});
